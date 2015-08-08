<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Test\Jobs\Failing;
  use ActiveCollab\JobsQueue\Test\Jobs\Inc;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class DelayedJobsTest extends AbstractMySqlQueueTest
  {
    /**
     * Test getting a delayed job
     */
    public function testGettingDelayedJob()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123, 'delay' => 1 ])));

      $this->assertNull($this->dispatcher->getQueue()->nextInLine());

      sleep(1);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());
    }

    public function testDelayIsAppliedToFailedAttempts()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Failing([ 'delay' => 1, 'attempts' => 2 ])));

      $this->assertRecordsCount(1);

      // First attempt
      $this->assertNull($this->dispatcher->getQueue()->nextInLine());

      sleep(1);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $this->dispatcher->getQueue()->execute($next_in_line);

      // Second attempt
      $this->assertRecordsCount(1);
      $this->assertAttempts(1, $next_in_line->getQueueId());

      $this->assertNull($this->dispatcher->getQueue()->nextInLine()); // Not yet available

      sleep(1);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $this->dispatcher->getQueue()->execute($next_in_line);
      $this->assertRecordsCount(0);
    }
  }