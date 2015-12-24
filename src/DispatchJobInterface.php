<?php

namespace ActiveCollab\JobsQueue;

use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\Queue\QueueInterface;

/**
 * @package ActiveCollab\JobsQueue
 */
interface DispatchJobInterface
{
    /**
     * Add a job to the queue
     *
     * @param  JobInterface $job
     * @param  string       $channel
     * @return mixed
     */
    public function dispatch(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL);
}
