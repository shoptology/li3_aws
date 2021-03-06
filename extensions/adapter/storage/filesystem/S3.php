<?php

namespace li3_aws\extensions\adapter\storage\filesystem;

/**
 * An Simple Storage Service (S3) filesystem adapter implementation.
 *
 * The S3 adapter is meant to be used through the `FileSystem` interface, which abstracts away
 * bucket creation, adapter instantiation, and filter implemenation.
 *
 * A simple configuration of this adapter can be accomplished in `config/bootstrap/filesystem.php`
 * as follows:
 *
 * {{{
 * FileSystem::config(array(
 *	'cloud' => 'array('adapter' => 'S3'),
 * ));
 * }}}
 *
 */
class S3 extends \lithium\core\Object {
	/**
	 * Class constructor.
	 *
	 * @see li3_filesystem\extensions\storage\FileSystem::config()
	 * @param array $config
	 */
	public function __construct(array $config = array()) {
		$defaults = array(
			'key' => 'your_aws_access_key',
			'secret' => 'your_aws_secret',
			'bucket' => 'your_default_bucket',
			'region' => \AmazonS3::REGION_EU_W1,
			'certificate_authority' => true,
		);
		parent::__construct($config + $defaults);
	}

	/**
	 * Writes files to Amazon S3 buckets.
	 * @param string $filename The name of the file to write
	 * @param array $data The body of the request (This includes the data which should write to file on Amazon S3)
	 * @param array $options
	 */
	public function write($filename, $data, array $options = array()) {
		$s3 = new \AmazonS3($this->_config);
		$bucket = $this->_config['bucket'];
		$region = $this->_config['region'];

		return function($self, $params) use ($s3, $bucket, $region) {
			$defaults = array(
				'acl' => \AmazonS3::ACL_PUBLIC,
				'body' => $params['data']
			);

			if (empty($params['options'])) {
				$params['options'] = array();
			}

			$params['options'] += $defaults;
			$filename = $params['filename'];

			// we were seeing issues that if we passed a path that started with
			// "/" that it wouldn't write correctly this should fix it
			if (strpos($filename, '/') === 0) {
				$filename = preg_replace('/^\//', '', $filename, 1);
			}

			if (! $s3->if_bucket_exists($bucket)) {
				$s3->create_bucket($bucket, $region);
			}

			if ($s3->if_object_exists($bucket, $filename)) {
				// @TODO: implement logic when file exists
			}

			return $s3->create_object($bucket, $filename, $params['options']);
		};
	}

	/**
	 * Read files from amazon s3 storage.
	 * @param string $filename The name of the file to read
	 * @param array $options
	 */
	public function read($filename, array $options = array()) {
		$s3 = new \AmazonS3($this->_config);
		$bucket = $this->_config['bucket'];
		$region = $this->_config['region'];

		return function($self, $params) use ($s3, $bucket, $region) {
			$defaults = array(
				'url_only' => false
			);
			if (empty($params['options'])) {
				$params['options'] = array();
			}

			$params['options'] += $defaults;
			$filename = $params['filename'];

			// we were seeing issues that if we passed a path that started with
			// "/" that it wouldn't write correctly this should fix it
			if (strpos($filename, '/') === 0) {
				$filename = preg_replace('/^\//', '', $filename, 1);
			}

			if ($params['options']['url_only'] === true) {
				$urlTimeout = (isset($params['options']['url_timeout'])) ? $params['options']['url_timeout'] : 0;
				return $s3->get_object_url($bucket, $filename, $urlTimeout, $params['options']);
			}

			$result = $s3->get_object($bucket, $filename, $params['options']);
			if ($result->status == 200) {
				return $result->body;
			}
			return "";
		};

	}

	/**
	 * Deletes a file from Amazon S3 storage.
	 * @param string $filename The name of the file to delete
	 * 	(The filename should includes the full path in your S3 bucket with subfolders)
	 * @param array $options
	 */
	public function delete($filename, array $options = array()) {
		$s3 = new \AmazonS3($this->_config);
		$bucket = $this->_config['bucket'];
		$region = $this->_config['region'];

		return function($self, $params) use ($s3, $bucket, $region) {
			$filename = $params['filename'];

			// we were seeing issues that if we passed a path that started with
			// "/" that it wouldn't write correctly this should fix it
			if (strpos($filename, '/') === 0) {
				$filename = preg_replace('/^\//', '', $filename, 1);
			}

			return $s3->delete_object($bucket, $filename, $params['options']);
		};
	}

	/**
	 * Copy files on amazon.
	 * The source bucket will get from filesystem configuration,
	 * the destination bucket have to set in options array, to keep generic copy method in filesystem layer.
	 * @param string $srcFilename The source filename with fullpath
	 * @param unknown_type $destFilename The destination filename with fullpath
	 * @param array $options The options array is required, because here we set the destination bucket.
	 */
	public function copy($srcFilename, $destFilename, array $options) {
		$s3 = new \AmazonS3($this->_config);
		$srcBucket = $this->_config['bucket'];
		$region = $this->_config['region'];

		if(!isset($options['destBucket'])) {
			return false;
		}

		// we were seeing issues that if we passed a path that started with
		// "/" that it wouldn't write correctly this should fix it
		if (strpos($srcFilename, '/') === 0) {
			$srcFilename = preg_replace('/^\//', '', $srcFilename, 1);
		}

		$source = array('bucket' => $srcBucket, 'filename' => $srcFilename);
		$dest = array('bucket' => $options['destBucket'], 'filename' => $destFilename);

		return function($self, $params) use ($s3, $source, $dest, $region) {
			$defaults = array('acl' => \AmazonS3::ACL_PUBLIC);
			$params['options'] += $defaults;

			return $s3->copy_object($source, $dest, $params['options']);
		};
	}
}
