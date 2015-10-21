<?php

namespace ActiveCollab\JobsQueue\Jobs;

use ActiveCollab\JobsQueue\Queue\QueueInterface;
use ActiveCollab\JobsQueue\Signals\SignalInterface;
use ActiveCollab\JobsQueue\Signals\ProcessLaunched;
use InvalidArgumentException;
use LogicException;

/**
 * @package ActiveCollab\JobsQueue\Jobs
 */
abstract class Job implements JobInterface
{
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
     * @var string
     */
    private $channel;

    /**
     * Get job channel, if known
     *
     * @return string
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * Set job channel when it is known
     *
     * @param  string $channel
     * @return $this
     */
    public function &setChannel($channel)
    {
        $this->channel = $channel;

        return $this;
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
     * @var QueueInterface
     */
    private $queue;

    /**
     * @return QueueInterface
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
     * Set job queue
     *
     * @param  QueueInterface $queue
     * @param  mixed          $queue_id
     * @return $this
     */
    public function &setQueue(QueueInterface &$queue, $queue_id)
    {
        $this->queue = $queue;

        if ($queue_id === null || is_scalar($queue_id)) {
            $this->queue_id = $queue_id;
        } else {
            throw new InvalidArgumentException('Queue ID is expected to be sacalar or empty value');
        }

        return $this;
    }

    /**
     * Report that this job has launched a background process
     *
     * @param  integer                         $process_id
     * @return SignalInterface|ProcessLaunched
     */
    protected function reportBackgroundProcess($process_id)
    {
        if (empty($this->queue) || empty($this->queue_id)) {
            throw new LogicException('Background process can be reported only for enqueued jobs');
        }

        if (!is_int($process_id) || $process_id < 1) {
            throw new InvalidArgumentException('Process ID is required');
        }

        $this->queue->reportBackgroundProcess($this, $process_id);

        return new ProcessLaunched($process_id);
    }
}
