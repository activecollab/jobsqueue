<?php

namespace ActiveCollab\JobsQueue\Batches;

use ActiveCollab\DatabaseConnection\ConnectionInterface;
use ActiveCollab\JobsQueue\DispatcherInterface;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use RuntimeException;

/**
 * @package ActiveCollab\JobsQueue\Queue
 */
class MySqlBatch extends Batch
{
    /**
     * @var ConnectionInterface
     */
    private $connection;

    /**
     * @param DispatcherInterface $dispatcher
     * @param ConnectionInterface $connection
     * @param integer             $queue_id
     * @param string              $name
     */
    public function __construct(DispatcherInterface &$dispatcher, ConnectionInterface &$connection, $queue_id = null, $name = null)
    {
        parent::__construct($dispatcher, $queue_id, $name);

        $this->connection = $connection;
    }

    /**
     * @var array
     */
    private $dispatched_job_ids = [];

    /**
     * Add a job to the queue
     *
     * @param  JobInterface $job
     * @param  string       $channel
     * @return mixed
     */
    public function dispatch(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
    {
        $dispatched_job_id = $this->dispatcher->dispatch($job->setBatch($this), $channel);

        $this->dispatched_job_ids[] = $dispatched_job_id;

        return $dispatched_job_id;
    }

    /**
     * {@inheritdoc}
     */
    public function commitDispatchedJobIds()
    {
        if ($queue_id = $this->getQueueId()) {
            if (!empty($this->dispatched_job_ids)) {
                $this->connection->transact(function() use ($queue_id) {
                    $this->connection->execute('UPDATE `' . MySqlQueue::BATCHES_TABLE_NAME . '` SET `jobs_count` = `jobs_count` + ? WHERE `id` = ?', count($this->dispatched_job_ids), $queue_id);
                });
            }
        } else {
            throw new RuntimeException("Can't commit dispatched job ID-s in an unsaved batch");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isComplete()
    {
        return empty($this->countPendingJobs());
    }

    /**
     * {@inheritdoc}
     */
    public function countJobs()
    {
        if ($queue_id = $this->getQueueId()) {
            return (integer) $this->connection->executeFirstCell('SELECT `jobs_count` FROM `' . MySqlQueue::BATCHES_TABLE_NAME . '` WHERE `id` = ?', $queue_id);
        } else {
            throw new RuntimeException("Can't get number of jobs from an unsaved batch");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function countPendingJobs()
    {
        if ($queue_id = $this->getQueueId()) {
            return (integer) $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `batch_id` = ?', $queue_id);
        } else {
            throw new RuntimeException("Can't get number of jobs from an unsaved batch");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function countCompletedJobs()
    {
        return $this->countJobs() - $this->countPendingJobs() - $this->countFailedJobs();
    }

    /**
     * {@inheritdoc}
     */
    public function countFailedJobs()
    {
        if ($queue_id = $this->getQueueId()) {
            return (integer) $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '` WHERE `batch_id` = ?', $queue_id);
        } else {
            throw new RuntimeException("Can't get number of jobs from an unsaved batch");
        }
    }
}
