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

namespace ActiveCollab\JobsQueue\Batches;

use ActiveCollab\JobsQueue\JobsDispatcherInterface;

abstract class Batch implements BatchInterface
{
    public function __construct(
        protected JobsDispatcherInterface $dispatcher,
        private ?int $queue_id = null,
        private ?string $name = null,
    )
    {
    }

    public function getQueueId(): ?int
    {
        return $this->queue_id;
    }

    public function getName()
    {
        return $this->name;
    }
}
