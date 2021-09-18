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

use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\JobsDispatcher;
use ActiveCollab\JobsQueue\Queue\TestQueue;
use ActiveCollab\JobsQueue\Test\Base\TestCase;
use ActiveCollab\JobsQueue\Test\Jobs\Inc;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class JobPriorityTest extends TestCase
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

    /**
     * Test if job starts with NOT_A_PRIORITY value.
     */
    public function testJobIsHasNoPriorityByDefault()
    {
        $this->assertEquals(Job::NOT_A_PRIORITY, (new Inc(['number' => 123]))->getData()['priority']);
    }

    /**
     * Test if we can set a priority that's not spefied with PRIORITY constants.
     */
    public function testCustomPriority()
    {
        $this->assertEquals(123, (new Inc(['number' => 123, 'priority' => 123]))->getData()['priority']);
    }

    /**
     * Test if job can't have a priority value lower than NOT_A_PRIORITY.
     */
    public function testJobCantHavePriorityLowerThanNotPriority()
    {
        $this->assertEquals(Job::NOT_A_PRIORITY, (new Inc(['number' => 123, 'priority' => -123]))->getData()['priority']);
    }

    /**
     * Test if job can't have a priority value higher than HIGHEST_PRIORITY.
     */
    public function testJobCantHavePriorityHigherThanHighestPriority()
    {
        $this->assertEquals(Job::HAS_HIGHEST_PRIORITY, (new Inc(['number' => 123, 'priority' => Job::HAS_HIGHEST_PRIORITY + 1]))->getData()['priority']);
    }
}
