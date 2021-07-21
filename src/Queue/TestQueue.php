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

    public function dequeue($job_id)
    {
    }

    public function dequeueByType($type)
    {
    }

    public function execute(JobInterface $job, $silent = true)
    {
        return $job->execute();
    }

    public function countByChannel($channel)
    {
        return count($this->jobs);
    }

    public function exists($job_type, array $properties = null)
    {
    }

    public function getJobById($job_id)
    {
    }

    public function nextInLine(...$from_channels)
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
     * @param  int            $jobs_to_run
     * @param  string[]       ...$from_channels
     * @return JobInterface[]
     */
    public function nextBatchInLine($jobs_to_run, ...$from_channels)
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

    public function count()
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

    public function cleanUp()
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

    public function createTables(...$additional_tables)
    {
    }

    public function clear()
    {
    }

    public function getFailedJobReasons($job_type)
    {
        return [];
    }

    public function unfurlType($search_for)
    {
    }

    public function failedJobStatistics()
    {
        return [];
    }

    public function failedJobStatisticsByType($event_type)
    {
        return [];
    }

    public function countJobsByType()
    {
        return [];
    }

    public function createBatch(JobsDispatcherInterface &$dispatcher, $name)
    {
        throw new LogicException('Method not implemented in test queue');
    }

    public function countBatches()
    {
        return 0;
    }
}
