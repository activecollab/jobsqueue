<?php

namespace ActiveCollab\JobsQueue\Queue;

use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\Jobs\JobInterface;

/**
 * @package ActiveCollab\JobsQueue\Queue
 */
class TestQueue implements QueueInterface
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
     * @param  JobInterface $job
     * @param  string       $channel
     * @return integer
     */
    public function enqueue(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
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
     * @param  JobInterface $job
     * @param  string       $channel
     * @return mixed
     */
    public function execute(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
    {
        return $job->execute();
    }

    /**
     * Return a total number of jobs that are in the given channel
     *
     * @param  string $channel
     * @return integer
     */
    public function countByChannel($channel)
    {
        return count($this->jobs);
    }

    /**
     * Return true if there's an active job of the give type with the given properties
     *
     * @param  string     $job_type
     * @param  array|null $properties
     * @return boolean
     */
    public function exists($job_type, array $properties = null)
    {
    }

    /**
     * Return Job that is next in line to be executed
     *
     * @param  string ...$from_channels
     * @return JobInterface|null
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
     * Restore failed job by job ID and optionally update job properties
     *
     * @param  mixed      $job_id
     * @param  array|null $update_data
     * @return Job
     */
    public function restoreFailedJobById($job_id, array $update_data = null)
    {
    }

    /**
     * Restore failed jobs by job type
     *
     * @param string     $job_type
     * @param array|null $update_data
     */
    public function restoreFailedJobsByType($job_type, array $update_data = null)
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
     * @param  string $type1
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
     * @param  string $type1
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
     * Let jobs report that they raised background process
     *
     * @param JobInterface $job
     * @param integer      $process_id
     */
    public function reportBackgroundProcess(JobInterface $job, $process_id)
    {
    }

    /**
     * Return a list of background processes that jobs from this queue have launched
     *
     * @return array
     */
    public function getBackgroundProcesses()
    {
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
     * @var callable[]
     */
    private $on_job_failure = [];

    /**
     * What to do when job fails
     *
     * @param callable|null $callback
     */
    public function onJobFailure(callable $callback = null)
    {
        $this->on_job_failure[] = $callback;
    }

    /**
     * Create one or more tables
     *
     * @param  list - string sql table definition
     * @throws Exception
     */
    public function createTables()
    {
    }

    /**
     * Clear up the all failed jobs
     */
    public function clear()
    {
    }

    /**
     * Return all distinct reasons why a job of the given type failed us in the past
     *
     * @param string $job_type
     * @returns array
     */
    public function getFailedJobReasons($job_type)
    {
        return [];
    }

    /**
     * Search for a full job class name
     *
     * @param string $search_for
     * @return mixed
     * @throws \Exception
     */
    public function unfurlType($search_for)
    {
    }

    /**
     * Method that returns failed job statistics
     *
     * @return array Key is job type, value is an array where keys are dates and values are number of failed jobs on that particular day.
     */
    public function failedJobStatistics()
    {
        return [];
    }

    /**
     * Method that returns failed job statistics
     *
     * @param $event_type
     * @return array Returns array where keys are dates and values are number of failed jobs on that particular day.
     */
    public function failedJobStatisticsByType($event_type)
    {
        return [];
    }

    /**
     * @return array where key is job type and value is number of jobs in the queue of that type.
     */
    public function countJobsByType()
    {
        return [];
    }
}
