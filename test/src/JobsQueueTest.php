<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Queue\ArrayQueue;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class JobsQueueTest extends TestCase
  {
    /**
     * Test if queue implements Countable interface
     */
    public function testQueuesAreCountable()
    {
      $this->assertInstanceOf('Countable', new ArrayQueue());
    }
  }