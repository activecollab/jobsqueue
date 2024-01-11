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

use ActiveCollab\JobsQueue\Helpers\ExecuteCliCommand;
use ActiveCollab\JobsQueue\Jobs\Job;

class ExecuteCliCommandHelperJob extends Job
{
    use ExecuteCliCommand;

    public function __construct(array $data = null)
    {
        $this->validateCommand($data);

        parent::__construct($data);
    }

    public function execute(): mixed
    {
        return $this->prepareCommandFromData($this->getData());
    }
}
