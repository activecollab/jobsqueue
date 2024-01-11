<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace ActiveCollab\JobsQueue\Queue;

use ActiveCollab\JobsQueue\Batches\BatchInterface;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\JobsDispatcherInterface;
use Countable;

interface QueueInterface extends Countable
{
    const MAIN_CHANNEL = 'main';

    /**
     * Add a job to the queue.
     */
    public function enqueue(
        JobInterface $job,
        string $channel = QueueInterface::MAIN_CHANNEL,
    ): int;

    /**
     * Remove a specific job from the queue.
     */
    public function dequeue(int $job_id): void;

    /**
     * Remove jobs from jobs queue by type.
     */
    public function dequeueByType(string $type, array $properties = null): void;

    /**
     * Execute a job now (sync, waits for a response).
     */
    public function execute(JobInterface $job, bool $silent = true): mixed;

    /**
     * Return true if there's an active job of the give type with the given properties.
     */
    public function exists(string $job_type, array $properties = null): bool;

    public function changePriority(
        string $job_type,
        int $new_priority,
        array $properties = null,
    ): void;

    /**
     * Return a total number of jobs that are in the given channel.
     */
    public function countByChannel(string $channel): int;

    /**
     * Return job by ID.
     */
    public function getJobById(int $job_id): ?JobInterface;

    /**
     * Return Job that is next in line to be executed.
     */
    public function nextInLine(string ...$from_channels): ?JobInterface;

    /**
     * Return a batch of jobs that are next in line to be executed.
     *
     * @return JobInterface[]
     */
    public function nextBatchInLine(int $jobs_to_run, string ...$from_channels): array;

    /**
     * What to do when job fails.
     */
    public function onJobFailure(callable $callback = null): void;

    /**
     * Restore failed job by job ID and optionally update job properties.
     */
    public function restoreFailedJobById(int $job_id, array $update_data = null): JobInterface;

    /**
     * Restore failed jobs by job type.
     */
    public function restoreFailedJobsByType(string $job_type, array $update_data = null): void;
    public function countByType(string $type): int;
    public function countFailed(): int;
    public function countFailedByType(string $type): int;
    public function createBatch(JobsDispatcherInterface $dispatcher, string $name): BatchInterface;
    public function countBatches(): int;

    /**
     * Let jobs report that they raised background process.
     */
    public function reportBackgroundProcess(JobInterface $job, int $process_id): void;

    /**
     * Return a list of background processes that jobs from this queue have launched.
     */
    public function getBackgroundProcesses(): array;

    /**
     * Check stuck jobs.
     */
    public function checkStuckJobs(): void;

    /**
     * Clean up the queue.
     */
    public function cleanUp(): void;

    /**
     * Clear up the all failed jobs.
     */
    public function clear(): void;

    /**
     * Return all distinct reasons why a job of the given type failed us in the past.
     */
    public function getFailedJobReasons(string $job_type): array;

    /**
     * Search for a full job class name.
     */
    public function unfurlType(string $search_for): ?array;

    /**
     * Method that returns failed job statistics.
     *
     * @return array
     */
    public function failedJobStatistics(): array;

    /**
     * Method that returns failed job statistics.
     */
    public function failedJobStatisticsByType(string $event_type): array;

    /**
     * @return array
     */
    public function countJobsByType(): array;

    /**
     * Create one or more tables.
     */
    public function createTables(string ...$additional_tables): void;
}
