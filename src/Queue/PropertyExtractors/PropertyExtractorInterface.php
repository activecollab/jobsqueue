<?php

declare(strict_types=1);

namespace ActiveCollab\JobsQueue\Queue\PropertyExtractors;

interface PropertyExtractorInterface
{
    public function getName(): string;

    public function getDataPath(): string;
}