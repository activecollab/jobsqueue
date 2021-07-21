<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Queue;

use ActiveCollab\JobsQueue\Batches\BatchInterface;
use ActiveCollab\JobsQueue\DispatcherInterface;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use Countable;

/**
 * @package ActiveCollab\JobsQueue\Queue
 */
interface QueueInterface extends Countable
{
    const MAIN_CHANNEL = 'main';

    /**
     * Add a job to the queue.
     *
     * @param  JobInterface $job
     * @param  string       $channel
     * @return mixed
     */
    public function enqueue(JobInterface $job, $channel = self::MAIN_CHANNEL);

    /**
     * Remove a specific job from the queue.
     *
     * @param mixed $job_id
     */
    public function dequeue($job_id);

    /**
     * Remove jobs from jobs queue by type.
     *
     * @param string $type
     */
    public function dequeueByType($type);

    /**
     * Execute a job now (sync, waits for a response).
     *
     * @param  JobInterface $job
     * @param  bool         $silent
     * @return mixed
     */
    public function execute(JobInterface $job, $silent = true);

    /**
     * Return true if there's an active job of the give type with the given properties.
     *
     * @param  string     $job_type
     * @param  array|null $properties
     * @return bool
     */
    public function exists($job_type, array $properties = null);

    /**
     * Return a total number of jobs that are in the given channel.
     *
     * @param  string $channel
     * @return int
     */
    public function countByChannel($channel);

    /**
     * Return job by ID.
     *
     * @param  int               $job_id
     * @return JobInterface|null
     */
    public function getJobById($job_id);

    /**
     * Return Job that is next in line to be executed.
     *
     * @param  string[]          ...$from_channels
     * @return JobInterface|null
     */
    public function nextInLine(...$from_channels);

    /**
     * Return a batch of jobs that are next in line to be executed.
     *
     * @param  int             $jobs_to_run
     * @param  string[]        ...$from_channels
     * @return JobInterface[]
     */
    public function nextBatchInLine($jobs_to_run, ...$from_channels);

    /**
     * What to do when job fails.
     *
     * @param callable|null $callback
     */
    public function onJobFailure(callable $callback = null);

    /**
     * Restore failed job by job ID and optionally update job properties.
     *
     * @param  mixed        $job_id
     * @param  array|null   $update_data
     * @return JobInterface
     */
    public function restoreFailedJobById($job_id, array $update_data = null);

    /**
     * Restore failed jobs by job type.
     *
     * @param string     $job_type
     * @param array|null $update_data
     */
    public function restoreFailedJobsByType($job_type, array $update_data = null);

    /**
     * @param  string $type1
     * @return int
     */
    public function countByType($type1);

    /**
     * @return int
     */
    public function countFailed();

    /**
     * @param  string $type1
     * @return int
     */
    public function countFailedByType($type1);

    /**
     * Create a new batch with the given name.
     *
     * @param  DispatcherInterface $dispatcher
     * @param  string              $name
     * @return BatchInterface
     */
    public function createBatch(DispatcherInterface &$dispatcher, $name);

    /**
     * Return total number of batches in the queue.
     *
     * @return int
     */
    public function countBatches();

    /**
     * Let jobs report that they raised background process.
     *
     * @param JobInterface $job
     * @param int          $process_id
     */
    public function reportBackgroundProcess(JobInterface $job, $process_id);

    /**
     * Return a list of background processes that jobs from this queue have launched.
     *
     * @return array
     */
    public function getBackgroundProcesses();

    /**
     * Check stuck jobs.
     */
    public function checkStuckJobs();

    /**
     * Clean up the queue.
     */
    public function cleanUp();

    /**
     * Clear up the all failed jobs.
     */
    public function clear();

    /**
     * Return all distinct reasons why a job of the given type failed us in the past.
     *
     * @param string $job_type
     * @returns array
     */
    public function getFailedJobReasons($job_type);

    /**
     * Search for a full job class name.
     *
     * @param  string   $search_for
     * @return string[]
     */
    public function unfurlType($search_for);

    /**
     * Method that returns failed job statistics.
     *
     * @return array
     */
    public function failedJobStatistics();

    /**
     * Method that returns failed job statistics.
     *
     * @param  string $event_type
     * @return array
     */
    public function failedJobStatisticsByType($event_type);

    /**
     * @return array
     */
    public function countJobsByType();

    /**
     * Create one or more tables.
     *
     * @param string[] ...$additional_tables
     */
    public function createTables(...$additional_tables);
}
