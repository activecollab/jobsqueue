<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace ActiveCollab\JobsQueue\Test;

use ActiveCollab\JobsQueue\JobsDispatcher;
use ActiveCollab\JobsQueue\Queue\TestQueue;
use ActiveCollab\JobsQueue\Test\Base\TestCase;

class DispatcherTest extends TestCase
{
    public function testDespatcherWithDefaultQueue(): void
    {
        $dispatcher = new JobsDispatcher(new TestQueue());
        $this->assertInstanceOf(TestQueue::class, $dispatcher->getQueue());
    }
}
