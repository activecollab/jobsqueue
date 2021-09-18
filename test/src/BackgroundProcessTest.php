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

use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Signals\ProcessLaunched;
use ActiveCollab\JobsQueue\Test\Jobs\ProcessLauncher;

class BackgroundProcessTest extends AbstractMySqlQueueTest
{
    /**
     * Test if new jobs have an empty process_id value.
     */
    public function testNewJobsHaveNoProcessId()
    {
        $job_id = $this->dispatcher->dispatch(new ProcessLauncher());

        $this->assertSame(1, $job_id);
        $this->assertSame(
            0,
            $this->connection->executeFirstCell(
                sprintf(
                    'SELECT `process_id` FROM `%s` WHERE `id` = ?',
                    MySqlQueue::JOBS_TABLE_NAME
                ),
                $job_id
            )
        );
        $this->assertEmpty($this->dispatcher->getQueue()->getBackgroundProcesses());
    }

    /**
     * Test if queue keeps the job that launches a process.
     */
    public function testProcessLauncherKeepsTheJob()
    {
        $job_id = $this->dispatcher->dispatch(new ProcessLauncher());

        $this->assertSame(1, $job_id);
        $this->assertSame(0, $this->connection->executeFirstCell('SELECT `process_id` FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `id` = ?', $job_id));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf(ProcessLauncher::class, $next_in_line);

        /** @var ProcessLaunched $result */
        $result = $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertInstanceOf(ProcessLaunched::class, $result);
        $this->assertEquals($result->getProcessId(), ProcessLauncher::TEST_PROCESS_ID);

        $this->assertSame(1, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `id` = ?', $job_id));
    }

    /**
     * Test if new jobs have an empty process_id value.
     */
    public function testProcessLauncherSetsProcessId()
    {
        $job_id = $this->dispatcher->dispatch(new ProcessLauncher());

        $this->assertSame(1, $job_id);
        $this->assertSame(0, $this->connection->executeFirstCell('SELECT `process_id` FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `id` = ?', $job_id));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('\ActiveCollab\JobsQueue\Test\Jobs\ProcessLauncher', $next_in_line);

        /** @var ProcessLaunched $result */
        $result = $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertInstanceOf(ProcessLaunched::class, $result);
        $this->assertEquals(ProcessLauncher::TEST_PROCESS_ID, $result->getProcessId());

        $this->assertSame(
            ProcessLauncher::TEST_PROCESS_ID,
            $this->connection->executeFirstCell(
                sprintf(
                    'SELECT `process_id` FROM `%s` WHERE `id` = ?',
                    MySqlQueue::JOBS_TABLE_NAME
                ),
                $job_id
            )
        );

        $background_processes = $this->dispatcher->getQueue()->getBackgroundProcesses();

        $this->assertIsArray($background_processes);
        $this->assertCount(1, $background_processes);
        $this->assertSame(
            [
                'id' => 1,
                'type' => get_class($next_in_line),
                'process_id' => ProcessLauncher::TEST_PROCESS_ID,
            ],
            $background_processes[0]
        );
    }
}
