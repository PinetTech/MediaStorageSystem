<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class TaskHandler
{
	protected $type;

	protected $config;
	protected $queue;

	public function __construct(TaskQueue $queue, CI_Config $config)
	{
		$this->queue = $queue;
		$this->config = $config;
	}


	public function execute()
	{
		$this->log("fetching a task...");
		$task = $this->queue->dequeue(TaskQueue::TASK_STATUS_INITIAL, $this->type);
		if ($task) {
			$this->log("task {$task->id} found.");
			$this->queue->tries($task->id);
			try {
				$this->process($task->id, json_decode($task->arguments,true));
				$this->done($task->id);
			}
			catch (TaskRevertException $ex) {
				$this->revert($task->id, $ex->getMessage());
				return;
			}	
			catch (Exception $ex) {
				$this->fail($task->id, $ex->getMessage());
				return;
			}
			$this->log("OK");
		}
	}

	public function log($msg, $level=E_NOTICE)
	{
		static $map;
		if (! isset($map)) {
			$map = [
				E_ERROR => 'ERROR',
				E_NOTICE => 'NOTICE',
				E_WARNING => 'WARNING',
			];
		}
		$now = date('Y-m-d H:i:s');

		printf("[%s] [%s]\t%s\n",
			isset($map[$level]) ? $map[$level] : $level,
			$now,
			$msg);
	}

	protected function process($task_id, $args)
	{
	}

	protected function done($id)
	{
		return $this->queue->update($id, TaskQueue::TASK_STATUS_DONE, null);
	}

	protected function fail($id, $errmsg)
	{
		$this->log($errmsg, E_ERROR);
		return $this->queue->update($id, TaskQueue::TASK_STATUS_FAILED, $errmsg);
	}

	protected function revert($id, $errmsg)
	{
		$this->log($errmsg, E_ERROR);
		return $this->queue->update($id, TaskQueue::TASK_STATUS_INITIAL, $errmsg);
	}
}


class TaskFailException extends Exception { }

class TaskRevertException extends Exception { }
