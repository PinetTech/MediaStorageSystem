<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once __DIR__ .'/taskhandler.php';

class PublishHandler extends TaskHandler
{
	protected $type = 'PUBLISH';
	private $inctag = 'G_VIDEO_COUNTER';

	protected function process($task_id, $args)
	{
		$CI =& get_instance();
		try {
			$CI->load->library('KeyValueStore');
		}
		catch (Exception $ex) {
			throw new TaskRevertException($ex->getMessage());
		}

		if (VideoPackage::FINAL_PKG !== VideoPackage::type($args['full_path'])) {
			throw new TaskFailException("invalid video package `{$args['full_path']}`");
		}

		if (! $CI->keyvaluestore->get($this->inctag)) {
			$CI->keyvaluestore->set($this->inctag, 0);
		}
		$CI->keyvaluestore->incr($this->inctag);
		$id = $CI->keyvaluestore->get($this->inctag);

		// save video package info into memcache
		if ($CI->keyvaluestore->set($id, $args['full_path'])) {
			$this->pushVideoPackage($id, VideoPackage::getMeta($args['full_path']));
		}
		else {
			throw new TaskRevertException("can not push video info onto memcahe");
		}
	}

	private function pushVideoPackage($id, $info)
	{
		// todo
		echo "\n--> ";
		var_dump($id, $info);
	}
}

