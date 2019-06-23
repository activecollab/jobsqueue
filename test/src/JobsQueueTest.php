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

use ActiveCollab\JobsQueue\Queue\TestQueue;
use Countable;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class JobsQueueTest extends TestCase
{
    /**
     * Test if queue implements Countable interface.
     */
    public function testQueuesAreCountable()
    {
        $this->assertInstanceOf(Countable::class, new TestQueue());
    }
}
