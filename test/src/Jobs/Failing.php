<?php

  namespace ActiveCollab\JobsQueue\Test\Jobs;

  use ActiveCollab\JobsQueue\Jobs\Job;
  use Exception;

  /**
   * @package ActiveCollab\JobsQueue\Test\Jobs
   */
  class Failing extends Job
  {
    /**
     * Always fail
     *
     * @return integer
     * @throws Exception
     */
    public function execute()
    {
      throw new Exception('Built to fail!');
    }
  }