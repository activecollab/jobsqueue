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
use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\JobsDispatcherInterface;
use LogicException;

class TestQueue extends Queue
{
    /**
     * @var Job[]
     */
    private array $jobs = [];

    /**
     * @var Job[]
     */
    private array $failed_jobs = [];

    private bool $needs_sort = false;

    public function enqueue(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
    {
        $this->jobs[] = $job;

        if (!$this->needs_sort) {
            $this->needs_sort = true;
        }

        return $this->count() - 1;
    }

    public function dequeue(int $job_id): void
    {
    }

    public function dequeueByType(string $type, array $properties = null): void
    {
    }

    public function execute(JobInterface $job, bool $silent = true)
    {
        return $job->execute();
    }

    public function countByChannel(string $channel): int
    {
        return count($this->jobs);
    }

    public function exists(string $job_type, array $properties = null): bool
    {
        return false;
    }

    public function changePriority(
        string $job_type,
        int $new_priority,
        array $properties = null
    ): void
    {
    }

    public function getJobById(int $job_id): ?JobInterface
    {
        return null;
    }

    public function nextInLine(string ...$from_channels): ?JobInterface
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
     * Return a batch of jobs that are next in line to be executed.
     *
     * @return JobInterface[]
     */
    public function nextBatchInLine(int $jobs_to_run, string ...$from_channels): array
    {
        if (!empty($this->jobs) && $this->needs_sort) {
            $this->sortByPriority($this->jobs);
        }

        return array_slice($this->jobs, 0, $jobs_to_run);
    }

    private function sortByPriority(array &$data)
    {
    }

    public function restoreFailedJobById($job_id, array $update_data = null)
    {
    }

    public function restoreFailedJobsByType($job_type, array $update_data = null)
    {
    }

    public function count(): int
    {
        return count($this->jobs);
    }

    public function countByType($type1)
    {
        $count = 0;

        $types = func_get_args();

        foreach ($this->jobs as $job) {
            if (in_array(get_class($job), $types)) {
                ++$count;
            }
        }

        return $count;
    }

    public function countFailed()
    {
        return count($this->failed_jobs);
    }

    public function countFailedByType($type1)
    {
        $count = 0;

        $types = func_get_args();

        foreach ($this->failed_jobs as $job) {
            if (in_array(get_class($job), $types)) {
                ++$count;
            }
        }

        return $count;
    }

    public function reportBackgroundProcess(JobInterface $job, $process_id)
    {
    }

    public function getBackgroundProcesses()
    {
    }

    public function checkStuckJobs()
    {
    }

    public function cleanUp(): void
    {
    }

    /**
     * @var callable[]
     */
    private array $on_job_failure = [];

    public function onJobFailure(callable $callback = null)
    {
        $this->on_job_failure[] = $callback;
    }

    public function createTables(string ...$additional_tables): void
    {
    }

    public function clear()
    {
    }

    public function getFailedJobReasons($job_type)
    {
        return [];
    }

    public function unfurlType(string $search_for): ?array
    {
        return null;
    }

    public function failedJobStatistics()
    {
        return [];
    }

    public function failedJobStatisticsByType($event_type)
    {
        return [];
    }

    public function countJobsByType(): array
    {
        return [];
    }

    public function createBatch(JobsDispatcherInterface $dispatcher, string $name): BatchInterface
    {
        throw new LogicException('Method not implemented in test queue');
    }

    public function countBatches(): int
    {
        return 0;
    }
}
