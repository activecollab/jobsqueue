<?php

  namespace ActiveCollab\JobsQueue\Queue;

  use ActiveCollab\JobsQueue\Jobs\Job;
  use ActiveCollab\DatabaseConnection\Connection;
  use Exception;
  use RuntimeException;

  /**
   * @package ActiveCollab\JobsQueue\Queue
   */
  class MySql implements Queue
  {
    const TABLE_NAME = 'jobs_queue';
    const TABLE_NAME_FAILED = 'jobs_queue_failed';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     * @param bool|true  $create_tables_if_missing
     */
    public function __construct(Connection &$connection, $create_tables_if_missing = true)
    {
      $this->connection = $connection;

      if ($create_tables_if_missing) {
        $this->connection->execute("CREATE TABLE IF NOT EXISTS `" . self::TABLE_NAME . "` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `type` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
          `priority` int(10) unsigned DEFAULT '0',
          `data` text CHARACTER SET utf8 NOT NULL,
          `available_at` datetime DEFAULT NULL,
          `reservation_key` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `reserved_at` datetime DEFAULT NULL,
          `attempts` smallint(6) DEFAULT '0',
          PRIMARY KEY (`id`),
          UNIQUE KEY `reservation_key` (`reservation_key`),
          KEY `type` (`type`),
          KEY `priority` (`priority`),
          KEY `reserved_at` (`reserved_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $this->connection->execute("CREATE TABLE IF NOT EXISTS `" . self::TABLE_NAME_FAILED . "` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `type` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
          `data` text CHARACTER SET utf8 NOT NULL,
          `failed_at` datetime DEFAULT NULL,
          `reason` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
          PRIMARY KEY (`id`),
          KEY `type` (`type`),
          KEY `failed_at` (`failed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
      }
    }

    /**
     * @var array
     */
    private $extract_properties_to_fields = [ 'priority' ];

    /**
     * Extract property value to field value
     *
     * @param string $property
     */
    public function extractPropertyToField($property)
    {
      if (!in_array($property, $this->extract_properties_to_fields)) {
        $this->extract_properties_to_fields[] = $property;
      }
    }
    
    /**
     * Add a job to the queue
     *
     * @param  Job     $job
     * @return integer
     */
    public function enqueue(Job $job)
    {
      $job_data = $job->getData();

      $extract = [];

      foreach ($this->extract_properties_to_fields as $property) {
        $extract['`' . $property . '`'] = $this->connection->escapeValue($job->getData()[$property]);
      }

      $extract_fields = empty($extract) ? '' : ', ' . implode(', ', array_keys($extract));
      $extract_values = empty($extract) ? '' : ', ' . implode(', ', $extract);

      $this->connection->execute('INSERT INTO `' . self::TABLE_NAME . '` (`type`, `data`, `available_at`' . $extract_fields . ') VALUES (?, ?, ?' . $extract_values . ')', get_class($job), json_encode($job_data), date('Y-m-d H:i:s', time() + $job->getFirstJobDelay()));
      return $this->connection->lastInsertId();
    }

    /**
     * Run job now (sync, waits for a response)
     *
     * @param  Job              $job
     * @return mixed
     * @throws RuntimeException
     */
    public function execute(Job $job)
    {
      try {
        $result = $job->execute();
        $this->deleteJob($job);
        return $result;
      } catch (\Exception $e) {
        $this->failJob($job, $e);
      }

      return null;
    }

    /**
     * Handle a job failure (attempts, removal from queue, exception handling etc)
     *
     * @param Job            $job
     * @param Exception|null $reason
     */
    private function failJob(Job $job, Exception $reason = null)
    {
      if ($job_id = $job->getQueueId()) {
        $previous_attempts = $this->getPreviousAttemptsByJobId($job_id);

        if (($previous_attempts + 1) >= $job->getAttempts()) {
          $this->logFailedJob($job, ($reason instanceof Exception ? $reason->getMessage() : ''));
        } else {
          $this->prepareForNextAttempt($job_id, $previous_attempts, $job->getDelay());
        }
      }

      if (!empty($this->on_job_failure)) {
        foreach ($this->on_job_failure as $callback) {
          call_user_func($callback, $job, $reason);
        }
      }
    }

    /**
     * Return job by ID
     *
     * @param  integer  $job_id
     * @return Job|null
     */
    public function getJobById($job_id)
    {
      if ($row = $this->connection->executeFirstRow('SELECT `id`, `type`, `data` FROM `' . self::TABLE_NAME . '` WHERE `id` = ?', $job_id)) {
        try {
          return $this->getJobFromRow($row);
        } catch (Exception $e) {
          $this->connection->transact(function() use ($row, $e) {
            $this->connection->execute('INSERT INTO `' . self::TABLE_NAME_FAILED . '` (`type`, `data`, `failed_at`, `reason`) VALUES (?, ?, ?, ?)', $row['type'], $row['data'], date('Y-m-d H:i:s'), $e->getMessage());
            $this->connection->execute('DELETE FROM `' . self::TABLE_NAME . '` WHERE `id` = ?', $row['id']);
          });
        }
      }

      return null;
    }

    /**
     * Hydrate a job based on row data
     *
     * @param  array $row
     * @return Job
     */
    private function getJobFromRow(array $row)
    {
      $type = $row['type'];

      $data = json_decode($row['data'], true);

      if (json_last_error()) {
        $error_message = 'Failed to parse JSON';

        if (function_exists('json_last_error_msg')) {
          $error_message .= '. Reason: ' . json_last_error_msg();
        }

        throw new RuntimeException($error_message);
      }

      /** @var Job $job */
      $job = new $type($data);
      $job->setQueue($this, (integer) $row['id']);

      return $job;
    }

    /**
     * Return number of previous attempts that we recorded for the given job
     *
     * @param  integer $job_id
     * @return integer
     */
    private function getPreviousAttemptsByJobId($job_id)
    {
      return (integer) $this->connection->executeFirstCell('SELECT `attempts` FROM `' . self::TABLE_NAME . '` WHERE `id` = ?', $job_id);
    }

    /**
     * Increase number of attempts by job ID
     *
     * @param integer $job_id
     * @param integer $previous_attempts
     * @param integer $delay
     */
    public function prepareForNextAttempt($job_id, $previous_attempts, $delay = 0)
    {
      $this->connection->execute('UPDATE `' . self::TABLE_NAME . '` SET `available_at` = ?, `reservation_key` = NULL, `reserved_at` = NULL, `attempts` = ? WHERE `id` = ?', date('Y-m-d H:i:s', time() + $delay), $previous_attempts + 1, $job_id);
    }

    /**
     * Log failed job and delete it from the main queue
     *
     * @param Job    $job
     * @param string $reason
     */
    public function logFailedJob(Job $job, $reason)
    {
      $this->connection->transact(function() use ($job, $reason) {
        $this->connection->execute('INSERT INTO `' . self::TABLE_NAME_FAILED . '` (`type`, `data`, `failed_at`, `reason`) VALUES (?, ?, ?, ?)', get_class($job), serialize($job->getData()), date('Y-m-d H:i:s'), $reason);
        $this->deleteJob($job);
      });
    }

    /**
     * Delete a job
     *
     * @param Job $job
     */
    public function deleteJob($job)
    {
      if ($job_id = $job->getQueueId()) {
        $this->connection->execute('DELETE FROM `' . self::TABLE_NAME . '` WHERE `id` = ?', $job_id);
      }
    }

    /**
     * Return Job that is next in line to be executed
     *
     * @return Job|null
     */
    public function nextInLine()
    {
      if ($job_id = $this->reserveNextJob()) {
        return $this->getJobById($job_id);
      } else {
        return null;
      }
    }

    /**
     * @var callable|null
     */
    private $on_reservation_key_ready;

    /**
     * Callback that is called when reservation key is prepared for a particular job, but not yet set
     *
     * This callback is useful for testing job snatching when we have multiple workers
     *
     * @param callable|null $callback
     */
    public function onReservationKeyReady(callable $callback = null)
    {
      if ($callback === null || is_callable($callback)) {
        $this->on_reservation_key_ready = $callback;
      } else {
        throw new \InvalidArgumentException('Callable or NULL expected');
      }
    }

    /**
     * Reserve next job ID
     *
     * @return int|null
     */
    public function reserveNextJob()
    {
      $timestamp = date('Y-m-d H:i:s');

      if ($job_id = $this->connection->executeFirstCell('SELECT `id` FROM `' . self::TABLE_NAME . '` WHERE `reserved_at` IS NULL AND `available_at` <= ? ORDER BY `priority` DESC, `id` LIMIT 0, 1', $timestamp)) {
        $reservation_key = $this->prepareNewReservationKey();

        if ($this->on_reservation_key_ready) {
          call_user_func($this->on_reservation_key_ready, $job_id, $reservation_key);
        }

        $this->connection->execute('UPDATE `' . self::TABLE_NAME . '` SET `reservation_key` = ?, `reserved_at` = ? WHERE `id` = ? AND `reservation_key` IS NULL', $reservation_key, $timestamp, $job_id);

        if ($this->connection->affectedRows() === 1) {
          return $job_id;
        }
      }

      return null;
    }

    /**
     * Prepare and return a new reservation key
     *
     * @return string
     */
    private function prepareNewReservationKey()
    {
      do {
        $reservation_key = sha1(microtime());
      } while($this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::TABLE_NAME . '` WHERE `reservation_key` = ?', $reservation_key));

      return $reservation_key;
    }

    /**
     * @return int
     */
    public function count()
    {
      return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::TABLE_NAME . '`');
    }

    /**
     * @param  string  $type1
     * @return integer
     */
    public function countByType($type1)
    {
      if (func_num_args()) {
        return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::TABLE_NAME . '` WHERE `type` IN ?', func_get_args());
      } else {
        return 0;
      }
    }

    /**
     * @return integer
     */
    public function countFailed()
    {
      return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::TABLE_NAME_FAILED . '`');
    }

    /**
     * @param  string  $type1
     * @return integer
     */
    public function countFailedByType($type1)
    {
      if (func_num_args()) {
        return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::TABLE_NAME_FAILED . '` WHERE `type` IN ?', func_get_args());
      } else {
        return 0;
      }
    }

    /**
     * Check stuck jobs
     */
    public function checkStuckJobs()
    {
      if ($rows = $this->connection->execute('SELECT * FROM `' . self::TABLE_NAME . '` WHERE `reserved_at` < ?', date('Y-m-d H:i:s', time() - 3600))) {
        foreach ($rows as $row) {
          $this->failJob($this->getJobFromRow($row), new RuntimeException('Job stuck for more than an hour'));
        }
      }
    }

    /**
     * Clean up the queue
     */
    public function cleanUp()
    {
      $this->connection->execute('DELETE FROM `' . self::TABLE_NAME_FAILED . '` WHERE `failed_at` < ?', date('Y-m-d H:i:s', strtotime('-7 days')));
    }

    /**
     * @var callable[]
     */
    private $on_job_failure = [];

    /**
     * What to do when job fails
     *
     * @param callable|null $callback
     */
    public function onJobFailure(callable $callback = null)
    {
      $this->on_job_failure[] = $callback;
    }
  }