<?php

  namespace ActiveCollab\JobsQueue\Queue;

  use ActiveCollab\JobsQueue\Jobs\Job;
  use RuntimeException;

  /**
   * @package ActiveCollab\JobsQueue\Queue
   */
  class MySqlQueue implements Queue
  {
    const TABLE_NAME = 'jobs_queue';

    /**
     * @var \MySQLi
     */
    private $link;

    /**
     * @param \MySQLi   $link
     * @param bool|true $create_table_if_missing
     */
    public function __construct(\MySQLi &$link, $create_table_if_missing = true)
    {
      $this->link = $link;

      if ($create_table_if_missing) {
        $this->query("CREATE TABLE `" . self::TABLE_NAME . "` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `type` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
          `priority` int(10) unsigned DEFAULT '0',
          `data` text CHARACTER SET utf8 NOT NULL,
          `is_locked` tinyint(1) unsigned DEFAULT '0',
          `retries` smallint(6) DEFAULT '0',
          PRIMARY KEY (`id`),
          KEY `type` (`type`),
          KEY `priority` (`priority`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
      }
    }

    /**
     * Query database
     *
     * @param  string              $sql
     * @return bool|\mysqli_result
     * @throws \Exception
     */
    private function query($sql)
    {
      $query_result = $this->link->query($sql);

      // Handle query error
      if ($query_result === false && $this->link->errno) {
        throw new \Exception($this->link->error . '. SQL: ' . $sql);
      }

      return $query_result;
    }
    
    /**
     * Add a job to the queue
     *
     * @param  Job     $job
     * @return integer
     */
    public function enqueue(Job $job)
    {
      if ($statement = $this->link->prepare('INSERT INTO `' . self::TABLE_NAME . '` (type, priority, data) VALUES (?, ?, ?)')) {
        $job_type = get_class($job);
        $job_data = $job->getData();
        $encoded_job_data = json_encode($job);

        $statement->bind_param('sss', $job_type, $job_data['priority'], $encoded_job_data);
        $statement->execute();

        return $this->link->insert_id;
      } else {
        throw new RuntimeException('Failed to prepare statement. Reason: ' . $this->link->error);
      }
    }

    /**
     * Run job now (sync, waits for a response)
     *
     * @param  Job   $job
     * @return mixed
     */
    public function run(Job $job)
    {
      return $job->run();
    }

    /**
     * @return int
     */
    public function count()
    {
      if ($result = $this->link->query('SELECT COUNT(`id`) AS "record_count" FROM `' . self::TABLE_NAME . '`')) {
        return (integer) $result->fetch_assoc()['record_count'];
      } else {
        throw new RuntimeException('Failed to count jobs in jobs queue');
      }
    }
  }