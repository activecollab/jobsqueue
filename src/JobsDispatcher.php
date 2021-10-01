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

use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use InvalidArgumentException;

class JobsDispatcher implements DispatcherInterface
{
    private QueueInterface $queue;

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    public function dispatch(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
    {
        return $this->getQueue()->enqueue($job, $this->validateChannel($channel));
    }

    public function execute(JobInterface $job, $silent = true)
    {
        return $this->getQueue()->execute($job, $silent);
    }

    public function executeNextInLine(...$from_channels)
    {
        if ($job = $this->getQueue()->nextInLine(...$from_channels)) {
            return $this->getQueue()->execute($job, $job->getChannel());
        }

        return null;
    }

    public function exists(string $job_type, array $properties = null): bool
    {
        return $this->getQueue()->exists($job_type, $properties);
    }

    public function &getQueue()
    {
        return $this->queue;
    }

    /**
     * @var string[]
     */
    private array $registered_channels = [
        QueueInterface::MAIN_CHANNEL
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

    public function getRegisteredChannels()
    {
        return $this->registered_channels;
    }

    public function isChannelRegistered($channel)
    {
        return in_array($channel, $this->registered_channels);
    }

    private bool $exception_on_unregistered_channel = true;

    public function getExceptionOnUnregisteredChannel()
    {
        return $this->exception_on_unregistered_channel;
    }

    public function &exceptionOnUnregisteredChannel($value = true)
    {
        $this->exception_on_unregistered_channel = (bool) $value;

        return $this;
    }

    private function validateChannel($channel)
    {
        if (is_string($channel)) {
            $channel = trim($channel);

            if (empty($channel)) {
                throw new InvalidArgumentException("Value '$channel' is not a valid channel name");
            }

            if (in_array($channel, $this->registered_channels)) {
                return $channel;
            } else {
                if ($this->exception_on_unregistered_channel) {
                    throw new InvalidArgumentException("Channel '$channel' is not registered");
                } else {
                    return QueueInterface::MAIN_CHANNEL;
                }
            }
        } else {
            throw new InvalidArgumentException('Channel name needs to be a string value');
        }
    }

    public function batch($name, callable $add_jobs = null)
    {
        $batch = $this->getQueue()->createBatch($this, $name);

        if ($add_jobs) {
            call_user_func_array($add_jobs, [&$batch]);
        }

        $batch->commitDispatchedJobIds();

        return $batch;
    }

    public function countBatches(): int
    {
        return $this->getQueue()->countBatches();
    }

    public function unfurlType($search_for)
    {
        return  $this->getQueue()->unfurlType($search_for);
    }
}
