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
    public function commitDispatchedJobIds(): void;

    /**
     * Return queue ID that this batch is created under.
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
     */
    public function isComplete(): bool;

    /**
     * Return total number of jobs that are in the batch.
     */
    public function countJobs(): int;
    public function countPendingJobs(): int;
    public function countCompletedJobs(): int;
    public function countFailedJobs(): int;
}
