<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue;

use ActiveCollab\JobsQueue\Batches\BatchInterface;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\Queue\QueueInterface;

/**
 * @package ActiveCollab\JobsQueue
 */
interface DispatcherInterface extends DispatchJobInterface
{
    /**
     * Execute a job now (sync, waits for a response).
     *
     * @param  JobInterface $job
     * @param  string       $channel
     * @return mixed
     */
    public function execute(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL);

    /**
     * Execute next job in line of execution.
     *
     * @param string[] ...$from_channels
     */
    public function executeNextInLine(...$from_channels);

    /**
     * Return true if job of the given type and with the given properties exists in queue.
     *
     * @param  string     $job_type
     * @param  array|null $properties
     * @return bool
     */
    public function exists($job_type, array $properties = null);

    /**
     * Return queue instance.
     *
     * @return QueueInterface
     */
    public function &getQueue();

    /**
     * Register multiple channels.
     *
     * @param  string[] ...$channels
     * @return $this
     */
    public function &registerChannels(...$channels);

    /**
     * Register a single change.
     *
     * @param  string $channel
     * @return $this
     */
    public function &registerChannel($channel);

    /**
     * Return an array of registered channels.
     *
     * @return array
     */
    public function getRegisteredChannels();

    /**
     * Return true if $channel is registered.
     *
     * @param  string $channel
     * @return bool
     */
    public function isChannelRegistered($channel);

    /**
     * @return bool
     */
    public function getExceptionOnUnregisteredChannel();

    /**
     * Set if exception should be thrown when producer tries to add a job to an unregistered channel.
     *
     * Default is TRUE. FALSE may be useful during testing and if there's only one channel used
     *
     * @param  bool  $value
     * @return $this
     */
    public function &exceptionOnUnregisteredChannel($value = true);

    /**
     * Create a job batch and optionally add jobs to it (via $add_jobs closure).
     *
     * @param  string         $name
     * @param  callable|null  $add_jobs
     * @return BatchInterface
     */
    public function batch($name, callable $add_jobs = null);

    /**
     * @return int
     */
    public function countBatches();

    /**
     * Search for a full job class name.
     *
     * @param  string $search_for
     * @return string
     */
    public function unfurlType($search_for);
}
