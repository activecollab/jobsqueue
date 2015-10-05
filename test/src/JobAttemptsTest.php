<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Dispatcher;
  use ActiveCollab\JobsQueue\Queue\TestQueue;
  use ActiveCollab\JobsQueue\Test\Jobs\Inc;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class JobAttemptsTest extends TestCase
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
    public function testJobAttemptsNeedsToBeInteger()
    {
      new Inc([ 'number' => 123, 'attempts' => '123' ]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testMinAttemptsIsOne()
    {
      new Inc([ 'number' => 123, 'attempts' => 0 ]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testMaxAttemptsIs256()
    {
      new Inc([ 'number' => 123, 'attempts' => 1000 ]);
    }

    /**
     * Test jobs are attempted once by default
     */
    public function testDefaultAttemptsIsOne()
    {
      $job = new Inc([ 'number' => 123 ]);
      $this->assertEquals(1, $job->getAttempts());
    }

    /**
     * Test attempts is set using attempts data property
     */
    public function testAttemptsIsSetUsingData()
    {
      $job = new Inc([ 'number' => 123, 'attempts' => 13 ]);
      $this->assertEquals(13, $job->getAttempts());
    }
  }