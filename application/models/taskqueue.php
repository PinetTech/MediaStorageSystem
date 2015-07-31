<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class TaskQueue extends CI_Model
{
	const TASK_STATUS_INITIAL = 0;
	const TASK_STATUS_DONE = 1;
	const TASK_STATUS_WORKING = 2;
	const TASK_STATUS_FAILED = 4;

	public function __construct()
	{
		parent::__construct();
		$this->load->database();
	}

	public function enqueue($args, $type='PREPARE', $last_task=0)
	{
		$data = [
			'id' => 0,
			'type' => $type,
			'status' => 0,
			'arguments' => json_encode($args),
			'try_count' => 0,
			'last_task' => $last_task,
			'last_try_date' => '',
		];

		if (! $this->db->insert('tasks', $data)) {
			throw new Exception('enqueue error #1');
		}
		return true;
	}

	public function dequeue($status, $type='PREPARE')
	{
		$this->db->where('type', $type);
		$this->db->where('status', $status);
		$this->db->order_by('try_count, create_date');

		$task = $this->db->get('tasks', 1)->row();
		if ('PUBLISH' === $type && $task) {
			// 先判断其他分片任务是否完成
			$result = $this->db->query("
			select count(1) as cnt from tasks where last_task in (
				select last_task from tasks where last_task = ?
			) and status <> ?
			", [ $task->id, self::TASK_STATUS_DONE ]);

			if ($result && $result = $result->row(0)) {
				if ($result->cnt > 0) {
					return false;
				}
			}
		}
		return $task;
	}

	public function tries($id)
	{
		$sql = 'update tasks set try_count = 1 + try_count, last_try_date = now() where id = ?';
		$result = $this->db->query($sql, [ $id ]);

		return $result;
	}

	public function update($id, $status, $errmsg=null)
	{
		$sql = 'update tasks set status = ?, last_error = ? where id = ?';
		$result = $this->db->query($sql, [ $status, $errmsg, $id ]);

		return $result;
	}
}
