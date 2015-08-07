<?php

  namespace ActiveCollab\JobsQueue\Queue;

  use ActiveCollab\JobsQueue\Jobs\Job;

  /**
   * @package ActiveCollab\JobsQueue\Queue
   */
  class ArrayQueue implements Queue
  {
    /**
     * @var array
     */
    private $data = [];

    /**
     * Add a job to the queue
     *
     * @param  Job           $job
     * @param  callable|null $on_instance_response
     * @return mixed
     */
    public function enqueue(Job $job, callable $on_instance_response = null)
    {

    }

    /**
     * @return int
     */
    public function count()
    {
      return count($this->data);
    }
  }