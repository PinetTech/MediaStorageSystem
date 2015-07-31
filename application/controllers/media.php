<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Media extends CI_Controller {
	function __construct() {
		parent::__construct();
	}

	public function show() {
		$args = func_get_args();
		$id = array_shift($args);
		$path = implode('/', $args);

		try {
			$this->load->library('KeyValueStore');
		}
		catch (Exception $ex) {
			show_error($ex->getMessage());
			return;
		}
		$phar_path = $this->keyvaluestore->get($id);
		if(!$phar_path || ! Phar::isValidPharFilename($phar_path)) {
			show_404();
			return;
		}

		$phar_path = realpath($phar_path);
		if(strpos($path, 'm3u8')) {
			header('Content-Type: application/x-mpegurl');
		}
		if(strpos($path, '.ts')) {
			header('Content-Type: video/mp2t');
		}
		$p = 'phar://'.$phar_path.'/'.$path;
		echo file_get_contents($p);
	}

	public function test() {
		try {
			$this->load->library('KeyValueStore');
		}
		catch (Exception $ex) {
			show_error($ex->getMessage());
			return;
		}
		$this->keyvaluestore->set('111231314-uuid', '/tmp/a.phar');
		var_dump($this->keyvaluestore->get('test'));
	}


	public function upload()
	{
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$this->load->model('mediastore');
			echo $this->mediastore->store('upfile', $this->input->post(null, true));
		}
		else {
			$this->load->view('up.php');
		}
	}


	public function batch($type='prepare')
	{
		switch ($type) {
		case 'prepare':
		case 'segment':
		case 'publish':
			$handler = "{$type}handler";
			require_once APPPATH ."models/{$handler}.php";
			$queue = 'taskqueue';
			$this->load->model($queue);
			$handler = new $handler($this->$queue, $this->config);
			break;
		default:
			return;
		}
		$this->load->library('VideoPackage');
		$this->load->library('Yaml');
		$this->load->library('Helper');

		$handler->execute();
	}

}
