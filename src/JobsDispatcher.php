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
    /**
     * @var QueueInterface
     */
    private $queue;

    /**
     * @param QueueInterface $queue
     */
    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
    {
        return $this->getQueue()->enqueue($job, $this->validateChannel($channel));
    }

    /**
     * {@inheritdoc}
     */
    public function execute(JobInterface $job, $silent = true)
    {
        return $this->getQueue()->execute($job, $silent);
    }

    /**
     * {@inheritdoc}
     */
    public function executeNextInLine(...$from_channels)
    {
        if ($job = $this->getQueue()->nextInLine(...$from_channels)) {
            return $this->getQueue()->execute($job, $job->getChannel());
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($job_type, array $properties = null)
    {
        return $this->getQueue()->exists($job_type, $properties);
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function getRegisteredChannels()
    {
        return $this->registered_channels;
    }

    /**
     * {@inheritdoc}
     */
    public function isChannelRegistered($channel)
    {
        return in_array($channel, $this->registered_channels);
    }

    /**
     * @var bool
     */
    private $exception_on_unregistered_channel = true;

    /**
     * {@inheritdoc}
     */
    public function getExceptionOnUnregisteredChannel()
    {
        return $this->exception_on_unregistered_channel;
    }

    /**
     * {@inheritdoc}
     */
    public function &exceptionOnUnregisteredChannel($value = true)
    {
        $this->exception_on_unregistered_channel = (boolean) $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function batch($name, callable $add_jobs = null)
    {
        $batch = $this->getQueue()->createBatch($this, $name);

        if ($add_jobs) {
            call_user_func_array($add_jobs, [&$batch]);
        }

        $batch->commitDispatchedJobIds();

        return $batch;
    }

    /**
     * @return int
     */
    public function countBatches()
    {
        return $this->getQueue()->countBatches();
    }

    /**
     * {@inheritdoc}
     */
    public function unfurlType($search_for)
    {
        return  $this->getQueue()->unfurlType($search_for);
    }
}
