<?php

namespace ActiveCollab\JobsQueue\Queue;

use ActiveCollab\DatabaseConnection\ConnectionInterface;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\Signals\SignalInterface;
use Exception;
use LogicException;
use InvalidArgumentException;
use RuntimeException;

/**
 * @package ActiveCollab\JobsQueue\Queue
 */
class MySqlQueue implements QueueInterface
{
    const TABLE_NAME = 'jobs_queue';
    const TABLE_NAME_FAILED = 'jobs_queue_failed';

    /**
     * @var ConnectionInterface
     */
    private $connection;

    /**
     * @param ConnectionInterface $connection
     * @param bool|true           $create_tables_if_missing
     */
    public function __construct(ConnectionInterface &$connection, $create_tables_if_missing = true)
    {
        $this->connection = $connection;

        if ($create_tables_if_missing) {
            $this->connection->execute("CREATE TABLE IF NOT EXISTS `" . self::TABLE_NAME . "` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `type` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
                `channel` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT 'main',
                `priority` int(10) unsigned DEFAULT '0',
                `data` longtext CHARACTER SET utf8 NOT NULL,
                `available_at` datetime DEFAULT NULL,
                `reservation_key` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `reserved_at` datetime DEFAULT NULL,
                `attempts` smallint(6) DEFAULT '0',
                `process_id` int(10) unsigned DEFAULT '0',
                PRIMARY KEY (`id`),
                UNIQUE KEY `reservation_key` (`reservation_key`),
                KEY `type` (`type`),
                KEY `channel` (`channel`),
                KEY `priority` (`priority`),
                KEY `reserved_at` (`reserved_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $this->connection->execute("CREATE TABLE IF NOT EXISTS `" . self::TABLE_NAME_FAILED . "` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `type` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
                `channel` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT 'main',
                `data` longtext CHARACTER SET utf8 NOT NULL,
                `failed_at` datetime DEFAULT NULL,
                `reason` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                KEY `type` (`type`),
                KEY `channel` (`channel`),
                KEY `failed_at` (`failed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
    }

    /**
     * @var array
     */
    private $extract_properties_to_fields = ['priority'];

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
     * @param  JobInterface $job
     * @param  string       $channel
     * @return integer
     */
    public function enqueue(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
    {
        $job_data = $job->getData();

        $extract = [];

        foreach ($this->extract_properties_to_fields as $property) {
            $extract[ '`' . $property . '`' ] = $this->connection->escapeValue($job->getData()[ $property ]);
        }

        $extract_fields = empty($extract) ? '' : ', ' . implode(', ', array_keys($extract));
        $extract_values = empty($extract) ? '' : ', ' . implode(', ', $extract);

        $this->connection->execute('INSERT INTO `' . self::TABLE_NAME . '` (`type`, `channel`, `data`, `available_at`' . $extract_fields . ') VALUES (?, ?, ?, ?' . $extract_values . ')', get_class($job), $channel, json_encode($job_data), date('Y-m-d H:i:s', time() + $job->getFirstJobDelay()));

        return $this->connection->lastInsertId();
    }

    /**
     * Run job now (sync, waits for a response)
     *
     * @param  JobInterface     $job
     * @param  string           $channel
     * @return mixed
     * @throws RuntimeException
     */
    public function execute(JobInterface $job, $channel = QueueInterface::MAIN_CHANNEL)
    {
        try {
            $result = $job->execute();

            if (!($result instanceof SignalInterface && $result->keepJobInQueue())) {
                $this->deleteJob($job);
            }

            return $result;
        } catch (\Exception $e) {
            $this->failJob($job, $e);
        }

        return null;
    }

    /**
     * Return a total number of jobs that are in the given channel
     *
     * @param  string  $channel
     * @return integer
     */
    public function countByChannel($channel)
    {
        return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::TABLE_NAME . '` WHERE `channel` = ?', $channel);
    }

    /**
     * Return true if there's an active job of the give type with the given properties
     *
     * @param  string     $job_type
     * @param  array|null $properties
     * @return boolean
     */
    public function exists($job_type, array $properties = null)
    {
        if (empty($properties)) {
            return (boolean) $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::TABLE_NAME . '` WHERE `type` = ?', $job_type);
        } else {
            if ($rows = $this->connection->execute('SELECT `data` FROM `' . self::TABLE_NAME . '` WHERE `type` = ?', $job_type)) {
                foreach ($rows as $row) {
                    try {
                        $data = $this->jsonDecode($row['data']);

                        $all_properties_found = true;

                        foreach ($properties as $k => $v) {
                            if (!(array_key_exists($k, $data) && $data[$k] === $v)) {
                                $all_properties_found = false;
                                break;
                            }
                        }

                        if ($all_properties_found) {
                            return true;
                        }
                    } catch (RuntimeException $e) {

                    }
                }
            }

            return false;
        }
    }

    /**
     * Handle a job failure (attempts, removal from queue, exception handling etc)
     *
     * @param JobInterface   $job
     * @param Exception|null $reason
     */
    private function failJob(JobInterface $job, Exception $reason = null)
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
     * @param  integer           $job_id
     * @return JobInterface|null
     */
    public function getJobById($job_id)
    {
        if ($row = $this->connection->executeFirstRow('SELECT `id`, `channel`, `type`, `data` FROM `' . self::TABLE_NAME . '` WHERE `id` = ?', $job_id)) {
            try {
                return $this->getJobFromRow($row);
            } catch (Exception $e) {
                $this->connection->transact(function () use ($row, $e) {
                    $this->connection->execute('INSERT INTO `' . self::TABLE_NAME_FAILED . '` (`type`, `data`, `failed_at`, `reason`) VALUES (?, ?, ?, ?)', $row['type'], $row['data'], date('Y-m-d H:i:s'), $e->getMessage());
                    $this->deleteJobById($row['id']);
                });
            }
        }

        return null;
    }

    /**
     * Hydrate a job based on row data
     *
     * @param  array        $row
     * @return JobInterface
     */
    private function getJobFromRow(array $row)
    {
        $type = $row['type'];

        /** @var Job $job */
        $job = new $type($this->jsonDecode($row['data']));
        $job->setChannel($row['channel']);
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
        return (integer)$this->connection->executeFirstCell('SELECT `attempts` FROM `' . self::TABLE_NAME . '` WHERE `id` = ?', $job_id);
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
     * @param JobInterface $job
     * @param string      $reason
     */
    public function logFailedJob(JobInterface $job, $reason)
    {
        $this->connection->transact(function () use ($job, $reason) {
            $this->connection->execute('INSERT INTO `' . self::TABLE_NAME_FAILED . '` (`type`, `channel`, `data`, `failed_at`, `reason`) VALUES (?, ?, ?, ?, ?)', get_class($job), $job->getChannel(), json_encode($job->getData()), date('Y-m-d H:i:s'), $reason);
            $this->deleteJob($job);
        });
    }

    /**
     * Delete a job
     *
     * @param JobInterface $job
     */
    public function deleteJob($job)
    {
        if ($job_id = $job->getQueueId()) {
            $this->deleteJobById($job_id);
        }
    }

    /**
     * Delete job by ID, used internally
     *
     * @param integer $job_id
     */
    private function deleteJobById($job_id)
    {
        $this->connection->execute('DELETE FROM `' . self::TABLE_NAME . '` WHERE `id` = ?', $job_id);
    }

    /**
     * Return Job that is next in line to be executed
     *
     * @param  string            ...$from_channels
     * @return JobInterface|null
     */
    public function nextInLine()
    {
        if ($job_id = $this->reserveNextJob(func_get_args())) {
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
     * @param  array|null $from_channels
     * @return int|null
     */
    public function reserveNextJob(array $from_channels = null)
    {
        $timestamp = date('Y-m-d H:i:s');
        $channel_conditions = empty($from_channels) ? '' : $this->connection->prepareConditions(['`channel` IN ? AND ', $from_channels]);

        if ($job_ids = $this->connection->executeFirstColumn('SELECT `id` FROM `' . self::TABLE_NAME . "` WHERE {$channel_conditions}`reserved_at` IS NULL AND `available_at` <= ? ORDER BY `priority` DESC, `id` LIMIT 0, 100", $timestamp)) {
            foreach ($job_ids as $job_id) {
                $reservation_key = $this->prepareNewReservationKey();

                if ($this->on_reservation_key_ready) {
                    call_user_func($this->on_reservation_key_ready, $job_id, $reservation_key);
                }

                $this->connection->execute('UPDATE `' . self::TABLE_NAME . '` SET `reservation_key` = ?, `reserved_at` = ? WHERE `id` = ? AND `reservation_key` IS NULL', $reservation_key, $timestamp, $job_id);

                if ($this->connection->affectedRows() === 1) {
                    return $job_id;
                }
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
            $reservation_key = sha1(microtime(true) . mt_rand(10000, 90000));
        } while ($this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::TABLE_NAME . '` WHERE `reservation_key` = ?', $reservation_key));

        return $reservation_key;
    }

    /**
     * Restore failed job by job ID and optionally update job properties
     *
     * @param  mixed        $job_id
     * @param  array|null   $update_data
     * @return JobInterface
     */
    public function restoreFailedJobById($job_id, array $update_data = null)
    {
        $job = null;

        if ($row = $this->connection->executeFirstRow('SELECT `type`, `channel`, `data` FROM `' . self::TABLE_NAME_FAILED . '` WHERE `id` = ?', $job_id)) {
            $this->connection->transact(function () use (&$job, $job_id, $update_data, $row) {
                $job_type = $row['type'];

                if (!class_exists($job_type)) {
                    throw new RuntimeException("Can't restore a job. Type '$job_type' not found");
                }

                $channel = $row['channel'];

                if (empty($channel)) {
                    $channel = QueueInterface::MAIN_CHANNEL;
                }

                if ($row['data']) {
                    if (mb_substr($row['data'], 0, 1) == '{') {
                        $data = $this->jsonDecode($row['data']);
                    } else {
                        $data = unserialize($row['data']);
                    }
                }

                if (empty($data)) {
                    $data = [];
                }

                if ($update_data && is_array($update_data) && count($update_data)) {
                    $data = array_merge($data, $update_data);
                }

                $job = new $job_type($data);

                $this->enqueue($job, $channel);
                $this->connection->execute('DELETE FROM `' . self::TABLE_NAME_FAILED . '` WHERE `id` = ?', $job_id);
            });
        } else {
            throw new RuntimeException("Failed job #{$job_id} not found");
        }

        return $job;
    }

    /**
     * Restore failed jobs by job type
     *
     * @param string     $job_type
     * @param array|null $update_data
     */
    public function restoreFailedJobsByType($job_type, array $update_data = null)
    {
        if ($job_ids = $this->connection->executeFirstColumn('SELECT `id` FROM `' . self::TABLE_NAME_FAILED . '` WHERE `type` LIKE ?', "%$job_type%")) {
            foreach ($job_ids as $job_id) {
                $this->restoreFailedJobById($job_id, $update_data);
            }
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::TABLE_NAME . '`');
    }

    /**
     * @param  string $type1
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
     * @param  string $type1
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
     * Let jobs report that they raised background process
     *
     * @param JobInterface $job
     * @param integer      $process_id
     */
    public function reportBackgroundProcess(JobInterface $job, $process_id)
    {
        if ($job->getQueue() && get_class($job->getQueue()) == get_class($this)) {
            if ($job_id = $job->getQueueId()) {
                if (is_int($process_id) && $process_id > 0) {
                    if ($this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::TABLE_NAME . '` WHERE `id` = ? AND `reserved_at` IS NOT NULL', $job_id)) {
                        $this->connection->execute('UPDATE `' . self::TABLE_NAME . '` SET `process_id` = ? WHERE `id` = ?', $process_id, $job_id);
                    } else {
                        throw new InvalidArgumentException('Job not found or not running');
                    }
                } else {
                    throw new InvalidArgumentException('Process ID is required (a non-negative integer is expected)');
                }
            } else {
                throw new InvalidArgumentException('Only enqueued jobs can report background processes');
            }
        } else {
            throw new InvalidArgumentException('Job does not belong to this queue');
        }
    }

    /**
     * Return a list of background processes that jobs from this queue have launched
     *
     * @return array
     */
    public function getBackgroundProcesses()
    {
        if ($result = $this->connection->execute('SELECT `id`, `type`, `process_id` FROM `' . self::TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL AND `process_id` > ? ORDER BY `reserved_at`', 0)) {
            return $result->toArray();
        }

        return [];
    }

    /**
     * Check stuck jobs
     */
    public function checkStuckJobs()
    {
        if ($rows = $this->connection->execute('SELECT * FROM `' . self::TABLE_NAME . '` WHERE `reserved_at` < ?', date('Y-m-d H:i:s', time() - 3600))) {
            foreach ($rows as $row) {
                if ($row['process_id'] > 0) {
                    if ($this->isProcessRunning($row['process_id'])) {
                        continue; // Skip jobs that launched long running background processes
                    } else {
                        $this->deleteJobById($row['id']); // Process done? Consider the job executed
                    }
                } else {
                    try {
                        $this->failJob($this->getJobFromRow($row), new RuntimeException('Job stuck for more than an hour'));
                    } catch (Exception $e) {
                        $this->connection->beginWork();

                        $this->connection->execute('INSERT INTO `' . self::TABLE_NAME_FAILED . '` (`type`, `channel`, `data`, `failed_at`, `reason`) VALUES (?, ?, ?, ?, ?)', $row['type'], $row['channel'], $row['data'], date('Y-m-d H:i:s'), $e->getMessage());
                        $this->deleteJobById($row['id']);

                        $this->connection->commit();
                    }
                }
            }
        }
    }

    /**
     * Check if process with PID $process_id is running
     *
     * @param  integer $process_id
     * @return bool
     */
    private function isProcessRunning($process_id)
    {
        return DIRECTORY_SEPARATOR != '\\' && posix_kill($process_id, 0); // Note: 0 signal does not kill the process, but kill will check for process existance
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

    /**
     * Decode JSON and throw an exception in case of any error
     *
     * @param  string           $serialized_data
     * @return mixed
     * @throws RuntimeException
     */
    private function jsonDecode($serialized_data)
    {
        $data = json_decode($serialized_data, true);

        if (json_last_error()) {
            $error_message = 'Failed to parse JSON';

            if (function_exists('json_last_error_msg')) {
                $error_message .= '. Reason: ' . json_last_error_msg();
            }

            throw new RuntimeException($error_message);
        }

        return $data;
    }
}
