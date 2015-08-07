<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Dispatcher;
  use ActiveCollab\JobsQueue\Jobs\Job;
  use ActiveCollab\JobsQueue\Queue\ArrayQueue;
  use ActiveCollab\JobsQueue\Test\Jobs\Inc;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class JobPriorityTest extends TestCase
  {
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * Set up test environment
     */
    public function setUp()
    {
      parent::setUp();

      $this->dispatcher = new Dispatcher(new ArrayQueue());

      $this->assertCount(0, $this->dispatcher->getQueue());
    }

    /**
     * Test if job starts with NOT_A_PRIORITY value
     */
    public function testJobIsHasNoPriorityByDefault()
    {
      $this->assertEquals(Job::NOT_A_PRIORITY, (new Inc())->getData()['priority']);
    }

    /**
     * Test if we can set a priority that's not spefied with PRIORITY constants
     */
    public function testCustomPriority()
    {
      $this->assertEquals(123, (new Inc([ 'priority' => 123 ]))->getData()['priority']);
    }

    /**
     * Test if job can't have a priority value lower than NOT_A_PRIORITY
     */
    public function testJobCantHavePriorityLowerThanNotPriority()
    {
      $this->assertEquals(Job::NOT_A_PRIORITY, (new Inc([ 'priority' => -123 ]))->getData()['priority']);
    }

    /**
     * Test if job can't have a priority value higher than HIGHEST_PRIORITY
     */
    public function testJobCantHavePriorityHigherThanHighestPriority()
    {
      $this->assertEquals(Job::HAS_HIGHEST_PRIORITY, (new Inc([ 'priority' => Job::HAS_HIGHEST_PRIORITY + 1 ]))->getData()['priority']);
    }
  }