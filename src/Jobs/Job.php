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

    private ?string $channel = null;

    public function getChannel(): ?string
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

    public function getData(string $property = null): mixed
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
     */
    public function getPriority(): int
    {
        return $this->data['priority'];
    }

    /**
     * Return max number of attempts for this job.
     */
    public function getAttempts(): int
    {
        return $this->data['attempts'] ?? 1;
    }

    /**
     * Return delay between first and every consecutive job execution (after failure).
     */
    public function getDelay(): int
    {
        return $this->data['delay'] ?? 0;
    }

    /**
     * Return first job delay.
     */
    public function getFirstJobDelay(): int
    {
        return array_key_exists('first_attempt_delay', $this->data)
            ? $this->data['first_attempt_delay']
            : $this->getDelay();
    }

    private ?QueueInterface $queue = null;

    public function getQueue(): ?QueueInterface
    {
        return $this->queue;
    }

    private ?int $queue_id = null;

    public function getQueueId(): ?int
    {
        return $this->queue_id;
    }

    public function setQueue(QueueInterface $queue, int $queue_id): static
    {
        $this->queue = $queue;
        $this->queue_id = $queue_id;

        return $this;
    }

    private ?int $batch_id = null;

    public function getBatchId(): ?int
    {
        return $this->batch_id;
    }

    public function setBatchId(?int $batch_id): static
    {
        $this->batch_id = $batch_id;

        return $this;
    }

    public function setBatch(BatchInterface $batch): static
    {
        if ($this->getQueueId()) {
            throw new RuntimeException("Can't add already enqueued job to a batch.");
        }

        if (!$batch->getQueueId()) {
            throw new RuntimeException("Can't set a non-queued batch.");
        }

        return $this->setBatchId($batch->getQueueId());
    }

    /**
     * Report that this job has launched a background process.
     */
    protected function reportBackgroundProcess(int $process_id): SignalInterface
    {
        if (empty($this->queue) || empty($this->queue_id)) {
            throw new LogicException('Background process can be reported only for enqueued jobs');
        }

        if ($process_id < 1) {
            throw new InvalidArgumentException('Process ID is required');
        }

        $this->queue->reportBackgroundProcess($this, $process_id);

        return new ProcessLaunched($process_id);
    }

    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $log = null;

    public function getLog(): ?LoggerInterface
    {
        return $this->log;
    }

    public function setLog(LoggerInterface $log): static
    {
        $this->log = $log;

        return $this;
    }
}
