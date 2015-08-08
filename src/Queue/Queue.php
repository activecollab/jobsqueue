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
     * Execute a job now (sync, waits for a response)
     *
     * @param  Job   $job
     * @return mixed
     */
    public function execute(Job $job);

    /**
     * Return Job that is next in line to be executed
     *
     * @return Job|null
     */
    public function nextInLine();
  }