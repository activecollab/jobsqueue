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
use ActiveCollab\JobsQueue\Test\Jobs\Inc;
use InvalidArgumentException;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class JobAttemptsTest extends TestCase
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = new Dispatcher(new TestQueue());

        $this->assertCount(0, $this->dispatcher->getQueue());
    }

    public function testJobAttemptsNeedsToBeInteger()
    {
        $this->expectException(InvalidArgumentException::class);

        new Inc(['number' => 123, 'attempts' => '123']);
    }

    public function testMinAttemptsIsOne()
    {
        $this->expectException(InvalidArgumentException::class);

        new Inc(['number' => 123, 'attempts' => 0]);
    }

    public function testMaxAttemptsIs256()
    {
        $this->expectException(InvalidArgumentException::class);

        new Inc(['number' => 123, 'attempts' => 1000]);
    }

    /**
     * Test jobs are attempted once by default.
     */
    public function testDefaultAttemptsIsOne()
    {
        $job = new Inc(['number' => 123]);
        $this->assertEquals(1, $job->getAttempts());
    }

    /**
     * Test attempts is set using attempts data property.
     */
    public function testAttemptsIsSetUsingData()
    {
        $job = new Inc(['number' => 123, 'attempts' => 13]);
        $this->assertEquals(13, $job->getAttempts());
    }
}
