<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once __DIR__ .'/taskhandler.php';

class SegmentHandler extends TaskHandler
{
	protected $type = 'SEGMENT';

	protected function process($task_id, $args)
	{
		$CI =& get_instance();
		$CI->load->library('FFmpeg');

		$phar_file = $args['dest_phar'];
		if (false === VideoPackage::type($phar_file)) {
			throw new TaskFailException("invalid destination video package `{$phar_file}`");
		}

		$meta = VideoPackage::getMeta($phar_file);
		if (! isset($meta['resolutions'])) {
			$meta['resolutions'] = [ ];
		}
		if (! in_array($args['width'], $meta['resolutions'])) {
			$meta['resolutions'][] = $args['width'];
		}

		$files = $CI->ffmpeg->convert($args['full_path'], $args, $args['work_dir']);
		if (false === $files) {
			throw new TaskFailException("convert video `{$args['full_path']}` fails");
		}

		$this->log("adding playlist and ts into `{$phar_file}`");
		$phar = new Phar($phar_file);
		foreach ($files as $fn) {
			$phar->addFile($args['work_dir'] .'/'. $fn, $fn);
		}
		$phar = null;
		$this->log("refresh meta info");
		if (! VideoPackage::setMeta($phar_file, $meta)) {
			throw new TaskFailException("could not update `{$phar_file}` meta info: ". var_export($meta, true));
		}

		$new_task = [
			'full_path' => $phar_file,
		];
		$this->queue->enqueue($new_task, 'PUBLISH', $task_id);
	}
}
