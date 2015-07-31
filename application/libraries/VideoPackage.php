<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class VideoPackage
{
	const ORGIN_PKG = 1;		// 上传的源视频及其 meta 信息
	const FINAL_PKG = 2;		// 最终可供播放的 phar

	public static $index = 'video.yaml';

	
	public static function type($fn)
	{
		if (! Phar::isValidPharFilename($fn)) {
			return false;
		}

		$meta = self::getMeta($fn);
		if ($meta && isset($meta['title'])) {
			if (isset($meta['resolutions']) && is_array($meta['resolutions']) && count($meta['resolutions'])) {
				return self::FINAL_PKG;
			}
			else if (isset($meta['video_file']) && ! empty($meta['video_file'])) {
				return self::ORGIN_PKG;
			}
		}
		return false;
	}

	public static function getMeta($fn)
	{
		static $meta = [ ];
		static $yaml;
		if (! isset($yaml)) {
			$yaml = new Yaml;
		}
		if (is_file($fn) && ! isset($meta[$fn=realpath($fn)])) {
			$text = file_get_contents("phar://{$fn}/". self::$index);
			$meta[$fn] = $yaml->parse($text);
		}

		return isset($meta[$fn]) ? $meta[$fn] : null;
	}

	public static function setMeta($fn, $meta)
	{
		static $yaml;
		if (! isset($yaml)) {
			$yaml = new Yaml;
		}
		if (false !== self::type($fn)) {
			$phar = new Phar($fn);
			$phar[self::$index] = $yaml->dump($meta);
			$phar = null;
			return true;
		}
		return false;
	}

	public static function del($fn, $entry)
	{
		if (false !== self::type($fn)) {
			$phar = new Phar($fn);
			if ($entry === self::$index) {
				throw new Exception("could not delete the meta file");
			}
			if ($phar->offsetExists($entry)) {
				return $phar->delete($entry);
			}
		}
		return false;
	}
}
