<?php

namespace ActiveCollab\JobsQueue\Jobs;

use ActiveCollab\JobsQueue\Queue\QueueInterface;
use JsonSerializable;

/**
 * @package ActiveCollab\JobsQueue\Jobs
 */
interface JobInterface extends  JsonSerializable
{
    const NOT_A_PRIORITY = 0;
    const HAS_PRIORITY = 256;
    const HAS_HIGHEST_PRIORITY = 4294967295; // UNSIGNED INT https://dev.mysql.com/doc/refman/5.0/en/integer-types.html

    /**
     * @return mixed
     */
    public function execute();

    /**
     * @return array
     */
    public function getData();

    /**
     * Return job priority
     *
     * @return int
     */
    public function getPriority();

    /**
     * Return max number of attempts for this job
     *
     * @return int
     */
    public function getAttempts();

    /**
     * Return delay between first and every consecutive job execution (after failure)
     *
     * @return int
     */
    public function getDelay();

    /**
     * Return first job delay
     *
     * @return integer|null
     */
    public function getFirstJobDelay();

    /**
     * @return QueueInterface
     */
    public function &getQueue();

    /**
     * Return queueu ID that this job is encured under
     *
     * @return mixed
     */
    public function getQueueId();

    /**
     * Set job queue ID
     *
     * @param QueueInterface $queue
     * @param mixed $queue_id
     */
    public function setQueue(QueueInterface &$queue, $queue_id);
}
