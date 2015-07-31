<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class MediaStore extends CI_Model
{
	const ORGIN_PKG = 1;		// 上传的源视频及其 meta 信息
	const FINAL_PKG = 2;		// 最终可供播放的 phar

	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library('Yaml');
		$this->load->library('Helper');
		$this->load->library('VideoPackage');
	}

	public function store($fieldname, $data=null)
	{
		$this->load->model('taskqueue');
		try {
			$save_path = Helper::prepareDir($this->config->item('video_upload_path'), '/tmp'); 
		}
		catch (Exception $ex) {
			return $ex->getMessage();
		}

		if (! ($types = $this->config->item('allowed_video_types'))) {
			$types = '*';
		}
		$this->load->library('upload', [
			'upload_path' => $save_path,
			'allowed_types' => $types,
		]);
		if (! $this->upload->do_upload($fieldname)) {
			return $this->upload->display_errors();
		}

		$finfo = $this->upload->data();
		$args = array_merge($data ?: [ ], [
			'full_path' => $finfo['full_path'],
		]);

		$pkgtype = VideoPackage::type($finfo['full_path']);
		$tasktype = 'PREPARE';
		if (false === $pkgtype) {
			if (! isset($args['title']) || empty($args['title'])) {
				$args['title'] = '.';
			}
			$result = $this->buildOrginalPackage($args);
			if ("OK" !== $result) {
				return $result;
			}
		}
		try {
			$this->taskqueue->enqueue($args, $tasktype);
		}
		catch (Exception $ex) {
			return $ex->getMessage();
		}
		return "OK";
	}

	/**
	 * 打包上传的视频及相关信息
	 */
	protected function buildOrginalPackage(&$args)
	{
		$flags = FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME;
		$pictures = 'png|jpg|jpeg|jpe|gif';

		$fn = $args['full_path'];
		$dest_phar = $this->helper->tmpFilename(dirname($fn), '.phar');

		if ($dest_phar) {
			$this->upload->set_allowed_types($pictures);

			$meta = $args;
			$meta['video_file'] = basename($fn);
			$meta['full_path'] = $dest_phar;

			try {
				$phar = new Phar($dest_phar, $flags);

				// todo, add posters
				$meta['posters'] = [ ];
				// todo, add screenshots
				$meta['screenshots'] = [ ];
				// add video file
				$phar->addFile($fn, $meta['video_file']);

				// update meta index
				$phar[VideoPackage::$index] = $this->yaml->dump($meta);
				$phar = null;

				// return dest_phar
				$args['full_path'] = $dest_phar;
				return 'OK';
			} catch (Exception $ex) {
				// rollback 
				$args['full_path'] = $fn;
				return $ex->getMessage();
			}
		}

		return 'package upload files error';
	}

}
