<?php

namespace ActiveCollab\JobsQueue;

use ActiveCollab\JobsQueue\Queue\QueueInterface;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use InvalidArgumentException;

/**
 * @package ActiveCollab\JobsQueue
 */
class Dispatcher implements DispatcherInterface
{
    /**
     * @var QueueInterface
     */
    private $queue;

    /**
     * @param QueueInterface $queue
     */
    public function __construct($queue)
    {
        if ($queue instanceof QueueInterface) {
            $this->queue = $queue;
        } else {
            throw new InvalidArgumentException('Queue is expected to be a Queue isntance or array of Queue instances');
        }
    }

    /**
     * Add a job to the queue
     *
     * @param  JobInterface $job
     * @param  string       $channel
     * @return mixed
     */
    public function dispatch(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
    {
        return $this->getQueue()->enqueue($job, $this->validateChannel($channel));
    }

    /**
     * Execute a job now (sync, waits for a response)
     *
     * @param  JobInterface $job
     * @param  string       $channel
     * @return mixed
     */
    public function execute(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
    {
        return $this->getQueue()->execute($job, $this->validateChannel($channel));
    }

    /**
     * @var string[]
     */
    private $registered_channels = [QueueInterface::MAIN_CHANNEL];

    /**
     * Register multiple channels
     *
     * @param  string ...$channels
     * @return $this
     */
    public function &registerChannels()
    {
        foreach (func_get_args() as $channel) {
            $this->registerChannel($channel);
        }

        return $this;
    }

    /**
     * Register a single change
     *
     * @param  string $channel
     * @return $this
     */
    public function &registerChannel($channel)
    {
        if (in_array($channel, $this->registered_channels)) {
            throw new InvalidArgumentException("Channel '$channel' already registered");
        }

        $this->registered_channels[] = $channel;

        return $this;
    }

    /**
     * Return an array of registered channels
     *
     * @return array
     */
    public function getRegisteredChannels()
    {
        return $this->registered_channels;
    }

    /**
     * Return true if $channel is registered
     *
     * @param  string  $channel
     * @return boolean
     */
    public function isChannelRegistered($channel)
    {
        return in_array($channel, $this->registered_channels);
    }

    /**
     * Make sure that we got a valid channel name
     *
     * @param  string $channel
     * @return string
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
                throw new InvalidArgumentException("Channel '$channel' is not registered");
            }
        } else {
            throw new InvalidArgumentException("Channel name needs to be a string value");
        }
    }

    /**
     * Return true if job of the given type and with the given properties exists in queue
     *
     * @param  string     $job_type
     * @param  array|null $properties
     * @return bool
     */
    public function exists($job_type, array $properties = null)
    {
        return $this->getQueue()->exists($job_type, $properties);
    }

    /**
     * Return queue instance
     *
     * @return QueueInterface
     */
    public function &getQueue()
    {
        return $this->queue;
    }
}
