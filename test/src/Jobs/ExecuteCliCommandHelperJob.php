<?php

namespace ActiveCollab\JobsQueue\Test\Jobs;

use ActiveCollab\JobsQueue\Helpers\ExecuteCliCommand;
use ActiveCollab\JobsQueue\Jobs\Job;

/**
 * @package ActiveCollab\JobsQueue\Test\Jobs
 */
class ExecuteCliCommandHelperJob extends Job
{
    use ExecuteCliCommand;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $data = null)
    {
        $this->validateCommand($data);

        parent::__construct($data);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        return $this->prepareCommandFromData();
    }
}