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

namespace ActiveCollab\JobsQueue;

use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\Queue\QueueInterface;

interface DispatchJobInterface
{
    /**
     * Add a job to the queue.
     */
    public function dispatch(
        JobInterface $job,
        string $channel = QueueInterface::MAIN_CHANNEL,
    ): int;
}
