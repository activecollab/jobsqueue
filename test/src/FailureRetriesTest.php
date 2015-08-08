<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Test\Jobs\Failing;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class FailureRetriesTest extends AbstractMySqlQueueTest
  {
    /**
     * Test job failure
     */
    public function testJobFailure()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Failing()));

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $this->dispatcher->getQueue()->execute($next_in_line);

      $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);
      $this->assertEquals('Built to fail!', $this->last_failure_message);

      $this->assertRecordsCount(0);
    }

    /**
     * Test if job is retried after failure until attempts limit is reached
     */
    public function testJobFailureAttempts()
    {
      $this->assertRecordsCount(0);

      $failing_job = new Failing([ 'attempts' => 3 ]);
      $this->assertEquals(3, $failing_job->getAttempts());

      $this->assertEquals(1, $this->dispatcher->dispatch($failing_job));

      // First attempt
      $next_in_line = $this->dispatcher->getQueue()->nextInLine();
      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $this->dispatcher->getQueue()->execute($next_in_line);

      $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);
      $this->assertEquals('Built to fail!', $this->last_failure_message);

      $this->assertRecordsCount(1);
      $this->assertAttempts(1, $next_in_line->getQueueId());

      // Second attempt
      $next_in_line = $this->dispatcher->getQueue()->nextInLine();
      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $this->dispatcher->getQueue()->execute($next_in_line);
      $this->assertAttempts(2, $next_in_line->getQueueId());

      // Third attempt
      $next_in_line = $this->dispatcher->getQueue()->nextInLine();
      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $this->dispatcher->getQueue()->execute($next_in_line);
      $this->assertAttempts(null, $next_in_line->getQueueId());
      $this->assertNull($this->dispatcher->getQueue()->nextInLine());
    }
  }