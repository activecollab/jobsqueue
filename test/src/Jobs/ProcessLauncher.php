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

namespace ActiveCollab\JobsQueue\Test\Jobs;

use ActiveCollab\JobsQueue\Jobs\Job;

class ProcessLauncher extends Job
{
    const TEST_PROCESS_ID = 12345;

    /**
     * Report that we launched a background process.
     */
    public function execute(): mixed
    {
        return $this->reportBackgroundProcess(self::TEST_PROCESS_ID);
    }
}
