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

namespace ActiveCollab\JobsQueue\Batches;

use ActiveCollab\JobsQueue\DispatchJobInterface;

interface BatchInterface extends DispatchJobInterface
{
    /**
     * Commit job ID-s dispatched by this batch.
     */
    public function commitDispatchedJobIds(): void;

    /**
     * Return queue ID that this batch is created under.
     */
    public function getQueueId(): ?int;

    /**
     * Return batch name (or description).
     */
    public function getName(): ?string;

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
