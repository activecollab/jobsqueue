<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Dispatcher;
  use ActiveCollab\JobsQueue\Queue\ArrayQueue;

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
      $dispatcher = new Dispatcher(new ArrayQueue());
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\ArrayQueue', $dispatcher->getQueue());
    }

    /**
     * Test create a discpatcher instance with a list of named queues
     */
    public function testDispatcherWithNamedQueues()
    {
      $dispatcher = new Dispatcher([
        'first' => new ArrayQueue(),
        'second' => new ArrayQueue(),
        'third' => new ArrayQueue(),
      ]);
      
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\ArrayQueue', $dispatcher->getQueue('first'));
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\ArrayQueue', $dispatcher->getQueue('second'));
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\ArrayQueue', $dispatcher->getQueue('third'));
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
      $dispatcher->addQueue(Dispatcher::DEFAULT_QUEUE, new ArrayQueue());
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\ArrayQueue', $dispatcher->getQueue());
    }

    /**
     * Test add named queue
     */
    public function testAddNamedQueue()
    {
      $dispatcher = new Dispatcher();
      $dispatcher->addQueue('example_queue', new ArrayQueue());
      $this->assertInstanceOf('\ActiveCollab\JobsQueue\Queue\ArrayQueue', $dispatcher->getQueue('example_queue'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionOnAddExistingQueue()
    {
      $dispatcher = new Dispatcher([ 'example_queue' => new ArrayQueue() ]);
      $dispatcher->addQueue('example_queue', new ArrayQueue());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionOnInvalidQueueName()
    {
      $dispatcher = new Dispatcher(new ArrayQueue());
      $dispatcher->getQueue('this queue does not exist');
    }
  }