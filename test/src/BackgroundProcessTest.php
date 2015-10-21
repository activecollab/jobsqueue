<?php

namespace ActiveCollab\JobsQueue\Test;

use ActiveCollab\JobsQueue\Test\Jobs\ProcessLauncher;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class BackgroundProcessTest extends AbstractMySqlQueueTest
{
    /**
     * Test if new jobs have an empty process_id value
     */
    public function testNewJobsHaveNoProcessId()
    {
        $job_id = $this->dispatcher->dispatch(new ProcessLauncher());

        $this->assertSame(1, $job_id);
        $this->assertSame(0, $this->connection->executeFirstCell('SELECT `process_id` FROM `jobs_queue` WHERE `id` = ?', $job_id));
        $this->assertEmpty($this->dispatcher->getQueue()->getBackgroundProcesses());
    }

    /**
     * Test if new jobs have an empty process_id value
     */
    public function testProcessLauncherSetsProcessId()
    {
        $job_id = $this->dispatcher->dispatch(new ProcessLauncher());

        $this->assertSame(1, $job_id);
        $this->assertSame(0, $this->connection->executeFirstCell('SELECT `process_id` FROM `jobs_queue` WHERE `id` = ?', $job_id));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('\ActiveCollab\JobsQueue\Test\Jobs\ProcessLauncher', $next_in_line);

        $next_in_line->execute();

        $this->assertSame(ProcessLauncher::TEST_PROCESS_ID, $this->connection->executeFirstCell('SELECT `process_id` FROM `jobs_queue` WHERE `id` = ?', $job_id));

        $background_processes = $this->dispatcher->getQueue()->getBackgroundProcesses();

        $this->assertInternalType('array', $background_processes);
        $this->assertCount(1, $background_processes);
        $this->assertSame([
            'id' => 1,
            'type' => get_class($next_in_line),
            'process_id' => ProcessLauncher::TEST_PROCESS_ID,
        ], $background_processes[0]);
    }
}