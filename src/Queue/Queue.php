<?php

  namespace ActiveCollab\JobsQueue\Queue;

  use Countable, ActiveCollab\JobsQueue\Jobs\Job;

  /**
   * @package ActiveCollab\JobsQueue\Queue
   */
  interface Queue extends Countable
  {
    /**
     * Add a job to the queue
     *
     * @param  Job           $job
     * @param  callable|null $on_instance_response
     * @return mixed
     */
    public function enqueue(Job $job, callable $on_instance_response = null);
  }