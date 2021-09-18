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

use ActiveCollab\JobsQueue\Test\Base\AbstractMySqlQueueTest;
use ActiveCollab\JobsQueue\Test\Jobs\Failing;
use ActiveCollab\JobsQueue\Test\Jobs\Inc;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class DelayedJobsTest extends AbstractMySqlQueueTest
{
    /**
     * Test getting a delayed job.
     */
    public function testGettingDelayedJob()
    {
        $this->assertEquals(1, $this->dispatcher->dispatch(new Inc(['number' => 123, 'delay' => 2])));

        $this->assertNull($this->dispatcher->getQueue()->nextInLine());

        sleep(2);

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());
    }

    /**
     * Test if delay is applied to failed attempts.
     */
    public function testDelayIsAppliedToFailedAttempts()
    {
        $this->assertRecordsCount(0);

        // Set delay of two seconds, because we sometimes got nextInLine() when job was set in one second, and we got to
        // the next second during assertRecordsCount() step
        $this->assertEquals(1, $this->dispatcher->dispatch(new Failing(['delay' => 2, 'attempts' => 2])));

        $this->assertRecordsCount(1);

        // First attempt
        $this->assertNull($this->dispatcher->getQueue()->nextInLine());

        sleep(2);

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        // Second attempt
        $this->assertRecordsCount(1);
        $this->assertAttempts(1, $next_in_line->getQueueId());

        $this->assertNull($this->dispatcher->getQueue()->nextInLine()); // Not yet available

        sleep(2);

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);
        $this->assertRecordsCount(0);
    }

    /**
     * Test if first job delay can be different than failed attempts.
     */
    public function testFirstAttemptCanHaveDifferentDelayThanFailedAttempts()
    {
        $this->assertRecordsCount(0);

        // Set delay of two seconds, because we sometimes got nextInLine() when job was set in one second, and we got to
        // the next second during assertRecordsCount() step
        $failing_job_with_instant_first_attempt = new Failing([
        'delay' => 2,
        'attempts' => 2,
        'first_attempt_delay' => 0,
        ]);

        $this->assertEquals(0, $failing_job_with_instant_first_attempt->getFirstJobDelay());
        $this->assertEquals(2, $failing_job_with_instant_first_attempt->getDelay());

        // Enqueue
        $this->assertEquals(1, $this->dispatcher->dispatch($failing_job_with_instant_first_attempt));

        $this->assertRecordsCount(1);

        // First attempt
        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        // Second attempt
        $this->assertRecordsCount(1);
        $this->assertAttempts(1, $next_in_line->getQueueId());

        $this->assertNull($this->dispatcher->getQueue()->nextInLine()); // Not yet available

        sleep(2);

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);
        $this->assertRecordsCount(0);
    }
}
