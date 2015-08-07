<?php

  namespace ActiveCollab\JobsQueue\Test;

  use ActiveCollab\JobsQueue\Dispatcher;
  use ActiveCollab\JobsQueue\Queue\MySqlQueue;
  use ActiveCollab\JobsQueue\Test\Jobs\Inc;
  use mysqli;

  class MySqlQueueTest extends TestCase
  {
    /**
     * @var mysqli
     */
    private $link;

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

      $this->link = new \MySQLi('localhost', 'root', '', 'activecollab_jobs_queue_test');
      $this->link->query('DROP TABLE IF EXISTS `' . MySqlQueue::TABLE_NAME . '`');

      $this->dispatcher = new Dispatcher(new MySqlQueue($this->link));

      $this->assertCount(0, $this->dispatcher->getQueue());
    }

    /**
     * Tear down test environment
     */
    public function tearDown()
    {
      $this->link->query('DROP TABLE IF EXISTS `' . MySqlQueue::TABLE_NAME . '`');
      $this->link->close();

      parent::tearDown();
    }

    /**
     * Test if job queue table is prepared for testing
     */
    public function testJobsQueueTableIsCreated()
    {
      $result = $this->link->query('SHOW TABLES');
      $this->assertEquals(1, $result->num_rows);
      $this->assertEquals(MySqlQueue::TABLE_NAME, $result->fetch_assoc()['Tables_in_activecollab_jobs_queue_test']);
    }

    /**
     * Check number of records in memories table
     *
     * @param integer $expected
     */
    private function assertRecordsCount($expected)
    {
      $result = $this->link->query('SELECT COUNT(`id`) AS "record_count" FROM `' . MySqlQueue::TABLE_NAME . '`');
      $this->assertEquals(1, $result->num_rows);
      $this->assertEquals($expected, (integer) $result->fetch_assoc()['record_count']);
    }
  }