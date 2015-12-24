<?php

namespace ActiveCollab\JobsQueue\Batches;

use ActiveCollab\JobsQueue\DispatchJobInterface;

/**
 * @package ActiveCollab\JobsQueue
 */
interface BatchInterface extends DispatchJobInterface
{
    /**
     * Commit job ID-s dispatched by this batch
     */
    public function commitDispatchedJobIds();

    /**
     * Return queueu ID that this batch is created under
     *
     * @return mixed
     */
    public function getQueueId();

    /**
     * Return batch name (or description)
     *
     * @return string
     */
    public function getName();

    /**
     * Return true if there are no pending jobs in this queue (all jobs are done)
     *
     * @return boolean
     */
    public function isComplete();

    /**
     * Return total number of jobs that are in the batch
     *
     * @return integer
     */
    public function countJobs();

    /**
     * @return integer
     */
    public function countPendingJobs();

    /**
     * Return total number of jobs
     *
     * @return integer
     */
    public function countCompletedJobs();

    /**
     * Return number of failed jobs
     *
     * @return integer
     */
    public function countFailedJobs();
}
