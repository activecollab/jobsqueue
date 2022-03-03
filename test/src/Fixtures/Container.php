<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Test\Fixtures;

use Pimple\Container as BaseContainer;
use Psr\Container\ContainerInterface;

class Container extends BaseContainer implements ContainerInterface
{
    public function get(string $id)
    {
        return $this->offsetGet($id);
    }

    public function has(string $id)
    {
        return $this->offsetExists($id);
    }
}
