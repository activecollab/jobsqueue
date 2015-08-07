<?php

  namespace ActiveCollab\JobsQueue;

  use ActiveCollab\JobsQueue\Jobs\Job;
  use ActiveCollab\JobsQueue\Queue\Queue;

  /**
   * @package ActiveCollab\JobsQueue
   */
  class Dispatcher
  {
    /**
     * @var Queue
     */
    private $queue;

    /**
     * @param Queue $queue
     */
    public function __construct(Queue $queue)
    {
      $this->queue = $queue;
    }

    /**
     * Add a job to the queue
     *
     * @param  Job   $job
     * @return mixed
     */
    public function dispatch(Job $job)
    {
      return $this->queue->enqueue($job);
    }

    /**
     * Run a job now (sync, waits for a response)
     *
     * @param  Job $job
     * @return mixed
     */
    public function run(Job $job)
    {
      return $this->queue->run($job);
    }

    /**
     * @return Queue
     */
    public function &getQueue()
    {
      return $this->queue;
    }
  }