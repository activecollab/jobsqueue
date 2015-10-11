<?php

namespace ActiveCollab\JobsQueue\Queue;

use ActiveCollab\JobsQueue\Jobs\JobInterface;
use Countable;

/**
 * @package ActiveCollab\JobsQueue\Queue
 */
interface QueueInterface extends Countable
{
    /**
     * Add a job to the queue
     *
     * @param  JobInterface $job
     * @return mixed
     */
    public function enqueue(JobInterface $job);

    /**
     * Execute a job now (sync, waits for a response)
     *
     * @param  JobInterface $job
     * @return mixed
     */
    public function execute(JobInterface $job);

    /**
     * Return true if there's an active job of the give type with the given properties
     *
     * @param  string     $job_type
     * @param  array|null $properties
     * @return boolean
     */
    public function exists($job_type, array $properties = null);

    /**
     * Return Job that is next in line to be executed
     *
     * @return JobInterface|null
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
     * @param  mixed        $job_id
     * @param  array|null   $update_data
     * @return JobInterface
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