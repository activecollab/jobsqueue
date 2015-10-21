<?php

namespace ActiveCollab\JobsQueue\Test\Jobs;

use ActiveCollab\JobsQueue\Jobs\Job;

/**
 * @package ActiveCollab\JobsQueue\Test\Jobs
 */
class ProcessLauncher extends Job
{
    const TEST_PROCESS_ID = 12345;

    /**
     * Report that we launched a background process
     */
    public function execute()
    {
        return $this->reportBackgroundProcess(self::TEST_PROCESS_ID);
    }
}