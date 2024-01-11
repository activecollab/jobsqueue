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

namespace ActiveCollab\JobsQueue\Jobs;

use ActiveCollab\JobsQueue\Batches\BatchInterface;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use ActiveCollab\JobsQueue\Signals\ProcessLaunched;
use ActiveCollab\JobsQueue\Signals\SignalInterface;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class Job implements JobInterface
{
    private array $data = [];

    public function __construct(array $data = null)
    {
        if ($data) {
            $this->data = $data;
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

        if (isset($this->data['delay']) && (!is_int($this->data['delay']) || $this->data['delay'] < 1 || $this->data['delay'] > 7776000)) {
            throw new InvalidArgumentException('Delay need to be an integer value between 1 and 7776000 seconds');
        }

        if (isset($this->data['first_attempt_delay']) && (!is_int($this->data['first_attempt_delay']) || $this->data['first_attempt_delay'] < 0 || $this->data['first_attempt_delay'] > 7776000)) {
            throw new InvalidArgumentException('First job delay need to be an integer value between 0 and 7776000 seconds');
        }
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * @var string
     */
    private $channel;

    /**
     * Get job channel, if known.
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * Set job channel when it is known.
     */
    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getData($property = null)
    {
        if ($property === null) {
            return $this->data;
        }

        if (empty($property)) {
            throw new InvalidArgumentException("When provided, property can't be an empty value");
        }

        if (empty($this->data[$property]) && !array_key_exists($property, $this->data)) {
            throw new InvalidArgumentException(sprintf("Property '%s' not found", $property));
        }

        return $this->data[$property];
    }

    /**
     * Return job priority.
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->data['priority'];
    }

    /**
     * Return max number of attempts for this job.
     *
     * @return int
     */
    public function getAttempts()
    {
        return $this->data['attempts'] ?? 1;
    }

    /**
     * Return delay between first and every consecutive job execution (after failure).
     *
     * @return int
     */
    public function getDelay()
    {
        return $this->data['delay'] ?? 0;
    }

    /**
     * Return first job delay.
     *
     * @return int|null
     */
    public function getFirstJobDelay()
    {
        return array_key_exists('first_attempt_delay', $this->data) ? $this->data['first_attempt_delay'] : $this->getDelay();
    }

    /**
     * @var QueueInterface
     */
    private $queue;

    public function getQueue(): QueueInterface
    {
        return $this->queue;
    }

    /**
     * @var mixed
     */
    private $queue_id;

    /**
     * {@inheritdoc}
     */
    public function getQueueId()
    {
        return $this->queue_id;
    }

    /**
     * {@inheritdoc}
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
     * @var int
     */
    private $batch_id;

    /**
     * @return int|null
     */
    public function getBatchId()
    {
        return $this->batch_id;
    }

    /**
     * @param  int|null $batch_id
     * @return $this
     */
    public function &setBatchId($batch_id)
    {
        $this->batch_id = $batch_id === null ? null : (integer) $batch_id;

        return $this;
    }

    /**
     * @param  BatchInterface $batch
     * @return $this
     */
    public function &setBatch(BatchInterface $batch)
    {
        if (empty($this->getQueueId())) {
            if ($batch->getQueueId()) {
                return $this->setBatchId($batch->getQueueId());
            } else {
                throw new RuntimeException("Can't set an unqueued batch");
            }
        } else {
            throw new RuntimeException("Can't add already enqueued job to a batch");
        }
    }

    /**
     * Report that this job has launched a background process.
     *
     * @param  int                             $process_id
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

    /**
     * @var LoggerInterface|null
     */
    protected $log;

    /**
     * @return null|LoggerInterface
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * @param  LoggerInterface $log
     * @return $this
     */
    public function &setLog(LoggerInterface $log)
    {
        $this->log = $log;

        return $this;
    }
}
