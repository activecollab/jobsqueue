<?php

  namespace ActiveCollab\JobsQueue\Queue;

  use ActiveCollab\JobsQueue\Jobs\Job;

  /**
   * @package ActiveCollab\JobsQueue\Queue
   */
  class Test implements Queue
  {
    /**
     * @var Job[]
     */
    private $jobs = [];

    /**
     * @var Job[]
     */
    private $failed_jobs = [];

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
      $this->jobs[] = $job;

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
      if (empty($this->jobs)) {
        return null;
      }

      if ($this->needs_sort) {
        $this->sortByPriority($this->jobs);
      }

      return $this->jobs[0];
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
      return count($this->jobs);
    }

    /**
     * @param  string  $type1
     * @return integer
     */
    public function countByType($type1)
    {
      $count = 0;

      $types = func_get_args();

      foreach ($this->jobs as $job) {
        if (in_array(get_class($job), $types)) {
          $count++;
        }
      }

      return $count;
    }

    /**
     * @return integer
     */
    public function countFailed()
    {
      return count($this->failed_jobs);
    }

    /**
     * @param  string  $type1
     * @return integer
     */
    public function countFailedByType($type1)
    {
      $count = 0;

      $types = func_get_args();

      foreach ($this->failed_jobs as $job) {
        if (in_array(get_class($job), $types)) {
          $count++;
        }
      }

      return $count;
    }

    /**
     * Check stuck jobs
     */
    public function checkStuckJobs()
    {
    }

    /**
     * Clean up the queue
     */
    public function cleanUp()
    {
    }

    /**
     * @var callable
     */
    private $on_job_failure;

    /**
     * What to do when job fails
     *
     * @param callable|null $callback
     */
    public function onJobFailure(callable $callback = null)
    {
      $this->on_job_failure = $callback;
    }
  }