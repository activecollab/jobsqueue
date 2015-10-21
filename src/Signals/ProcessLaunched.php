<?php

namespace ActiveCollab\JobsQueue\Signals;

/**
 * @package ActiveCollab\JobsQueue\Signals
 */
class ProcessLaunched implements SignalInterface
{
    /**
     * @var integer
     */
    private $process_id;

    /**
     * @param integer $process_id
     */
    public function __construct($process_id)
    {
        $this->process_id = $process_id;
    }

    /**
     * @return integer
     */
    public function getProcessId()
    {
        return $this->process_id;
    }

    /**
     * Return true if you would like to signal queue to keep the job instead of removing it
     *
     * @return boolean
     */
    public function keepJobInQueue()
    {
        return true;
    }
}
