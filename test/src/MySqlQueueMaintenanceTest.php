<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Queue\MySql;
  use ActiveCollab\JobsQueue\Test\Jobs\Failing;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class MySqlQueueMaintenanceTest extends AbstractMySqlQueueTest
  {
    /**
     * Test clean-up method removes failed job logs older than 7 days
     */
    public function testCleanUpJobsFailedMoreThan7DaysAgo()
    {
      $this->assertRecordsCount(0);

      for ($i = 1; $i <= 5; $i++) {
        $this->assertEquals($i, $this->dispatcher->dispatch(new Failing()));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals($i, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Failing', $this->last_failed_job);
        $this->assertEquals('Built to fail!', $this->last_failure_message);
      }

      $this->assertEquals(0, $this->dispatcher->getQueue()->count());
      $this->assertEquals(5, $this->dispatcher->getQueue()->countFailed());

      // Lets age a record a bit
      $this->connection->execute('UPDATE `' . MySql::TABLE_NAME_FAILED . '` SET `failed_at` = ? WHERE `id` = ?', date('Y-m-d H:i:s', strtotime('-14 days')), 1);

      // Check the queue, clean up and check again
      $this->assertEquals(5, $this->dispatcher->getQueue()->countFailed());
      $this->dispatcher->getQueue()->cleanUp();
      $this->assertEquals(4, $this->dispatcher->getQueue()->countFailed());
    }
  }