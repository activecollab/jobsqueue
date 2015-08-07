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
     * @param  Job           $job
     * @param  callable|null $on_instance_response
     * @return mixed
     */
    public function dispatch(Job $job, callable $on_instance_response = null)
    {

    }

    /**
     * @return Queue
     */
    public function &getQueue()
    {
      return $this->queue;
    }
  }