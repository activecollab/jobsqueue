<?php

namespace ActiveCollab\JobsQueue;

use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use InvalidArgumentException;

/**
 * @package ActiveCollab\JobsQueue
 */
class Dispatcher
{
    const DEFAULT_QUEUE = 'jobs';

    /**
     * @var QueueInterface[]
     */
    private $queues = [];

    /**
     * @param QueueInterface|QueueInterface[]|null $queue
     */
    public function __construct($queue = null)
    {
        if ($queue instanceof QueueInterface) {
            $this->queues[ self::DEFAULT_QUEUE ] = $queue;
        } else {
            if (is_array($queue)) {
                foreach ($queue as $k => $v) {
                    $this->addQueue($k, $v);
                }
            } else {
                if ($queue !== null) {
                    throw new \InvalidArgumentException('Queue is expected to be a Queue isntance or array of Queue instances');
                }
            }
        }
    }

    /**
     * @param       $queue_name
     * @param QueueInterface $queue
     */
    public function addQueue($queue_name, QueueInterface $queue)
    {
        if (isset($this->queues[ $queue_name ])) {
            throw new \InvalidArgumentException("Queue '$queue_name' already added");
        } else {
            $this->queues[ $queue_name ] = $queue;
        }
    }

    /**
     * Add a job to the queue
     *
     * @param  Job    $job
     * @param  string $queue_name
     * @return mixed
     */
    public function dispatch(Job $job, $queue_name = self::DEFAULT_QUEUE)
    {
        return $this->getQueue($queue_name)->enqueue($job);
    }

    /**
     * Execute a job now (sync, waits for a response)
     *
     * @param  Job    $job
     * @param  string $queue_name
     * @return mixed
     */
    public function execute(Job $job, $queue_name = self::DEFAULT_QUEUE)
    {
        return $this->getQueue($queue_name)->execute($job);
    }

    /**
     * Return true if job of the given type and with the given properties exists in queue
     *
     * @param  string     $job_type
     * @param  array|null $properties
     * @param  string     $queue_name
     * @return bool
     */
    public function exists($job_type, array $properties = null, $queue_name = self::DEFAULT_QUEUE)
    {
        return $this->getQueue($queue_name)->exists($job_type, $properties);
    }

    /**
     * @param  string $queue_name
     * @return QueueInterface
     */
    public function &getQueue($queue_name = self::DEFAULT_QUEUE)
    {
        if (isset($this->queues[ $queue_name ])) {
            return $this->queues[ $queue_name ];
        } else {
            throw new InvalidArgumentException("Queue $queue_name' is not specified");
        }
    }
}
