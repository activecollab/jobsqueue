<?php

namespace ActiveCollab\JobsQueue\Jobs;

use ActiveCollab\JobsQueue\Queue\Queue;
use JsonSerializable;
use InvalidArgumentException;

/**
 * @package ActiveCollab\JobsQueue\Jobs
 */
abstract class Job implements JsonSerializable
{
    const NOT_A_PRIORITY = 0;
    const HAS_PRIORITY = 256;
    const HAS_HIGHEST_PRIORITY = 4294967295; // UNSIGNED INT https://dev.mysql.com/doc/refman/5.0/en/integer-types.html

    /**
     * @var array
     */
    private $data;

    /**
     * Construct a new Job instance
     *
     * @param  array|null $data
     * @throws InvalidArgumentException
     */
    public function __construct(array $data = null)
    {
        if (empty($data)) {
            $this->data = [];
        } else {
            if (is_array($data)) {
                $this->data = $data;
            } else {
                throw new InvalidArgumentException('Data is expected to be an array or NULL');
            }
        }

        if (empty($this->data['priority']) || $this->data['priority'] < self::NOT_A_PRIORITY) {
            $this->data['priority'] = self::NOT_A_PRIORITY;
        } else {
            if ($this->data['priority'] > self::HAS_HIGHEST_PRIORITY) {
                $this->data['priority'] = self::HAS_HIGHEST_PRIORITY;
            }
        }

        if (isset($this->data['attempts']) && (!is_int($this->data['attempts']) || $this->data['attempts'] < 1 || $this->data['attempts'] > 256)) {
            throw new InvalidArgumentException('Attempts need to be an integer value between 1 and 256');
        }

        if (isset($this->data['delay']) && (!is_int($this->data['delay']) || $this->data['delay'] < 1 || $this->data['delay'] > 3600)) {
            throw new InvalidArgumentException('Delay need to be an integer value between 1 and 3600 seconds');
        }

        if (isset($this->data['first_attempt_delay']) && (!is_int($this->data['first_attempt_delay']) || $this->data['first_attempt_delay'] < 0 || $this->data['first_attempt_delay'] > 3600)) {
            throw new InvalidArgumentException('First job delay need to be an integer value between 0 and 3600 seconds');
        }
    }

    /**
     * @return mixed
     */
    abstract public function execute();

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Return job priority
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->data['priority'];
    }

    /**
     * Return max number of attempts for this job
     *
     * @return int
     */
    public function getAttempts()
    {
        return isset($this->data['attempts']) ? $this->data['attempts'] : 1;
    }

    /**
     * Return delay between first and every consecutive job execution (after failure)
     *
     * @return int
     */
    public function getDelay()
    {
        return isset($this->data['delay']) ? $this->data['delay'] : 0;
    }

    /**
     * Return first job delay
     *
     * @return integer|null
     */
    public function getFirstJobDelay()
    {
        return array_key_exists('first_attempt_delay', $this->data) ? $this->data['first_attempt_delay'] : $this->getDelay();
    }

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @return Queue
     */
    public function &getQueue()
    {
        return $this->queue;
    }

    /**
     * @var mixed
     */
    private $queue_id;

    /**
     * Return queueu ID that this job is encured under
     *
     * @return mixed
     */
    public function getQueueId()
    {
        return $this->queue_id;
    }

    /**
     * Set job queue ID
     *
     * @param Queue $queue
     * @param mixed $queue_id
     */
    public function setQueue(Queue &$queue, $queue_id)
    {
        $this->queue = $queue;

        if ($queue_id === null || is_scalar($queue_id)) {
            $this->queue_id = $queue_id;
        } else {
            throw new InvalidArgumentException('Queue ID is expected to be sacalar or empty value');
        }
    }
}
