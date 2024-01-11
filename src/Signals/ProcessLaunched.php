<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Signals;

class ProcessLaunched implements SignalInterface
{
    /**
     * @var int
     */
    private $process_id;

    /**
     * @param int $process_id
     */
    public function __construct($process_id)
    {
        $this->process_id = $process_id;
    }

    /**
     * @return int
     */
    public function getProcessId()
    {
        return $this->process_id;
    }

    /**
     * Return true if you would like to signal queue to keep the job instead of removing it.
     *
     * @return bool
     */
    public function keepJobInQueue()
    {
        return true;
    }
}
