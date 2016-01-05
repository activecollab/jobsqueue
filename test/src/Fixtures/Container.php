<?php

namespace ActiveCollab\JobsQueue\Test\Fixtures;

use Interop\Container\ContainerInterface;
use Pimple\Container as BaseContainer;

/**
 * @package ActiveCollab\JobQueue\Test\Commands\Fixtures
 */
class Container extends BaseContainer implements ContainerInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        return $this->offsetGet($id);
    }

    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
        return $this->offsetExists($id);
    }
}
