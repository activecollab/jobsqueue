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
use InvalidArgumentException;

class JobsDispatcher implements DispatcherInterface
{
    public function __construct(
        private QueueInterface $queue,
    )
    {
    }

    public function dispatch(
        JobInterface $job,
        string $channel = QueueInterface::MAIN_CHANNEL,
    ): int
    {
        return $this->getQueue()->enqueue($job, $this->validateChannel($channel));
    }

    public function execute(JobInterface $job, bool $silent = true): mixed
    {
        return $this->getQueue()->execute($job, $silent);
    }

    public function executeNextInLine(string ...$from_channels)
    {
        $job = $this->getQueue()->nextInLine(...$from_channels);

        if (empty($job)) {
            return null;
        }

        return $this->getQueue()->execute($job);
    }

    public function exists(string $job_type, array $properties = null): bool
    {
        return $this->getQueue()->exists($job_type, $properties);
    }

    public function getQueue(): QueueInterface
    {
        return $this->queue;
    }

    private array $registered_channels = [
        QueueInterface::MAIN_CHANNEL,
    ];

    public function registerChannels(string ...$channels): JobsDispatcherInterface
    {
        foreach ($channels as $channel) {
            $this->registerChannel($channel);
        }

        return $this;
    }

    public function registerChannel(string $channel): JobsDispatcherInterface
    {
        if (in_array($channel, $this->registered_channels)) {
            throw new InvalidArgumentException("Channel '$channel' already registered");
        }

        $this->registered_channels[] = $channel;

        return $this;
    }

    public function getRegisteredChannels(): array
    {
        return $this->registered_channels;
    }

    public function isChannelRegistered(string $channel): bool
    {
        return in_array($channel, $this->registered_channels);
    }

    private bool $exception_on_unregistered_channel = true;

    public function getExceptionOnUnregisteredChannel(): bool
    {
        return $this->exception_on_unregistered_channel;
    }

    public function exceptionOnUnregisteredChannel(bool $value = true): static
    {
        $this->exception_on_unregistered_channel = $value;

        return $this;
    }

    private function validateChannel(string $channel): string
    {
        $channel = trim($channel);

        if (empty($channel)) {
            throw new InvalidArgumentException(
                sprintf("Value '%s' is not a valid channel name.", $channel),
            );
        }

        if (in_array($channel, $this->registered_channels)) {
            return $channel;
        }

        if ($this->exception_on_unregistered_channel) {
            throw new InvalidArgumentException(
                sprintf("Channel '%s' is not registered.", $channel),
            );
        }

        return QueueInterface::MAIN_CHANNEL;
    }

    public function batch(string $name, callable $add_jobs = null): BatchInterface
    {
        $batch = $this->getQueue()->createBatch($this, $name);

        if ($add_jobs) {
            call_user_func_array($add_jobs, [$batch]);
        }

        $batch->commitDispatchedJobIds();

        return $batch;
    }

    public function countBatches(): int
    {
        return $this->getQueue()->countBatches();
    }

    public function unfurlType(string $search_for): ?array
    {
        return $this->getQueue()->unfurlType($search_for);
    }
}
