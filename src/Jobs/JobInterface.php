<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Jobs;

use ActiveCollab\JobsQueue\Batches\BatchInterface;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use JsonSerializable;
use Psr\Log\LoggerInterface;

/**
 * @package ActiveCollab\JobsQueue\Jobs
 */
interface JobInterface extends JsonSerializable
{
    const NOT_A_PRIORITY = 0;
    const HAS_PRIORITY = 256;
    const HAS_HIGHEST_PRIORITY = 4294967295; // UNSIGNED INT https://dev.mysql.com/doc/refman/5.0/en/integer-types.html

    public function execute(): mixed;
    public function getChannel(): ?string;

    /**
     * Set job channel when it is known.
     */
    public function setChannel(string $channel): static;

    /**
     * Return all job data (when $property is NULL) or a particular property.
     */
    public function getData(string $property = null): mixed;

    /**
     * Return job priority.
     */
    public function getPriority(): int;

    /**
     * Return max number of attempts for this job.
     */
    public function getAttempts(): int;

    /**
     * Return delay between first and every consecutive job execution (after failure).
     */
    public function getDelay(): int;

    /**
     * Return first job delay.
     */
    public function getFirstJobDelay(): int;
    public function getQueue(): ?QueueInterface;

    /**
     * Return queue ID that this job is enqueued under.
     */
    public function getQueueId(): ?int;

    /**
     * @return int|null
     */
    public function getBatchId(): ?int;
    public function setBatch(BatchInterface $batch): static;
    public function setQueue(QueueInterface $queue, int $queue_id): static;
    public function getLog(): ?LoggerInterface;
    public function setLog(LoggerInterface $log): static;
}
