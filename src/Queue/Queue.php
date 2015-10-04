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
     * @param  Job $job
     * @return mixed
     */
    public function execute(Job $job);

    /**
     * Return Job that is next in line to be executed
     *
     * @return Job|null
     */
    public function nextInLine();

    /**
     * What to do when job fails
     *
     * @param callable|null $callback
     */
    public function onJobFailure(callable $callback = null);

    /**
     * Restore failed job by job ID and optionally update job properties
     *
     * @param  mixed      $job_id
     * @param  array|null $update_data
     * @return Job
     */
    public function restoreFailedJobById($job_id, array $update_data = null);

    /**
     * Restore failed jobs by job type
     *
     * @param string     $job_type
     * @param array|null $update_data
     */
    public function restoreFailedJobsByType($job_type, array $update_data = null);

    /**
     * @param  string $type1
     * @return integer
     */
    public function countByType($type1);

    /**
     * @return integer
     */
    public function countFailed();

    /**
     * @param  string  $type1
     * @return integer
     */
    public function countFailedByType($type1);

    /**
     * Check stuck jobs
     */
    public function checkStuckJobs();

    /**
     * Clean up the queue
     */
    public function cleanUp();
}