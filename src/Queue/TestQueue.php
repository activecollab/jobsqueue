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

use ActiveCollab\JobsQueue\DispatcherInterface;
use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\Jobs\JobInterface;

/**
 * @package ActiveCollab\JobsQueue\Queue
 */
class TestQueue extends Queue
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function dequeue($job_id)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function dequeueByType($type)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function execute(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
    {
        return $job->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function countByChannel($channel)
    {
        return count($this->jobs);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($job_type, array $properties = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getJobById($job_id)
    {
    }

    /**
     * {@inheritdoc}
     */
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
     * {@inheritdoc}
     */
    private function sortByPriority(array &$data)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function restoreFailedJobById($job_id, array $update_data = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function restoreFailedJobsByType($job_type, array $update_data = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->jobs);
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function countFailed()
    {
        return count($this->failed_jobs);
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function reportBackgroundProcess(JobInterface $job, $process_id)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getBackgroundProcesses()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function checkStuckJobs()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function cleanUp()
    {
    }

    /**
     * @var callable[]
     */
    private $on_job_failure = [];

    /**
     * {@inheritdoc}
     */
    public function onJobFailure(callable $callback = null)
    {
        $this->on_job_failure[] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function createTables(...$additional_tables)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedJobReasons($job_type)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function unfurlType($search_for)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function failedJobStatistics()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function failedJobStatisticsByType($event_type)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function countJobsByType()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function createBatch(DispatcherInterface &$dispatcher, $name)
    {
        throw new \LogicException('Method not implemented in test queue');
    }

    /**
     * {@inheritdoc}
     */
    public function countBatches()
    {
        return 0;
    }
}
