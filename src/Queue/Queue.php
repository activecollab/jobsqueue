<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Queue;

use Psr\Log\LoggerInterface;

/**
 * @package ActiveCollab\JobsQueue\Queue
 */
abstract class Queue implements QueueInterface
{
    /**
     * @var null|LoggerInterface
     */
    protected $log;

    /**
     * @param LoggerInterface|null $log
     */
    public function __construct(LoggerInterface &$log = null)
    {
        if ($log) {
            $this->log = $log;
        }
    }
}
