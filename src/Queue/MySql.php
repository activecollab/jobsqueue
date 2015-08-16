<?php

  namespace ActiveCollab\JobsQueue\Queue;

  use ActiveCollab\JobsQueue\Jobs\Job;
  use Exception, RuntimeException;
  use ActiveCollab\DatabaseConnection\Connection;

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
          PRIMARY KEY (`id`),
          KEY `type` (`type`),
          KEY `reserved_at` (`failed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
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

      $this->connection->execute('INSERT INTO `' . self::TABLE_NAME . '` (type, priority, data, available_at) VALUES (?, ?, ?, ?)', get_class($job), $job_data['priority'], json_encode($job_data), date('Y-m-d H:i:s', time() + $job->getFirstJobDelay()));
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

        if ($job_id = $job->getQueueId()) {
          $this->deleteByJobId($job_id);
        }

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
          $this->logFailedJob($job);
        } else {
          $this->prepareForNextAttempt($job_id, $job->getDelay());
        }
      }

      if ($this->on_job_failure && is_callable($this->on_job_failure)) {
        call_user_func($this->on_job_failure, $job, $reason);
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
      if ($result = $this->connection->executeFirstRow('SELECT `id`, `type`, `data` FROM `' . self::TABLE_NAME . '` WHERE `id` = ?', $job_id)) {
        $type = $result['type'];

        /** @var Job $job */
        $job = new $type(json_decode($result['data'], true));
        $job->setQueue($this, (integer) $result['id']);

        return $job;
      }

      return null;
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
     * @param integer $delay
     */
    public function prepareForNextAttempt($job_id, $delay = 0)
    {
      $this->connection->execute('UPDATE `' . self::TABLE_NAME . '` SET `available_at` = ?, `reservation_key` = NULL, `reserved_at` = NULL, `attempts` = `attempts` + 1 WHERE `id` = ?', date('Y-m-d H:i:s', time() + $delay), $job_id);
    }

    /**
     * Log failed job
     *
     * @param Job $job
     */
    public function logFailedJob(Job $job)
    {
      $this->connection->execute('INSERT INTO `' . self::TABLE_NAME_FAILED . '` (`type`, `data`, `failed_at`) VALUES (?, ?, ?)', get_class($job), serialize($job->getData()), date('Y-m-d H:i:s'));
      $this->deleteByJobId($job->getQueueId());
    }

    /**
     * Delete by job ID
     *
     * @param integer $job_id
     */
    public function deleteByJobId($job_id)
    {
      $this->connection->execute('DELETE FROM `' . self::TABLE_NAME . '` WHERE `id` = ?', $job_id);
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
     * Reserve next job ID
     *
     * @return int|null
     */
    public function reserveNextJob()
    {
      $timestamp = date('Y-m-d H:i:s');

      if ($job_id = $this->connection->executeFirstCell('SELECT `id` FROM `' . self::TABLE_NAME . '` WHERE `reserved_at` IS NULL AND `available_at` <= ? ORDER BY `priority` DESC, `id` LIMIT 0, 1', $timestamp)) {
        $reservation_key = $this->prepareNewReservationKey();

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
     * @var callable
     */
    private $on_job_failure;

    /**
     * What to do when job fails
     *
     * @param callable|null $callback
     */
    public function onJobFailure(callable $callback = null)
    {
      $this->on_job_failure = $callback;
    }
  }