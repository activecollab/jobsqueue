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

namespace ActiveCollab\JobsQueue;

use ActiveCollab\JobsQueue\Batches\BatchInterface;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\Queue\QueueInterface;

interface JobsDispatcherInterface extends DispatchJobInterface
{
    /**
     * Execute a job now (sync, waits for a response).
     *
     * @return mixed
     */
    public function execute(JobInterface $job, bool $silent = true);

    /**
     * Execute next job in line of execution.
     */
    public function executeNextInLine(string ...$from_channels);

    /**
     * Return true if job of the given type and with the given properties exists in queue.
     */
    public function exists(string $job_type, array $properties = null): bool;
    public function getQueue(): QueueInterface;
    public function registerChannels(string ...$channels): JobsDispatcherInterface;

    /**
     * Register a single channel.
     */
    public function registerChannel(string $channel): JobsDispatcherInterface;

    /**
     * Return an array of registered channels.
     *
     * @return string[]
     */
    public function getRegisteredChannels(): array;

    /**
     * Return true if $channel is registered.
     */
    public function isChannelRegistered(string $channel): bool;
    public function getExceptionOnUnregisteredChannel(): bool;

    /**
     * Set if exception should be thrown when producer tries to add a job to an unregistered channel.
     *
     * Default is TRUE. FALSE may be useful during testing and if there's only one channel used.
     */
    public function exceptionOnUnregisteredChannel(bool $value = true): static;

    /**
     * Create a job batch and optionally add jobs to it (via $add_jobs closure).
     */
    public function batch(string $name, callable $add_jobs = null): BatchInterface;
    
    public function countBatches(): int;

    /**
     * Search for a full job class name.
     */
    public function unfurlType(string $search_for): ?array;
}
