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

    /**
     * Register multiple channels
     *
     * @param  string[] ...$channels
     * @return $this
     */
    public function &registerChannels(...$channels);

    /**
     * Register a single change
     *
     * @param  string $channel
     * @return $this
     */
    public function &registerChannel($channel);

    /**
     * Return an array of registered channels
     *
     * @return array
     */
    public function getRegisteredChannels();

    /**
     * Return true if $channel is registered
     *
     * @param  string  $channel
     * @return boolean
     */
    public function isChannelRegistered($channel);

    /**
     * @return boolean
     */
    public function getExceptionOnUnregisteredChannel();

    /**
     * Set if exception should be thrown when producer tries to add a job to an unregistered channel
     *
     * Default is TRUE. FALSE may be useful during testing and if there's only one channel used
     *
     * @param  boolean $value
     * @return $this
     */
    public function &exceptionOnUnregisteredChannel($value = true);

    /**
     * Search for a full job class name
     *
     * @param  string $search_for
     * @return string
     */
    public function unfurlType($search_for);
}
