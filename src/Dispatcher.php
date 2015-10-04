<?php

namespace ActiveCollab\JobsQueue;

use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\Queue\Queue;
use InvalidArgumentException;

/**
 * @package ActiveCollab\JobsQueue
 */
class Dispatcher
{
    const DEFAULT_QUEUE = 'jobs';

    /**
     * @var Queue[]
     */
    private $queues = [];

    /**
     * @param Queue|Queue[]|null $queue
     */
    public function __construct($queue = null)
    {
        if ($queue instanceof Queue) {
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
     * @param Queue $queue
     */
    public function addQueue($queue_name, Queue $queue)
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
     * @param  string $queue_name
     * @return Queue
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
