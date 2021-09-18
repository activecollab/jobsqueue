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

namespace ActiveCollab\JobsQueue\Queue\PropertyExtractors;

class StringPropertyExtractor extends PropertyExtractor
{
    private int $length;

    public function __construct(string $name, int $length = 191, string $data_path = null)
    {
        parent::__construct($name, $data_path);

        $this->length = $length;
    }

    public function getLength(): int
    {
        return $this->length;
    }
}
