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

use ActiveCollab\JobsQueue\JobsDispatcherInterface;

abstract class Batch implements BatchInterface
{
    protected JobsDispatcherInterface $dispatcher;

    /**
     * @param JobsDispatcherInterface $dispatcher
     * @param int                 $queue_id
     * @param string              $name
     */
    public function __construct(JobsDispatcherInterface &$dispatcher, $queue_id = null, $name = null)
    {
        $this->dispatcher = $dispatcher;
        $this->queue_id = $queue_id;
        $this->name = $name;
    }

    /**
     * @var mixed
     */
    private $queue_id;

    /**
     * {@inheritdoc}
     */
    public function getQueueId()
    {
        return $this->queue_id;
    }

    /**
     * @var string
     */
    private $name;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
