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

namespace ActiveCollab\JobsQueue\Batches;

use ActiveCollab\DatabaseConnection\ConnectionInterface;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\JobsDispatcherInterface;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use RuntimeException;

class MySqlBatch extends Batch
{
    public function __construct(
        JobsDispatcherInterface $dispatcher,
        private ConnectionInterface $connection,
        int $queue_id = null,
        string $name = null
    )
    {
        parent::__construct($dispatcher, $queue_id, $name);
    }

    private array $dispatched_job_ids = [];

    /**
     * Add a job to the queue.
     */
    public function dispatch(
        JobInterface $job,
        string $channel = QueueInterface::MAIN_CHANNEL,
    ): int
    {
        $dispatched_job_id = $this->dispatcher->dispatch($job->setBatch($this), $channel);

        $this->dispatched_job_ids[] = $dispatched_job_id;

        return $dispatched_job_id;
    }

    public function commitDispatchedJobIds(): void
    {
        $queue_id = $this->getQueueId();

        if (empty($queue_id)) {
            throw new RuntimeException("Can't commit dispatched job ID-s in an unsaved batch");
        }

        if (!empty($this->dispatched_job_ids)) {
            $this->connection->transact(
                function () use ($queue_id) {
                    $this->connection->execute(
                        'UPDATE `' . MySqlQueue::BATCHES_TABLE_NAME . '` SET `jobs_count` = `jobs_count` + ? WHERE `id` = ?',
                        count($this->dispatched_job_ids),
                        $queue_id,
                    );
                },
            );
        }
    }

    public function isComplete(): bool
    {
        return empty($this->countPendingJobs());
    }

    public function countJobs(): int
    {
        if ($queue_id = $this->getQueueId()) {
            return (int) $this->connection->executeFirstCell(
                'SELECT `jobs_count` FROM `' . MySqlQueue::BATCHES_TABLE_NAME . '` WHERE `id` = ?',
                $queue_id,
            );
        }

        throw new RuntimeException("Can't get number of jobs from an unsaved batch");
    }

    public function countPendingJobs(): int
    {
        if ($queue_id = $this->getQueueId()) {
            return (integer) $this->connection->executeFirstCell(
                'SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `batch_id` = ?',
                $queue_id,
            );
        }

        throw new RuntimeException("Can't get number of jobs from an unsaved batch");
    }

    public function countCompletedJobs(): int
    {
        return $this->countJobs() - $this->countPendingJobs() - $this->countFailedJobs();
    }

    public function countFailedJobs(): int
    {
        if ($queue_id = $this->getQueueId()) {
            return (integer) $this->connection->executeFirstCell(
                'SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '` WHERE `batch_id` = ?',
                $queue_id,
            );
        }

        throw new RuntimeException("Can't get number of jobs from an unsaved batch");
    }
}
