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
use InvalidArgumentException;

class Inc extends Job
{
    /**
     * Construct a new Job instance.
     *
     * @param array|null $data
     */
    public function __construct(array $data = null)
    {
        if (!($data && is_array($data) && array_key_exists('number', $data) && is_int($data['number']))) {
            throw new InvalidArgumentException('Number is required and it needs to be an integer value');
        }

        parent::__construct($data);
    }

    public function execute(): mixed
    {
        return $this->getData()['number'] + 1;
    }
}
