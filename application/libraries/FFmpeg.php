<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class FFmpeg
{
	private $ffmpeg;
	private $config;

	public function __construct()
	{
		$CI =& get_instance();
		$this->config = $CI->config;

		$ffmpeg = $this->config->item("ffmpeg_bin");
		if (is_executable($ffmpeg)) {
			$this->ffmpeg = $ffmpeg;
		}
		else {
			throw new Exception("count not found ffmpeg");
		}
	}

	public function convert($file, $option, $work_dir='/tmp') 
	{
		$prefix = "{$work_dir}/{$option['width']}";
		if (file_exists($prefix)) {
			Helper::rm($prefix);
		}
		mkdir($prefix);

		$playlist = $prefix .'/'. $option['playlist'] .'.m3u8';
		$command = sprintf($this->ffmpeg .' -i "%s" -codec:a aac -strict -2 -codec:v libx264 -b:a %dk -b:v %dk -s %dx%d -f segment -segment_time %d -segment_list %s ',
			$file, $option['ab'], $option['vb'], $option['width'], $option['height'], $option['segtime'], $playlist);

		$command .= " {$prefix}/video_%03d.ts";
		exec($command, $output, $retcode);

		if ($retcode == 0) {
			$list = glob("{$prefix}/*.ts");
			$list[] = $playlist;

			$skip = strlen($work_dir);
			return array_map(function($file) use($skip) {
				return substr($file, $skip);
			}, $list);
		}

		return false;
	}

	public function determineResolutions($fn)
	{
		$resolutions = $this->config->item('resolutions');
		if (! $resolutions) {
			$resolutions = [ '640' => [ 'ab' => '80', 'vb' => '1600' ], ];
		}
		$ret = [ ];

		$info = $this->queryVideoInfo($fn);
		if (isset($info['bitrate']) && isset($info['video']['width'])) {
			$br = + $info['bitrate'];
			$min = 1920;
			foreach ($resolutions as $k => $v) {
				if ($br > $v['vb']) {
					$ret[$k] = $v;
				}
				if ($k < $min) {
					$min = $k;
				}
			}
			$ret = count($ret) ? $ret : [ "{$min}" => $resolutions[$min] ];

			$r = $info['video']['width'] / $info['video']['height'];
			foreach ($ret as $k => &$v) {
				$v['height'] = intval($k / $r);
				if ($v['height'] % 2) {		// asure height divisible by 2
					$v['height'] += 1;
				}
				$v['width'] = $k;
			}
			unset($v);

			return $ret;
		}

		return false;
	}

	public function queryVideoInfo($fn) 
	{
		$output = [ ];
		$command = $this->ffmpeg .' -i "'. $fn .'"';

		$fd = proc_open($command, [ 2 => ['pipe','w'] ], $output);
		$stderr = stream_get_contents($output[2]);
		fclose($output[2]);
		proc_close($fd);

		$lines = trim(strstr($stderr, 'Input #0'));
		$info = [ ];
		if ($lines) foreach (explode("\n",$lines) as $row) {
			if ($row = trim($row)) {
				if (strpos($row, 'Input #0') === 0) {
					list($k, $info['format']) = explode(', ', $row);
				}
				else if (strpos($row, 'Duration') === 0) {
					foreach (explode(', ',$row) as $unit) {
						list($k, $v) = explode(': ', $unit);
						$info[strtolower($k)] = $v;
					}
				}
				else if (strpos($row, 'Stream #0:') === 0) {
					list($k, $type, $row) = explode(': ', $row);
					if ($type === 'Video') {
						$info['video'] = self::extractVideoInfo($row);
					}
					else if ($type === 'Audio') {
						$info['audio'] = self::extractAudioInfo($row);
					}
				}
			}
		}

		return $info;
	}

	protected static function extractVideoInfo($string)
	{
		$stream = [ ];
		list($stream['codec'], $stream['color_range'], $k, $fps, $v) = explode(', ',$string);

		preg_match_all("/(?P<w>\d+)x(?<h>\d+)/", $k, $matches);
		$stream['fps'] = preg_replace("/[^.0-9]/", '', $fps);
		$stream['width'] = $matches['w'][0];
		$stream['height'] = $matches['h'][0];

		return $stream;
	}

	protected static function extractAudioInfo($string)
	{
		$stream = [ ];
		list($stream['codec'], $stream['sample'], $stream['stereo'], $v) = explode(', ',$string);

		return $stream;
	}

}
