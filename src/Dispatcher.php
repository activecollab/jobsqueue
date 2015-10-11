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
        if (empty($channel)) {
            throw new InvalidArgumentException("Value '$channel' is not a valid channel name");
        }

        return $this->getQueue()->enqueue($job, $channel);
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
        if (empty($channel)) {
            throw new InvalidArgumentException("Value '$channel' is not a valid channel name");
        }

        return $this->getQueue()->execute($job, $channel);
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
