<?php

  namespace ActiveCollab\JobsQueue\Test\Jobs;

  use ActiveCollab\JobsQueue\Helpers\Port;
  use ActiveCollab\JobsQueue\Jobs\Job;
  use InvalidArgumentException;

  /**
   * @package ActiveCollab\JobsQueue\Test\Jobs
   */
  class PortHelperJob extends Job
  {
    use Port;

    const DEFAULT_PORT = 1234;

    /**
     * Construct a new Job instance
     *
     * @param  array|null               $data
     * @throws InvalidArgumentException
     */
    public function __construct(array $data = null)
    {
      $this->validatePort($data, self::DEFAULT_PORT);

      parent::__construct($data);
    }

    /**
     * Execute the job
     */
    public function execute()
    {
    }
  }