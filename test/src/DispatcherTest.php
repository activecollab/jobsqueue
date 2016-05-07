<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Test;

use ActiveCollab\JobsQueue\Dispatcher;
use ActiveCollab\JobsQueue\Queue\TestQueue;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class DispatcherTest extends TestCase
{
    /**
     * Test creation of dispatcher instance with default queue.
     */
    public function testDespatcherWithDefaultQueue()
    {
        $dispatcher = new Dispatcher(new TestQueue());
        $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\TestQueue', $dispatcher->getQueue());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDispatcherConstructorErrorOnInvalidParam()
    {
        new Dispatcher('Hello world!');
    }
}
