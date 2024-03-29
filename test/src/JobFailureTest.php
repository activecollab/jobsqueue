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

use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Test\Base\IntegratedMySqlQueueTest;
use ActiveCollab\JobsQueue\Test\Jobs\Failing;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class JobFailureTest extends IntegratedMySqlQueueTest
{
    /**
     * Test job failure.
     */
    public function testJobFailure()
    {
        $this->assertRecordsCount(0);

        $this->assertEquals(1, $this->dispatcher->dispatch(new Failing()));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);
        $this->assertEquals('Built to fail!', $this->last_failure_message);

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->getQueue()->countFailed());
    }

    /**
     * Test if job failure reason is properly logged.
     */
    public function testJobFailureLogsReason()
    {
        $this->assertRecordsCount(0);

        $this->assertEquals(1, $this->dispatcher->dispatch(new Failing()));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);
        $this->assertEquals('Built to fail!', $this->last_failure_message);

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->getQueue()->countFailed());

        $this->assertEquals('Built to fail!', $this->connection->executeFirstCell('SELECT `reason` FROM `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '` WHERE `id` = ?', 1));
    }

    public function testJobFailureTrimsLongReason()
    {
        $this->assertRecordsCount(0);

        $exception_message = '';

        for ($i = 0; $i < 10; $i++) {
            $exception_message .= 'abcdefghijklmnopqrstuvwxyz';
        }

        $this->assertGreaterThan(191, strlen($exception_message));

        $this->assertEquals(1, $this->dispatcher->dispatch(new Failing([
            'exception_message' => $exception_message,
        ])));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);
        $this->assertEquals($exception_message, $this->last_failure_message);

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->getQueue()->countFailed());

        $this->assertEquals(substr($exception_message, 0, 191), $this->connection->executeFirstCell('SELECT `reason` FROM `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '` WHERE `id` = ?', 1));
    }

    /**
     * Test count failed by job type.
     */
    public function testCountFailedByJobType()
    {
        $this->assertEquals(1, $this->dispatcher->dispatch(new Failing()));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);
        $this->assertEquals('Built to fail!', $this->last_failure_message);

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->getQueue()->countFailed());
        $this->assertEquals(1, $this->dispatcher->getQueue()->countFailedByType('ActiveCollab\JobsQueue\Test\Jobs\Failing'));
        $this->assertEquals(1, $this->dispatcher->getQueue()->countFailedByType('ActiveCollab\JobsQueue\Test\Jobs\Inc', 'ActiveCollab\JobsQueue\Test\Jobs\Failing'));
        $this->assertEquals(0, $this->dispatcher->getQueue()->countFailedByType('ActiveCollab\JobsQueue\Test\Jobs\Inc'));
    }

    /**
     * Test if job is retried after failure until attempts limit is reached.
     */
    public function testJobFailureAttempts()
    {
        $this->assertRecordsCount(0);

        $failing_job = new Failing(['attempts' => 3]);
        $this->assertEquals(3, $failing_job->getAttempts());

        $this->assertEquals(1, $this->dispatcher->dispatch($failing_job));

        // First attempt
        $next_in_line = $this->dispatcher->getQueue()->nextInLine();
        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);
        $this->assertEquals('Built to fail!', $this->last_failure_message);

        $this->assertRecordsCount(1);
        $this->assertAttempts(1, $next_in_line->getQueueId());

        // Second attempt
        $next_in_line = $this->dispatcher->getQueue()->nextInLine();
        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);
        $this->assertAttempts(2, $next_in_line->getQueueId());

        // Third attempt
        $next_in_line = $this->dispatcher->getQueue()->nextInLine();
        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);
        $this->assertAttempts(null, $next_in_line->getQueueId());
        $this->assertNull($this->dispatcher->getQueue()->nextInLine());
    }
}
