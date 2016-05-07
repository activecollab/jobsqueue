<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Batches;

use ActiveCollab\JobsQueue\DispatchJobInterface;

/**
 * @package ActiveCollab\JobsQueue
 */
interface BatchInterface extends DispatchJobInterface
{
    /**
     * Commit job ID-s dispatched by this batch.
     */
    public function commitDispatchedJobIds();

    /**
     * Return queueu ID that this batch is created under.
     *
     * @return mixed
     */
    public function getQueueId();

    /**
     * Return batch name (or description).
     *
     * @return string
     */
    public function getName();

    /**
     * Return true if there are no pending jobs in this queue (all jobs are done).
     *
     * @return bool
     */
    public function isComplete();

    /**
     * Return total number of jobs that are in the batch.
     *
     * @return int
     */
    public function countJobs();

    /**
     * @return int
     */
    public function countPendingJobs();

    /**
     * Return total number of jobs.
     *
     * @return int
     */
    public function countCompletedJobs();

    /**
     * Return number of failed jobs.
     *
     * @return int
     */
    public function countFailedJobs();
}
