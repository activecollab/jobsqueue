<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Test\Jobs\Failing;
  use ActiveCollab\JobsQueue\Jobs\Job;
  use ActiveCollab\JobsQueue\Test\Jobs\Inc;
  use Exception;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class ExtractPropertyTest extends AbstractMySqlQueueTest
  {
    /**
     * Test to confirm that priority is extracted field by default
     */
    public function testPriorityIsExtractedByDefault()
    {
      $job_id = $this->dispatcher->dispatch(new Inc([ 'number' => 12, 'priority' => Job::HAS_PRIORITY ]));
      $this->assertEquals(1, $job_id);

      $job_row = $this->connection->executeFirstRow('SELECT * FROM `jobs_queue` WHERE `id` = ?', $job_id);

      $this->assertEquals(Job::HAS_PRIORITY, (integer) $job_row['priority']);
    }

    /**
     * @expectedException \ActiveCollab\DatabaseConnection\Exception\QueryException
     */
    public function testExceptionBecauseFieldDoesNotExist()
    {
      $this->dispatcher->getQueue()->extractPropertyToField('number');

      $this->dispatcher->dispatch(new Inc([ 'number' => 12 ]));
    }

    /**
     * Test if property is extracted to field properly
     */
    public function testExtractPropertyToField()
    {
      $this->connection->execute("ALTER TABLE `jobs_queue` ADD `number` INT(10) UNSIGNED NULL DEFAULT '0' AFTER `type`");

      $this->dispatcher->getQueue()->extractPropertyToField('number');

      $job_id = $this->dispatcher->dispatch(new Inc([ 'number' => 12 ]));
      $this->assertEquals(1, $job_id);

      $job_row = $this->connection->executeFirstRow('SELECT * FROM `jobs_queue` WHERE `id` = ?', $job_id);

      $this->assertEquals(12, (integer) $job_row['number']);
    }
  }