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

use ActiveCollab\JobsQueue\Helpers\Port;
use ActiveCollab\JobsQueue\Jobs\Job;

/**
 * @package ActiveCollab\JobsQueue\Test\Jobs
 */
class PortHelperJob extends Job
{
    use Port;

    const DEFAULT_PORT = 1234;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $data = null)
    {
        $this->validatePort($data, self::DEFAULT_PORT);

        parent::__construct($data);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
    }
}
