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
use ActiveCollab\JobsQueue\Test\Base\IntegratedMySqlQueueTest;
use ActiveCollab\JobsQueue\Test\Jobs\Failing;
use Exception;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class OnJobFailedCallbacksTest extends IntegratedMySqlQueueTest
{
    /**
     * Test to check if we can set multiple failure callbacks.
     */
    public function testExtraCallback()
    {
        $failure_count = 0;
        $failure_message = '';

        $this->dispatcher->getQueue()->onJobFailure(function (Job $job, Exception $e) use (&$failure_count, &$failure_message) {
            ++$failure_count;
            $failure_message = $e->getMessage();
        });

        $this->assertRecordsCount(0);

        $this->assertEquals(1, $this->dispatcher->dispatch(new Failing()));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertEquals('Built to fail!', $this->last_failure_message);
        $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);

        $this->assertEquals('Built to fail!', $failure_message);
        $this->assertEquals(1, $failure_count);
    }
}
