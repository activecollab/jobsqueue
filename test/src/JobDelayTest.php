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
use ActiveCollab\JobsQueue\JobsDispatcher;
use ActiveCollab\JobsQueue\Queue\TestQueue;
use ActiveCollab\JobsQueue\Test\Jobs\Inc;
use InvalidArgumentException;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class JobDelayTest extends TestCase
{
    /**
     * @var JobsDispatcher
     */
    private $dispatcher;

    public function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = new JobsDispatcher(new TestQueue());

        $this->assertCount(0, $this->dispatcher->getQueue());
    }

    public function testJobDelayNeedsToBeInteger()
    {
        $this->expectException(InvalidArgumentException::class);
        new Inc(['number' => 123, 'delay' => '123']);
    }

    public function testMinDelayIsOne()
    {
        $this->expectException(InvalidArgumentException::class);
        new Inc(['number' => 123, 'delay' => 0]);
    }

    public function testMaxDelayIsTreeMonths()
    {
        $this->expectException(InvalidArgumentException::class);
        new Inc(['number' => 123, 'delay' => 7776001]);
    }

    /**
     * Test jobs have no delay by default.
     */
    public function testNoDelayByDefault()
    {
        $job = new Inc(['number' => 123]);
        $this->assertEquals(0, $job->getDelay());
    }

    /**
     * Test delay is set using delay data property.
     */
    public function testDelayIsSetUsingData()
    {
        $job = new Inc(['number' => 123, 'delay' => 15]);
        $this->assertEquals(15, $job->getDelay());
    }
}
