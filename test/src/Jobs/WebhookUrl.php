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

class WebhookUrl extends Job
{
    public function __construct(array $data = null)
    {
        if (empty($data['webhook_url'])) {
            throw new \InvalidArgumentException('Webhook URL required.');
        }

        parent::__construct($data);
    }

    public function execute()
    {
    }
}