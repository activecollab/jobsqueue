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

namespace ActiveCollab\JobsQueue\Signals;

class ProcessLaunched implements SignalInterface
{
    public function __construct(
        private int $process_id,
    )
    {
    }

    public function getProcessId(): int
    {
        return $this->process_id;
    }

    /**
     * Return true if you would like to signal queue to keep the job instead of removing it.
     */
    public function keepJobInQueue(): bool
    {
        return true;
    }
}
