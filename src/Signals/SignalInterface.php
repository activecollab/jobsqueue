<?php

namespace ActiveCollab\JobsQueue\Signals;

/**
 * @package ActiveCollab\JobsQueue\Signals
 */
interface SignalInterface
{
    /**
     * Return true if you would like to signal queue to keep the job instead of removing it
     *
     * @return boolean
     */
    public function keepJobInQueue();
}
