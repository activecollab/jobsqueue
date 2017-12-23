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

    /**
     * @return mixed
     */
    public function execute();

    /**
     * @return string
     */
    public function getChannel();

    /**
     * Set job channel when it is known.
     *
     * @param  string $channel
     * @return $this
     */
    public function &setChannel($channel);

    /**
     * Return all job data (when $property is NULL) or a particular property.
     *
     * @param  string|null $property
     * @return array|mixed
     */
    public function getData($property = null);

    /**
     * Return job priority.
     *
     * @return int
     */
    public function getPriority();

    /**
     * Return max number of attempts for this job.
     *
     * @return int
     */
    public function getAttempts();

    /**
     * Return delay between first and every consecutive job execution (after failure).
     *
     * @return int
     */
    public function getDelay();

    /**
     * Return first job delay.
     *
     * @return int|null
     */
    public function getFirstJobDelay();

    /**
     * @return QueueInterface
     */
    public function &getQueue();

    /**
     * Return queue ID that this job is enqueued under.
     *
     * @return mixed
     */
    public function getQueueId();

    /**
     * @return int|null
     */
    public function getBatchId();

    /**
     * @param  BatchInterface $batch
     * @return $this
     */
    public function &setBatch(BatchInterface $batch);

    /**
     * Set job queue.
     *
     * @param QueueInterface $queue
     * @param mixed          $queue_id
     */
    public function &setQueue(QueueInterface &$queue, $queue_id);

    /**
     * @return null|LoggerInterface
     */
    public function getLog();

    /**
     * @param  LoggerInterface $log
     * @return $this
     */
    public function &setLog(LoggerInterface $log);
}
