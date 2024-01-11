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

use ActiveCollab\JobsQueue\Helpers\Port;
use ActiveCollab\JobsQueue\Jobs\Job;

class PortHelperJob extends Job
{
    use Port;

    const DEFAULT_PORT = 1234;

    public function __construct(array $data = null)
    {
        $this->validatePort($data, self::DEFAULT_PORT);

        parent::__construct($data);
    }

    public function execute(): mixed
    {
        return null;
    }
}
