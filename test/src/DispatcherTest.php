<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Dispatcher;
  use ActiveCollab\JobsQueue\Queue\Test;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class DispatcherTest extends TestCase
  {
    /**
     * Test that dispatcher can constructed without a queue
     */
    public function testDespatcherCanBeConstructedWithoutAQueue()
    {
      $dispatcher = new Dispatcher();
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Dispatcher', $dispatcher);
    }

    /**
     * Test creation of dispatcher instance with default queue
     */
    public function testDespatcherWithDefaultQueue()
    {
      $dispatcher = new Dispatcher(new Test());
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\Test', $dispatcher->getQueue());
    }

    /**
     * Test create a discpatcher instance with a list of named queues
     */
    public function testDispatcherWithNamedQueues()
    {
      $dispatcher = new Dispatcher([
        'first' => new Test(),
        'second' => new Test(),
        'third' => new Test(),
      ]);

      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\Test', $dispatcher->getQueue('first'));
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\Test', $dispatcher->getQueue('second'));
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\Test', $dispatcher->getQueue('third'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDispatcherConstructorErrorOnInvalidParam()
    {
      new Dispatcher('Hello world!');
    }

    /**
     * Test if we can specify a default queue after the construction of dispatcher object
     */
    public function testAddDefaultQueue()
    {
      $dispatcher = new Dispatcher();
      $dispatcher->addQueue(Dispatcher::DEFAULT_QUEUE, new Test());
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\Test', $dispatcher->getQueue());
    }

    /**
     * Test add named queue
     */
    public function testAddNamedQueue()
    {
      $dispatcher = new Dispatcher();
      $dispatcher->addQueue('example_queue', new Test());
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\Test', $dispatcher->getQueue('example_queue'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionOnAddExistingQueue()
    {
      $dispatcher = new Dispatcher([ 'example_queue' => new Test() ]);
      $dispatcher->addQueue('example_queue', new Test());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionOnInvalidQueueName()
    {
      $dispatcher = new Dispatcher(new Test());
      $dispatcher->getQueue('this queue does not exist');
    }
  }