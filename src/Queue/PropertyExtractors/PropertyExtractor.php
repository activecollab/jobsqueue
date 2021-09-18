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

abstract class PropertyExtractor implements PropertyExtractorInterface
{
    private string $name;
    private string $data_path;

    public function __construct(string $name, string $data_path = null)
    {
        $this->name = $name;
        $this->data_path = $data_path ?? sprintf('$.%s', $name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDataPath(): string
    {
        return $this->data_path;
    }
}
