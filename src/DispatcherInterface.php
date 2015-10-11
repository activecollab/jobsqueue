<?php

namespace ActiveCollab\JobsQueue;

use ActiveCollab\JobsQueue\Queue\QueueInterface;
use ActiveCollab\JobsQueue\Jobs\JobInterface;

/**
 * @package ActiveCollab\JobsQueue
 */
interface DispatcherInterface
{
    /**
     * Add a job to the queue
     *
     * @param  JobInterface $job
     * @param  string       $channel
     * @return mixed
     */
    public function dispatch(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL);

    /**
     * Execute a job now (sync, waits for a response)
     *
     * @param  JobInterface $job
     * @param  string       $channel
     * @return mixed
     */
    public function execute(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL);

    /**
     * Return true if job of the given type and with the given properties exists in queue
     *
     * @param  string     $job_type
     * @param  array|null $properties
     * @return bool
     */
    public function exists($job_type, array $properties = null);

    /**
     * Return queue instance
     *
     * @return QueueInterface
     */
    public function &getQueue();
}