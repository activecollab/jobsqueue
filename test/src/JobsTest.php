<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Dispatcher;
  use ActiveCollab\JobsQueue\Queue\ArrayQueue;
  use ActiveCollab\JobsQueue\Test\Jobs\Inc;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class JobsTest extends TestCase
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
     * Test if dispatch adds a job to the queue
     */
    public function testDispatchAddsEventToTheQueue()
    {
      $this->assertEquals(0, $this->dispatcher->dispatch(new Inc([ 'number' => 1245 ])));
      $this->assertCount(1, $this->dispatcher->getQueue());
    }

    /**
     * Test if run executes immediately
     */
    public function testRunExecutesImmediately()
    {
      $this->assertEquals(1246, $this->dispatcher->run(new Inc([ 'number' => 1245 ])));
      $this->assertCount(0, $this->dispatcher->getQueue());
    }
  }