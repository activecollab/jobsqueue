<?php

  namespace ActiveCollab\JobsQueue\Test\Jobs;

  use ActiveCollab\JobsQueue\Jobs\Job;

  /**
   * @package ActiveCollab\JobsQueue\Test\Jobs
   */
  class Inc extends Job
  {
    /**
     * Increment a number
     *
     * @return integer
     */
    public function execute()
    {
      return $this->getData()['number'] + 1;
    }
  }