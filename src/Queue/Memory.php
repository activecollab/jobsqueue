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
     * @var bool
     */
    private $needs_sort = false;

    /**
     * Add a job to the queue
     *
     * @param  Job     $job
     * @return integer
     */
    public function enqueue(Job $job)
    {
      $this->data[] = $job;

      if (!$this->needs_sort) {
        $this->needs_sort = true;
      }

      return $this->count() - 1;
    }

    /**
     * Run job now (sync, waits for a response)
     *
     * @param  Job   $job
     * @return mixed
     */
    public function execute(Job $job)
    {
      return $job->execute();
    }

    /**
     * Return Job that is next in line to be executed
     *
     * @return Job|null
     */
    public function nextInLine()
    {
      if (empty($this->data)) {
        return null;
      }

      if ($this->needs_sort) {
        $this->sortByPriority($this->data);
      }

      return $this->data[0];
    }

    /**
     * Sort jobs so priority ones are at the beginning of the array
     *
     * @param array $data
     */
    private function sortByPriority(array &$data)
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