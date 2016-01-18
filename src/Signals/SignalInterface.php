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

/**
 * @package ActiveCollab\JobsQueue\Signals
 */
interface SignalInterface
{
    /**
     * Return true if you would like to signal queue to keep the job instead of removing it.
     *
     * @return bool
     */
    public function keepJobInQueue();
}
