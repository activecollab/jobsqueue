<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Dispatcher;
  use ActiveCollab\JobsQueue\Queue\TestQueue;
  use ActiveCollab\JobsQueue\Test\Jobs\Inc;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class JobDelayTest extends TestCase
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

      $this->dispatcher = new Dispatcher(new TestQueue());

      $this->assertCount(0, $this->dispatcher->getQueue());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testJobDelayNeedsToBeInteger()
    {
      new Inc([ 'number' => 123, 'delay' => '123' ]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testMinDelayIsOne()
    {
      new Inc([ 'number' => 123, 'delay' => 0 ]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testMaxDelayIsOneHour()
    {
      new Inc([ 'number' => 123, 'delay' => 7200 ]);
    }

    /**
     * Test jobs have no delay by default
     */
    public function testNoDelayByDefault()
    {
      $job = new Inc([ 'number' => 123 ]);
      $this->assertEquals(0, $job->getDelay());
    }

    /**
     * Test delay is set using delay data property
     */
    public function testDelayIsSetUsingData()
    {
      $job = new Inc([ 'number' => 123, 'delay' => 15 ]);
      $this->assertEquals(15, $job->getDelay());
    }
  }