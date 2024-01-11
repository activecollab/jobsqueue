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
     * {@inheritdoc}
     */
    public function __construct(array $data = null)
    {
        if (empty($data)) {
            $data = [];
        }

        if (empty($data['exception_message'])) {
            $data['exception_message'] = 'Built to fail!';
        }

        parent::__construct($data);
    }

    public function execute(): mixed
    {
        throw new Exception($this->getData('exception_message'));
    }
}
