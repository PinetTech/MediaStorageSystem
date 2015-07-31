<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Helper
{
	public static function hash()
	{
		// todo
		return '0001';
	}

	public static function guid()
	{
		$charid = strtoupper(md5(uniqid(mt_rand(), true)));
		$hyphen = chr(45);	// "-"
		$uuid =  substr($charid, 0, 8).$hyphen
			.substr($charid, 8, 4).$hyphen
			.substr($charid,12, 4).$hyphen
			.substr($charid,16, 4).$hyphen
			.substr($charid,20,12);

		return $uuid;
	}

	public static function tmpFilename($prefix, $suffix='')
	{
		$tries = 5;
		if (is_writable($prefix)) do {
			$dest = $prefix .'/'. microtime(true) .$suffix;
			if (! file_exists($dest)) {
				return $dest;
			}
			if ($tries < 0) {
				break;
			}
			usleep(mt_rand(50,120) * 1000);
		} while (true);

		return false;
	}

	public static function generateFilename($prefix, $ext = '.phar')
	{
		if (is_writable($prefix)) {
			return $prefix .'/'. self::hash() .'_'. self::guid() .$ext;
		}
		return false;
	}

	public static function rm($path)
	{
		if (is_dir($path)) {
			$it = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
			$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($files as $file) {
				if ($file->isDir()) {
					call_user_func('self::'.__FUNCTION__, $file->getRealPath());
				}
				else {
					unlink($file->getRealPath());
				}
			}
			rmdir($path);
		}
		else {
			unlink($path);
		}
	}

	public static function prepareDir($dir, $fallback="/tmp", &$errmsg=null)
	{
		if ( !$dir && $fallback) {
			$dir = $fallback;
		}

		if (is_dir($dir)) {
			if (! is_writable($dir)) {
				throw new Exception("destination `{$dir}` not writable");
			}
		}
		else {
			if (! @mkdir($dir, 0777, true)) {
				throw new Exception("failed to mkdir `{$dir}`");
			}
		}

		return $dir;
	}

	public static function copy($file, $prefix, $suffix='')
	{
		$dest = self::generateFilename($prefix, $suffix);
		if (! $dest) {
			throw new Exception("could not create destination file in `{$prefix}`");
		}
		if (! copy($file, $dest)) {
			throw new Exception("failed to copy file `{$file}` into `{$dest}`");
		}
		return $dest;
	}
}
