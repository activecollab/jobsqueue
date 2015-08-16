<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Jobs\Job;
  use ActiveCollab\JobsQueue\Queue\MySql;
  use ActiveCollab\JobsQueue\Test\Jobs\Inc;
  use DateTime;

  /**
   * @package ActiveCollab\JobsQueue\Test
   */
  class MySqlQueueTest extends AbstractMySqlQueueTest
  {
    /**
     * Test if job queue table is prepared for testing
     */
    public function testJobsQueueTableIsCreated()
    {
      $result = $this->link->query('SHOW TABLES');
      $this->assertEquals(2, $result->num_rows);
      $this->assertEquals(MySql::TABLE_NAME, $result->fetch_assoc()['Tables_in_activecollab_jobs_queue_test']);
    }

    /**
     * Test jobs are added to the queue
     */
    public function testJobsAreAddedToTheQueue()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));
      $this->assertEquals(2, $this->dispatcher->dispatch(new Inc([ 'number' => 456 ])));
      $this->assertEquals(3, $this->dispatcher->dispatch(new Inc([ 'number' => 789 ])));

      $this->assertRecordsCount(3);
    }

    /**
     * Make sure that full job class is recorded
     */
    public function testFullJobClassIsRecorded()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('type', $row);
      $this->assertEquals('ActiveCollab\JobsQueue\Test\Jobs\Inc', $row['type']);
    }

    /**
     * Test if priority is properly set
     */
    public function testPriorityIsProperlySetFromData()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123, 'priority' => Job::HAS_HIGHEST_PRIORITY ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('priority', $row);
      $this->assertEquals((string) Job::HAS_HIGHEST_PRIORITY, $row['priority']);
    }

    /**
     * Test job data is properly serialized to JSON
     */
    public function testJobDataIsSerializedToJson()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('data', $row);
      $this->assertStringStartsWith('{', $row['data']);
      $this->assertStringEndsWith('}', $row['data']);

      $decoded_data = json_decode($row['data'], true);
      $this->assertInternalType('array', $decoded_data);

      $this->assertArrayHasKey('number', $decoded_data);
      $this->assertEquals(123, $decoded_data['number']);
      $this->assertArrayHasKey('priority', $decoded_data);
      $this->assertEquals(Job::NOT_A_PRIORITY, $decoded_data['priority']);
    }

    /**
     * Test if new jobs are instantly available
     */
    public function testNewJobsAreAvailableInstantly()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('available_at', $row);
      $this->assertEquals(time(), strtotime($row['available_at']));
    }

    /**
     * Test new jobs can be delayed by a specified number of seconds
     */
    public function testNewJobsCanBeDelayed()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123, 'delay' => 5 ])));

      /** @var DateTime $available_at */
      $available_at = $this->connection->executeFirstCell('SELECT `available_at` FROM `' . MySql::TABLE_NAME . '` WHERE `id` = ?', 1);

      $this->assertInstanceOf('DateTime', $available_at);
      $this->assertGreaterThan(time(), $available_at->getTimestamp());
    }

    /**
     * Test if we can use first_attempt_delay to set a delay of the first attempt
     */
    public function testNewJobsCanBeDelayedWithFirstAttemptExecutedNow()
    {
      $inc_job_with_no_first_attempt_delay = new Inc([
        'number' => 123,
        'delay' => 5,
        'first_attempt_delay' => 0
      ]);

      $this->assertEquals(5, $inc_job_with_no_first_attempt_delay->getDelay());
      $this->assertEquals(0, $inc_job_with_no_first_attempt_delay->getFirstJobDelay());

      $this->assertEquals(1, $this->dispatcher->dispatch($inc_job_with_no_first_attempt_delay));

      /** @var DateTime $available_at */
      $available_at = $this->connection->executeFirstCell('SELECT `available_at` FROM `' . MySql::TABLE_NAME . '` WHERE `id` = ?', 1);

      $this->assertInstanceOf('DateTime', $available_at);
      $this->assertLessThanOrEqual(time(), $available_at->getTimestamp());
    }

    /**
     * Test that jobs are not reserved by default
     */
    public function testJobsAreNotReservedByDefault()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('reservation_key', $row);
      $this->assertNull($row['reservation_key']);

      $this->assertArrayHasKey('reserved_at', $row);
      $this->assertNull($row['reserved_at']);
    }

    /**
     * Test that jobs start with zero attempts
     */
    public function testAttemptsAreZeroByDefault()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $result = $this->link->query('SELECT * FROM `' . MySql::TABLE_NAME . '` WHERE id = 1');
      $this->assertInstanceOf('mysqli_result', $result);
      $this->assertEquals(1, $result->num_rows);

      $row = $result->fetch_assoc();

      $this->assertArrayHasKey('attempts', $row);
      $this->assertEquals('0', $row['attempts']);
    }

    /**
     * Test next in line when no priority is set (FIFO)
     */
    public function testNextInLineReturnsNullOnNoJobs()
    {
      $this->assertRecordsCount(0);
      $this->assertNull($this->dispatcher->getQueue()->nextInLine());
    }

    /**
     * Test next in line when no priority is set (FIFO)
     */
    public function testNextInLine()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));
      $this->assertEquals(2, $this->dispatcher->dispatch(new Inc([ 'number' => 456 ])));

      $this->assertRecordsCount(2);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());
    }

    /**
     * Test if queue instance is properly set
     */
    public function testJobGetsQueueProperlySet()
    {
      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));
      $this->assertRecordsCount(1);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Queue\MySql', $next_in_line->getQueue());
      $this->assertEquals(1, $next_in_line->getQueueId());
    }

    /**
     * Test priority tasks are front in line
     */
    public function testPriorityJobsAreFrontInLine()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));
      $this->assertEquals(2, $this->dispatcher->dispatch(new Inc([ 'number' => 456, 'priority' => Job::HAS_PRIORITY ])));
      $this->assertEquals(3, $this->dispatcher->dispatch(new Inc([ 'number' => 789, 'priority' => Job::HAS_PRIORITY ])));

      $this->assertRecordsCount(3);

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
      $this->assertEquals(2, $next_in_line->getQueueId());
    }

    /**
     * Test if job execution removes it from the queue
     */
    public function testExecuteJobRemovesItFromQueue()
    {
      $this->assertRecordsCount(0);

      $this->assertEquals(1, $this->dispatcher->dispatch(new Inc([ 'number' => 123 ])));

      $next_in_line = $this->dispatcher->getQueue()->nextInLine();

      $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
      $this->assertEquals(1, $next_in_line->getQueueId());

      $job_execution_result = $this->dispatcher->getQueue()->execute($next_in_line);
      $this->assertEquals(124, $job_execution_result);

      $this->assertRecordsCount(0);
    }
  }