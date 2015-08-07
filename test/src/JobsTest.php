<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Dispatcher;
  use ActiveCollab\JobsQueue\Queue\ArrayQueue;
  use ActiveCollab\JobsQueue\Test\Jobs\Inc;

  /**
   * Class description
   *
   * @package
   * @subpackage
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

    public function testInc()
    {
      $number = 1245;

      $this->dispatcher->dispatch(new Inc([ 'number' => $number ]), function($run_result) use (&$number) {
        $number = $run_result;
      });

      $this->assertEquals(1246, $number);
    }
  }