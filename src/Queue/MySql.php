<?php

  namespace ActiveCollab\JobsQueue\Queue;

  use ActiveCollab\JobsQueue\Jobs\Job;
  use Exception, RuntimeException;

  /**
   * @package ActiveCollab\JobsQueue\Queue
   */
  class MySql implements Queue
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
        $this->query("CREATE TABLE IF NOT EXISTS `" . self::TABLE_NAME . "` (
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
      if ($statement = $this->link->prepare('INSERT INTO `' . self::TABLE_NAME . '` (type, priority, data, available_at) VALUES (?, ?, ?, ?)')) {
        $job_type = get_class($job);
        $job_data = $job->getData();
        $encoded_job_data = json_encode($job);
        $timestamp = date('Y-m-d H:i:s', time() + $job->getDelay());

        $statement->bind_param('ssss', $job_type, $job_data['priority'], $encoded_job_data, $timestamp);
        $statement->execute();

        $insert_id = $this->link->insert_id;

        $statement->close();

        return $insert_id;
      } else {
        throw new RuntimeException('Failed to prepare statement. Reason: ' . $this->link->error);
      }
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
          $this->deleteByJobId($job_id);
        } else {
          $this->prepreForNextAttempt($job_id, $job->getDelay());
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
      $statement = $this->link->prepare('SELECT `id`, `type`, `data` FROM `' . self::TABLE_NAME . '` WHERE `id` = ?');
      $statement->bind_param('i', $job_id);
      $statement->execute();

      if ($result = $statement->get_result()) {
        if ($result->num_rows) {
          $result = $result->fetch_assoc();

          $type = $result['type'];
          $serialized_data = $result['data'];

          /** @var Job $job */
          $job = new $type(json_decode($serialized_data, true));
          $job->setQueueId((integer) $result['id']);

          $statement->close();

          return $job;
        }

        $statement->close();

        return null;
      } else {
        throw new RuntimeException('Failed to query job details. MySQL said: ' . $this->link->error);
      }
    }

    /**
     * Return number of previous attempts that we recorded for the given job
     *
     * @param  integer $job_id
     * @return integer
     */
    private function getPreviousAttemptsByJobId($job_id)
    {
      $statement = $this->link->prepare('SELECT `attempts` FROM `' . self::TABLE_NAME . '` WHERE `id` = ?');
      $statement->bind_param('i', $job_id);
      $statement->execute();

      if ($result = $statement->get_result()) {
        $statement->close();

        if ($result->num_rows) {
          return $result->fetch_assoc()['attempts'];
        }
      }

      return 0;
    }

    /**
     * Increase number of attempts by job ID
     *
     * @param integer $job_id
     * @param integer $delay
     */
    public function prepreForNextAttempt($job_id, $delay = 0)
    {
      $timestamp = date('Y-m-d H:i:s', time() + $delay);

      $statement = $this->link->prepare('UPDATE `' . self::TABLE_NAME . '` SET `available_at` = ?, `reservation_key` = NULL, `reserved_at` = NULL, `attempts` = `attempts` + 1 WHERE `id` = ?');
      $statement->bind_param('si', $timestamp, $job_id);
      $statement->execute();
      $statement->close();
    }

    /**
     * Delete by job ID
     *
     * @param integer $job_id
     */
    public function deleteByJobId($job_id)
    {
      $statement = $this->link->prepare('DELETE FROM `' . self::TABLE_NAME . '` WHERE `id` = ?');
      $statement->bind_param('i', $job_id);
      $statement->execute();
      $statement->close();
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

      $statement = $this->link->prepare('SELECT `id` FROM `' . self::TABLE_NAME . '` WHERE `reserved_at` IS NULL AND `available_at` <= ? ORDER BY `priority` DESC, `id` LIMIT 0, 1');
      $statement->bind_param('s', $timestamp);
      $statement->execute();

      if ($result = $statement->get_result()) {
        $statement->close();

        if ($result->num_rows) {
          $job_id = (integer) $result->fetch_assoc()['id'];
          $reservation_key = $this->prepareNewReservationKey();

          $statement = $this->link->prepare('UPDATE `' . self::TABLE_NAME . '` SET `reservation_key` = ?, `reserved_at` = ? WHERE `id` = ? AND `reservation_key` IS NULL');
          $statement->bind_param('ssi', $reservation_key, $timestamp, $job_id);
          $statement->execute();

          $affected_rows = $this->link->affected_rows;
          $statement->close();

          // Simple concurency control. We reserve the ID with first query, and then update it with reservation code. If a different job
          // reserved that particular job between the two queries, we will not be able to update it because its reservation key will be
          // updated and we will have 0 for affected rows
          if ($affected_rows === 1) {
            return $job_id;
          }
        }

        return null;
      } else {
        throw new RuntimeException('Failed to find next job. MySQL said: ' . $this->link->error);
      }
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

        $statement = $this->link->prepare('SELECT COUNT(`id`) AS "record_count" FROM `' . self::TABLE_NAME . '` WHERE `reservation_key` = ?');
        $statement->bind_param('s', $reservation_key);
        $statement->execute();

        if ($result = $statement->get_result()) {
          $key_exists = (boolean) $result->fetch_assoc()['record_count'];

          $statement->close();
        } else {
          throw new RuntimeException('Failed to check uniqueness of reservation key');
        }
      } while($key_exists);

      return $reservation_key;
    }

    /**
     * @return int
     */
    public function count()
    {
      if ($result = $this->link->query('SELECT COUNT(`id`) AS "record_count" FROM `' . self::TABLE_NAME . '`')) {
        return (integer) $result->fetch_assoc()['record_count'];
      } else {
        throw new RuntimeException('Failed to count jobs in jobs queue. MySQL said: ' . $this->link->error);
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