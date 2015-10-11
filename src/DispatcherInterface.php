<?php

namespace ActiveCollab\JobsQueue;

use ActiveCollab\JobsQueue\Queue\QueueInterface;
use ActiveCollab\JobsQueue\Jobs\JobInterface;

/**
 * @package ActiveCollab\JobsQueue
 */
interface DispatcherInterface
{
    const DEFAULT_QUEUE = 'jobs';

    /**
     * @param string         $queue_name
     * @param QueueInterface $queue
     */
    public function addQueue($queue_name, QueueInterface $queue);

    /**
     * Add a job to the queue
     *
     * @param  JobInterface $job
     * @param  string       $queue_name
     * @return mixed
     */
    public function dispatch(JobInterface $job, $queue_name = self::DEFAULT_QUEUE);

    /**
     * Execute a job now (sync, waits for a response)
     *
     * @param  JobInterface $job
     * @param  string       $queue_name
     * @return mixed
     */
    public function execute(JobInterface $job, $queue_name = self::DEFAULT_QUEUE);

    /**
     * Return true if job of the given type and with the given properties exists in queue
     *
     * @param  string     $job_type
     * @param  array|null $properties
     * @param  string     $queue_name
     * @return bool
     */
    public function exists($job_type, array $properties = null, $queue_name = self::DEFAULT_QUEUE);

    /**
     * @param  string         $queue_name
     * @return QueueInterface
     */
    public function &getQueue($queue_name = self::DEFAULT_QUEUE);
}