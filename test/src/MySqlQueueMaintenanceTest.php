<?php

namespace ActiveCollab\JobsQueue\Test;

use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Test\Jobs\Failing;
use ActiveCollab\JobsQueue\Test\Jobs\Inc;
use ActiveCollab\JobsQueue\Test\Jobs\ProcessLauncher;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class MySqlQueueMaintenanceTest extends AbstractMySqlQueueTest
{
    /**
     * Test clean-up method removes failed job logs older than 7 days
     */
    public function testCleanUpJobsFailedMoreThan7DaysAgo()
    {
        $this->assertRecordsCount(0);

        for ($i = 1; $i <= 5; $i++) {
            $this->assertEquals($i, $this->dispatcher->dispatch(new Failing()));

            $next_in_line = $this->dispatcher->getQueue()->nextInLine();

            $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
            $this->assertEquals($i, $next_in_line->getQueueId());

            $this->dispatcher->getQueue()->execute($next_in_line);

            $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);
            $this->assertEquals('Built to fail!', $this->last_failure_message);
        }

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->assertEquals(5, $this->dispatcher->getQueue()->countFailed());

        // Lets age a record a bit
        $this->connection->execute('UPDATE `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '` SET `failed_at` = ? WHERE `id` = ?', date('Y-m-d H:i:s', strtotime('-14 days')), 1);

        // Check the queue, clean up and check again
        $this->assertEquals(5, $this->dispatcher->getQueue()->countFailed());
        $this->dispatcher->getQueue()->cleanUp();
        $this->assertEquals(4, $this->dispatcher->getQueue()->countFailed());
    }

    /**
     * Test unstuck job by failing it
     */
    public function testUnstuckJob()
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertEquals($i, $this->dispatcher->dispatch(new Inc(['number' => 123])));
        }

        $this->assertEquals(5, $this->dispatcher->getQueue()->count());
        $this->assertEquals(0, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));

        /** @var Inc $reserved_but_not_going_to_be_executed */
        $reserved_but_not_going_to_be_executed = $this->dispatcher->getQueue()->nextInLine();
        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $reserved_but_not_going_to_be_executed);

        $this->assertEquals(1, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));

        // Lets simulate that job #1 is stuck
        $this->connection->execute('UPDATE `' . MySqlQueue::JOBS_TABLE_NAME . '` SET `reserved_at` = ? WHERE `id` = ?', date('Y-m-d H:i:s', time() - 7200), 1);

        $this->dispatcher->getQueue()->checkStuckJobs();

        $this->assertEquals(0, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));
        $this->assertEquals(4, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->getQueue()->countFailed());
    }

    /**
     * Test unstuck job by failing it and respecting attempts settings
     */
    public function testUnstuckJobRespectsAttemptsSettings()
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertEquals($i, $this->dispatcher->dispatch(new Inc(['number' => 123, 'attempts' => 5])));
        }

        $this->assertEquals(5, $this->dispatcher->getQueue()->count());
        $this->assertEquals(0, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));

        /** @var Inc $reserved_but_not_going_to_be_executed */
        $reserved_but_not_going_to_be_executed = $this->dispatcher->getQueue()->nextInLine();
        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $reserved_but_not_going_to_be_executed);

        $this->assertEquals(1, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));
        $this->assertEquals(0, (integer)$this->connection->executeFirstCell('SELECT `attempts` FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `id` = ?', 1));

        // Lets simulate that job #1 is stuck
        $this->connection->execute('UPDATE `' . MySqlQueue::JOBS_TABLE_NAME . '` SET `reserved_at` = ? WHERE `id` = ?', date('Y-m-d H:i:s', time() - 7200), 1);

        $this->dispatcher->getQueue()->checkStuckJobs();

        $this->assertEquals(0, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));
        $this->assertEquals(1, (integer)$this->connection->executeFirstCell('SELECT `attempts` FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `id` = ?', 1));
        $this->assertEquals(5, $this->dispatcher->getQueue()->count());
        $this->assertEquals(0, $this->dispatcher->getQueue()->countFailed());
    }

    /**
     * Test if stuck jobs checker also checks if job launched a process and that process with that PID is still running
     */
    public function testJobIsNotConsideredStuckIfBackgroundProcessIsRunning()
    {
        $job_id = $this->dispatcher->dispatch(new ProcessLauncher());

        $this->assertEquals(1, $job_id);
        $this->assertEquals(1, $this->dispatcher->getQueue()->count());
        $this->assertEquals(0, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));

        /** @var ProcessLauncher $reserved_but_not_going_to_be_executed */
        $reserved_but_not_going_to_be_executed = $this->dispatcher->getQueue()->nextInLine();
        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\ProcessLauncher', $reserved_but_not_going_to_be_executed);

        $this->assertEquals(1, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));

        // Lets simulate that job reported a background process and that it is
        $this->connection->execute('UPDATE `' . MySqlQueue::JOBS_TABLE_NAME . '` SET `reserved_at` = ?, `process_id` = ? WHERE `id` = ?', date('Y-m-d H:i:s', time() - 7200), getmypid(), 1);

        $this->dispatcher->getQueue()->checkStuckJobs();

        $this->assertEquals(1, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));
        $this->assertEquals(1, $this->dispatcher->getQueue()->count());
        $this->assertEquals(0, $this->dispatcher->getQueue()->countFailed());
    }

    /**
     * Test if stuck jobs with process that's completed are considered successful
     */
    public function testJobsWithCompletedProcessesAreConsideredSuccessful()
    {
        $job_id = $this->dispatcher->dispatch(new ProcessLauncher());

        $this->assertEquals(1, $job_id);
        $this->assertEquals(1, $this->dispatcher->getQueue()->count());
        $this->assertEquals(0, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));

        /** @var ProcessLauncher $reserved_but_not_going_to_be_executed */
        $reserved_but_not_going_to_be_executed = $this->dispatcher->getQueue()->nextInLine();
        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\ProcessLauncher', $reserved_but_not_going_to_be_executed);

        $this->assertEquals(1, $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . MySqlQueue::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL'));

        $unused_process_id = 32767;

        while (posix_kill($unused_process_id, 0)) {
            $unused_process_id--;

            if ($unused_process_id === 0) {
                $this->fail('We have reached 0');
                return;
            }
        }

        // Lets simulate that job reported a background process and that it is
        $this->connection->execute('UPDATE `' . MySqlQueue::JOBS_TABLE_NAME . '` SET `reserved_at` = ?, `process_id` = ? WHERE `id` = ?', date('Y-m-d H:i:s', time() - 7200), $unused_process_id, 1);

        $this->dispatcher->getQueue()->checkStuckJobs();

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->assertEquals(0, $this->dispatcher->getQueue()->countFailed());
    }
}