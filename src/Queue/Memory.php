<?php

  namespace ActiveCollab\JobsQueue\Queue;

  use ActiveCollab\JobsQueue\Jobs\Job;

  /**
   * @package ActiveCollab\JobsQueue\Queue
   */
  class Memory implements Queue
  {
    /**
     * @var array
     */
    private $data = [];

    /**
     * Add a job to the queue
     *
     * @param  Job     $job
     * @return integer
     */
    public function enqueue(Job $job)
    {
      $this->data[] = $job;

      return $this->count() - 1;
    }

    /**
     * Run job now (sync, waits for a response)
     *
     * @param  Job   $job
     * @return mixed
     */
    public function run(Job $job)
    {
      return $job->run();
    }

    /**
     * @return int
     */
    public function count()
    {
      return count($this->data);
    }
  }