<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Test\Jobs;

use ActiveCollab\JobsQueue\Jobs\Job;
use Exception;

/**
 * @package ActiveCollab\JobsQueue\Test\Jobs
 */
class Failing extends Job
{
    /**
     * Always fail.
     *
     * @return int
     * @throws Exception
     */
    public function execute()
    {
        throw new Exception('Built to fail!');
    }
}
