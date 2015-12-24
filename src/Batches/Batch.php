<?php

namespace ActiveCollab\JobsQueue\Batches;

use ActiveCollab\JobsQueue\DispatcherInterface;

/**
 * @package ActiveCollab\JobsQueue
 */
abstract class Batch implements BatchInterface
{
    /**
     * @var DispatcherInterface
     */
    protected $dispatcher;

    /**
     * @param DispatcherInterface $dispatcher
     * @param integer             $queue_id
     * @param string              $name
     */
    public function __construct(DispatcherInterface &$dispatcher, $queue_id = null, $name = null)
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
