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
     * @param  Job   $job
     * @return mixed
     */
    public function enqueue(Job $job);

    /**
     * Run job now (sync, waits for a response)
     *
     * @param  Job   $job
     * @return mixed
     */
    public function run(Job $job);
  }