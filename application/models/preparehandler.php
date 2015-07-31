<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once __DIR__ .'/taskhandler.php';

class PrepareHandler extends TaskHandler
{
	protected $type = 'PREPARE';

	protected function process($task_id, $args)
	{
		$CI =& get_instance();
		$CI->load->library('FFmpeg');

		if (VideoPackage::ORGIN_PKG !== VideoPackage::type($args['full_path'])) {
			throw new TaskFailException("file `{$args['full_path']}` is not a valid video package");
		}

		$work_dir = Helper::prepareDir($this->config->item('video_frags_path'), '/tmp');
		$work_dir = Helper::prepareDir(Helper::tmpFilename($work_dir));

		$meta = VideoPackage::getMeta($args['full_path']);
		$dest_phar = Helper::copy($args['full_path'], Helper::prepareDir($this->config->item('video_store_path')), '.phar');

		$this->log("extract video file into `{$work_dir}`");
		$phar = new Phar($args['full_path']);
		if (! is_file($fn = $work_dir .'/'. $meta['video_file'])) {
			try {
				$phar->extractTo($work_dir, $meta['video_file']);
			}
			catch(Exception $ex) {
				throw new TaskRevertException("can not extract video file from the package to `{$fn}`");
			}
		}

		VideoPackage::del($dest_phar, $meta['video_file']);
		// unset($meta['video_file']);
		$meta['full_path'] = $dest_phar;
		VideoPackage::setMeta($dest_phar, $meta);

		$this->log("determine available resolutions");
		$resolutions = $CI->ffmpeg->determineResolutions($fn);
		if (false === $resolutions) {
			throw new TaskFailException("file `{$fn}` is not a valid video file");
		}

		$option = [
			'dest_phar' => $dest_phar,
			'last_task' => $task_id,
			'full_path' => $fn,
			'work_dir' => $work_dir,
			'playlist' => 'playlist',
			'segtime' => $this->config->item('video_segment_time'),
		];
		if ($option['segtime'] < 2) {
			$option['segtime'] = 2;
		}

		foreach ($resolutions as $width=>$def) {
			$option['resolution'] = $width;
			$option['height'] = $def['height'];
			$option['width'] = $def['width'];
			$option['ab'] = $def['ab'];
			$option['vb'] = $def['vb'];

			$this->queue->enqueue($option, 'SEGMENT', $task_id);
		}

		return true;
	}
}
