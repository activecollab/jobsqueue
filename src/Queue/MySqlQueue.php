<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Queue;

use ActiveCollab\DatabaseConnection\ConnectionInterface;
use ActiveCollab\JobsQueue\Batches\MySqlBatch;
use ActiveCollab\JobsQueue\DispatcherInterface;
use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\Signals\SignalInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @package ActiveCollab\JobsQueue\Queue
 */
class MySqlQueue extends Queue
{
    const BATCHES_TABLE_NAME = 'job_batches';
    const JOBS_TABLE_NAME = 'jobs_queue';
    const FAILED_JOBS_TABLE_NAME = 'jobs_queue_failed';

    /**
     * @var ConnectionInterface
     */
    private $connection;

    /**
     * @param ConnectionInterface  $connection
     * @param bool|true            $create_tables_if_missing
     * @param LoggerInterface|null $log
     */
    public function __construct(ConnectionInterface &$connection, $create_tables_if_missing = true, LoggerInterface &$log = null)
    {
        parent::__construct($log);

        $this->connection = $connection;

        if ($create_tables_if_missing) {
            $this->createTables();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTables(...$additional_tables)
    {
        $table_names = $this->connection->getTableNames();

        try {
            if (!in_array(self::BATCHES_TABLE_NAME, $table_names)) {
                if ($this->log) {
                    $this->log->info('Creating {table_name} MySQL queue table', ['table_name' => self::BATCHES_TABLE_NAME]);
                }

                $this->connection->execute('CREATE TABLE IF NOT EXISTS `' . self::BATCHES_TABLE_NAME . "` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(191) NOT NULL DEFAULT '',
                    `jobs_count` int(10) unsigned NOT NULL DEFAULT '0',
                    `created_at` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }

            if (!in_array(self::JOBS_TABLE_NAME, $table_names)) {
                if ($this->log) {
                    $this->log->info('Creating {table_name} MySQL queue table', ['table_name' => self::JOBS_TABLE_NAME]);
                }

                $this->connection->execute('CREATE TABLE IF NOT EXISTS `' . self::JOBS_TABLE_NAME . "` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `type` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
                    `channel` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT 'main',
                    `batch_id` int(10) unsigned,
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
                    KEY `batch_id` (`batch_id`),
                    KEY `priority` (`priority`),
                    KEY `available_at` (`available_at`),
                    KEY `reserved_at` (`reserved_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }

            if (!in_array(self::FAILED_JOBS_TABLE_NAME, $table_names)) {
                if ($this->log) {
                    $this->log->info('Creating {table_name} MySQL queue table', ['table_name' => self::FAILED_JOBS_TABLE_NAME]);
                }

                $this->connection->execute('CREATE TABLE IF NOT EXISTS `' . self::FAILED_JOBS_TABLE_NAME . "` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `type` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
                    `channel` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT 'main',
                    `batch_id` int(10) unsigned,
                    `data` longtext CHARACTER SET utf8 NOT NULL,
                    `failed_at` datetime DEFAULT NULL,
                    `reason` varchar(191) CHARACTER SET utf8 NOT NULL DEFAULT '',
                    PRIMARY KEY (`id`),
                    KEY `type` (`type`),
                    KEY `channel` (`channel`),
                    KEY `batch_id` (`batch_id`),
                    KEY `failed_at` (`failed_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }

            foreach ($additional_tables as $additional_table) {
                $this->connection->execute($additional_table);
            }
        } catch (\Exception $e) {
            throw new Exception('Error on create table execute. MySql error message:' . $e->getMessage());
        }
    }

    /**
     * @var array
     */
    private $extract_properties_to_fields = ['priority'];

    /**
     * Extract property value to field value.
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
     * {@inheritdoc}
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

        $available_at_timestamp = date('Y-m-d H:i:s', time() + $job->getFirstJobDelay());

        $this->connection->execute('INSERT INTO `' . self::JOBS_TABLE_NAME . '` (`type`, `channel`, `batch_id`, `data`, `available_at`' . $extract_fields . ') VALUES (?, ?, ?, ?, ?' . $extract_values . ')', get_class($job), $channel, $job->getBatchId(), json_encode($job_data), $available_at_timestamp);

        $job_id = $this->connection->lastInsertId();

        if ($this->log) {
            $this->log->info('Job #{job_id} ({job_type}) enqueued. Becomes available at {available_at}', [
                'job_id' => $job_id,
                'job_type' => get_class($job),
                'available_at' => $available_at_timestamp,
            ]);
        }

        return $job_id;
    }

    /**
     * {@inheritdoc}
     */
    public function dequeue($job_id)
    {
        $this->connection->execute('DELETE FROM `' . self::JOBS_TABLE_NAME . '` WHERE `id` = ?', $job_id);
    }

    /**
     * {@inheritdoc}
     */
    public function dequeueByType($type)
    {
        $this->connection->execute('DELETE FROM `' . self::JOBS_TABLE_NAME . '` WHERE `type` = ?', $type);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(JobInterface $job, $silent = true)
    {
        try {
            if ($this->log) {
                $this->log->info('Executing #{job_id} ({job_type})', [
                    'job_id' => $job->getQueueId(),
                    'job_type' => get_class($job),
                    'event' => 'job_started',
                ]);
            }

            $result = $job->execute();

            if ($result instanceof SignalInterface && $result->keepJobInQueue()) {
                $log_message = 'Job #{job_id} ({job_type}) executed and kept in the queue';
            } else {
                $log_message = 'Job #{job_id} ({job_type}) executed';

                $this->deleteJob($job);
            }

            if ($this->log) {
                $this->log->info($log_message, [
                    'job_id' => $job->getQueueId(),
                    'job_type' => get_class($job),
                    'event' => 'job_executed',
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->failJob($job, $e, $silent);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function countByChannel($channel)
    {
        return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::JOBS_TABLE_NAME . '` WHERE `channel` = ?', $channel);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($job_type, array $properties = null)
    {
        if (empty($properties)) {
            return (boolean) $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::JOBS_TABLE_NAME . '` WHERE `type` = ?', $job_type);
        } else {
            if ($rows = $this->connection->execute('SELECT `data` FROM `' . self::JOBS_TABLE_NAME . '` WHERE `type` = ?', $job_type)) {
                foreach ($rows as $row) {
                    try {
                        $data = $this->jsonDecode($row['data']);

                        $all_properties_found = true;

                        foreach ($properties as $k => $v) {
                            if (!(array_key_exists($k, $data) && $data[ $k ] === $v)) {
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
     * Handle a job failure (attempts, removal from queue, exception handling etc).
     *
     * @param  JobInterface   $job
     * @param  Exception|null $reason
     * @param  bool|true      $silent
     * @throws Exception
     */
    private function failJob(JobInterface $job, Exception $reason = null, $silent = true)
    {
        if ($job_id = $job->getQueueId()) {
            $previous_attempts = $this->getPreviousAttemptsByJobId($job_id);

            $log_arguments = [
                'job_id' => $job->getQueueId(),
                'job_type' => get_class($job),
            ];

            if ($reason) {
                $log_arguments['exception'] = $reason;
            }

            if (($previous_attempts + 1) >= $job->getAttempts()) {
                $log_arguments['event'] = 'job_failed';

                if ($this->log) {
                    $log_message = 'Job #{job_id} ({job_type}) failed after {attempts} attemtps';
                    $log_arguments['attempts'] = $previous_attempts + 1;

                    if ($reason instanceof Exception) {
                        $log_message .= '. Exception: {exception}';
                    }

                    $this->log->error($log_message, $log_arguments);
                }

                $this->logFailedJob($job, ($reason instanceof Exception ? $reason->getMessage() : ''));
            } else {
                $log_arguments['event'] = 'job_attempt_failed';

                if ($this->log) {
                    $log_message = 'Job #{job_id} ({job_type}) failed at attempt {attempt}';
                    $log_arguments['attempt'] = $previous_attempts + 1;

                    if ($reason instanceof Exception) {
                        $log_message .= '. Exception: {exception}';
                    }

                    $this->log->error($log_message, $log_arguments);
                }

                $this->prepareForNextAttempt($job_id, $previous_attempts, $job->getDelay());
            }
        }

        if (!empty($this->on_job_failure)) {
            foreach ($this->on_job_failure as $callback) {
                call_user_func($callback, $job, $reason);
            }
        }

        if ($reason instanceof Exception && !$silent) {
            throw $reason;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getJobById($job_id)
    {
        if ($row = $this->connection->executeFirstRow('SELECT `id`, `channel`, `batch_id`, `type`, `data` FROM `' . self::JOBS_TABLE_NAME . '` WHERE `id` = ?', $job_id)) {
            try {
                return $this->getJobFromRow($row);
            } catch (Exception $e) {
                $this->connection->transact(function () use ($row, $e) {
                    $this->connection->execute('INSERT INTO `' . self::FAILED_JOBS_TABLE_NAME . '` (`type`, `channel`, `batch_id`, `data`, `failed_at`, `reason`) VALUES (?, ?, ?, ?, ?, ?)', $row['type'], $row['channel'], $row['batch_id'], $row['data'], date('Y-m-d H:i:s'), $e->getMessage());
                    $this->dequeue($row['id']);
                });
            }
        }

        return null;
    }

    /**
     * Hydrate a job based on row data.
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
        $job->setBatchId($row['batch_id']);
        $job->setQueue($this, (integer) $row['id']);

        return $job;
    }

    /**
     * Return number of previous attempts that we recorded for the given job.
     *
     * @param  int $job_id
     * @return int
     */
    private function getPreviousAttemptsByJobId($job_id)
    {
        return (integer) $this->connection->executeFirstCell('SELECT `attempts` FROM `' . self::JOBS_TABLE_NAME . '` WHERE `id` = ?', $job_id);
    }

    /**
     * Increase number of attempts by job ID.
     *
     * @param int $job_id
     * @param int $previous_attempts
     * @param int $delay
     */
    public function prepareForNextAttempt($job_id, $previous_attempts, $delay = 0)
    {
        $this->connection->execute('UPDATE `' . self::JOBS_TABLE_NAME . '` SET `available_at` = ?, `reservation_key` = NULL, `reserved_at` = NULL, `attempts` = ? WHERE `id` = ?', date('Y-m-d H:i:s', time() + $delay), $previous_attempts + 1, $job_id);
    }

    /**
     * Log failed job and delete it from the main queue.
     *
     * @param JobInterface $job
     * @param string       $reason
     */
    public function logFailedJob(JobInterface $job, $reason)
    {
        if (mb_strlen($reason) > 191) {
            $reason = mb_substr($reason, 0, 191);
        }

        $this->connection->transact(function () use ($job, $reason) {
            $this->connection->execute('INSERT INTO `' . self::FAILED_JOBS_TABLE_NAME . '` (`type`, `channel`, `batch_id`, `data`, `failed_at`, `reason`) VALUES (?, ?, ?, ?, ?, ?)', get_class($job), $job->getChannel(), $job->getBatchId(), json_encode($job->getData()), date('Y-m-d H:i:s'), $reason);
            $this->deleteJob($job);
        });
    }

    /**
     * Delete a job.
     *
     * @param JobInterface $job
     */
    public function deleteJob($job)
    {
        if ($job_id = $job->getQueueId()) {
            $this->dequeue($job_id);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function nextInLine(...$from_channels)
    {
        $reserved_job_ids = $this->reserveNextJobs($from_channels, 1);

        if (count($reserved_job_ids) === 1) {
            return $this->getJobById($reserved_job_ids[0]);
        } else {
            return null;
        }
    }

    /**
     * @var callable|null
     */
    private $on_reservation_key_ready;

    /**
     * Callback that is called when reservation key is prepared for a particular job, but not yet set.
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
            throw new InvalidArgumentException('Callable or NULL expected');
        }
    }

    /**
     * Reserve next job ID.
     *
     * @param  array|null $from_channels
     * @param  int        $number_of_jobs_to_reserve
     * @return int[]
     */
    private function reserveNextJobs(array $from_channels = null, $number_of_jobs_to_reserve = 1)
    {
        $reserved_job_ids = [];

        $timestamp = date('Y-m-d H:i:s');
        $channel_conditions = empty($from_channels) ? '' : $this->connection->prepareConditions(['`channel` IN ? AND ', $from_channels]);

        $limit = $number_of_jobs_to_reserve + 100;

        $job_ids = $this->connection->executeFirstColumn(
            'SELECT `id` 
                FROM `' . self::JOBS_TABLE_NAME . "` 
                WHERE {$channel_conditions}`reserved_at` IS NULL AND `available_at` <= ? 
                ORDER BY `priority` DESC, `id` 
                LIMIT 0, {$limit}",
            $timestamp
        );

        if (!empty($job_ids)) {
            foreach ($job_ids as $job_id) {
                $reservation_key = $this->prepareNewReservationKey();

                if ($this->on_reservation_key_ready) {
                    call_user_func($this->on_reservation_key_ready, $job_id, $reservation_key);
                }

                $this->connection->execute('UPDATE `' . self::JOBS_TABLE_NAME . '` SET `reservation_key` = ?, `reserved_at` = ? WHERE `id` = ? AND `reservation_key` IS NULL', $reservation_key, $timestamp, $job_id);

                if ($this->connection->affectedRows() === 1) {
                    $reserved_job_ids[] = $job_id;

                    if (count($reserved_job_ids) >= $number_of_jobs_to_reserve) {
                        break;
                    }
                }
            }
        }

        return $reserved_job_ids;
    }

    /**
     * {@inheritdoc}
     */
    private function prepareNewReservationKey()
    {
        do {
            $reservation_key = sha1(microtime(true) . mt_rand(10000, 90000));
        } while ($this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::JOBS_TABLE_NAME . '` WHERE `reservation_key` = ?', $reservation_key));

        return $reservation_key;
    }

    /**
     * {@inheritdoc}
     */
    public function restoreFailedJobById($job_id, array $update_data = null)
    {
        $job = null;

        if ($row = $this->connection->executeFirstRow('SELECT `type`, `channel`, `data` FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `id` = ?', $job_id)) {
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
                $this->connection->execute('DELETE FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `id` = ?', $job_id);
            });
        } else {
            throw new RuntimeException("Failed job #{$job_id} not found");
        }

        return $job;
    }

    /**
     * {@inheritdoc}
     */
    public function restoreFailedJobsByType($job_type, array $update_data = null)
    {
        if ($job_ids = $this->connection->executeFirstColumn('SELECT `id` FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `type` LIKE ?', "%$job_type%")) {
            foreach ($job_ids as $job_id) {
                $this->restoreFailedJobById($job_id, $update_data);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::JOBS_TABLE_NAME . '`');
    }

    /**
     * {@inheritdoc}
     */
    public function countByType($type1)
    {
        if (func_num_args()) {
            return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::JOBS_TABLE_NAME . '` WHERE `type` IN ?', func_get_args());
        } else {
            return 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function countFailed()
    {
        return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::FAILED_JOBS_TABLE_NAME . '`');
    }

    /**
     * {@inheritdoc}
     */
    public function countFailedByType($type1)
    {
        if (func_num_args()) {
            return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `type` IN ?', func_get_args());
        } else {
            return 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reportBackgroundProcess(JobInterface $job, $process_id)
    {
        if ($job->getQueue() && get_class($job->getQueue()) == get_class($this)) {
            if ($job_id = $job->getQueueId()) {
                if (is_int($process_id) && $process_id > 0) {
                    if ($this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::JOBS_TABLE_NAME . '` WHERE `id` = ? AND `reserved_at` IS NOT NULL', $job_id)) {
                        $this->connection->execute('UPDATE `' . self::JOBS_TABLE_NAME . '` SET `process_id` = ? WHERE `id` = ?', $process_id, $job_id);
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
     * {@inheritdoc}
     */
    public function getBackgroundProcesses()
    {
        if ($result = $this->connection->execute('SELECT `id`, `type`, `process_id` FROM `' . self::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL AND `process_id` > ? ORDER BY `reserved_at`', 0)) {
            return $result->toArray();
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function checkStuckJobs()
    {
        if ($rows = $this->connection->execute('SELECT * FROM `' . self::JOBS_TABLE_NAME . '` WHERE `reserved_at` < ?', date('Y-m-d H:i:s', time() - 3600))) {
            foreach ($rows as $row) {
                if ($row['process_id'] > 0) {
                    if ($this->isProcessRunning($row['process_id'])) {
                        continue; // Skip jobs that launched long running background processes
                    } else {
                        $this->dequeue($row['id']); // Process done? Consider the job executed
                    }
                } else {
                    try {
                        $this->failJob($this->getJobFromRow($row), new RuntimeException('Job stuck for more than an hour'));
                    } catch (Exception $e) {
                        $this->connection->beginWork();

                        $this->connection->execute('INSERT INTO `' . self::FAILED_JOBS_TABLE_NAME . '` (`type`, `channel`, `data`, `failed_at`, `reason`) VALUES (?, ?, ?, ?, ?)', $row['type'], $row['channel'], $row['data'], date('Y-m-d H:i:s'), $e->getMessage());
                        $this->dequeue($row['id']);

                        $this->connection->commit();
                    }
                }
            }
        }
    }

    /**
     * Check if process with PID $process_id is running.
     *
     * @param  int  $process_id
     * @return bool
     */
    private function isProcessRunning($process_id)
    {
        return DIRECTORY_SEPARATOR != '\\' && posix_kill($process_id, 0); // Note: 0 signal does not kill the process, but kill will check for process existance
    }

    /**
     * {@inheritdoc}
     */
    public function cleanUp()
    {
        $this->connection->execute('DELETE FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `failed_at` < ?', date('Y-m-d H:i:s', strtotime('-7 days')));
    }

    /**
     * @var callable[]
     */
    private $on_job_failure = [];

    /**
     * {@inheritdoc}
     */
    public function onJobFailure(callable $callback = null)
    {
        $this->on_job_failure[] = $callback;
    }

    /**
     * Decode JSON and throw an exception in case of any error.
     *
     * @param  string $serialized_data
     * @return mixed
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

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->connection->execute('TRUNCATE TABLE `' . self::JOBS_TABLE_NAME . '`');
        $this->connection->execute('TRUNCATE TABLE `' . self::FAILED_JOBS_TABLE_NAME . '`');
        $this->connection->execute('TRUNCATE TABLE `' . self::BATCHES_TABLE_NAME . '`');
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedJobReasons($job_type)
    {
        if ($result = $this->connection->execute('SELECT DISTINCT(`reason`) AS "reason" FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `type` = ?', $job_type)) {
            return $result->toArray();
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function unfurlType($search_for)
    {
        return $this->connection->executeFirstColumn('SELECT DISTINCT(`type`) FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `type` LIKE ?', '%' . $search_for . '%');
    }

    /**
     * {@inheritdoc}
     */
    public function failedJobStatistics()
    {
        $result = [];
        $event_types = $this->connection->executeFirstColumn('SELECT DISTINCT(`type`) FROM `' . self::FAILED_JOBS_TABLE_NAME . '`');

        if (count($event_types)) {
            foreach ($event_types as $event_type) {
                $result[ $event_type ] = $this->failedJobStatisticsByType($event_type);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function failedJobStatisticsByType($event_type)
    {
        $result = [];
        $job_rows = $this->connection->execute('SELECT DATE(`failed_at`) AS "date", COUNT(`id`) AS "failed_jobs_count" FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `type` = ? GROUP BY DATE(`failed_at`)', $event_type);
        if (count($job_rows)) {
            foreach ($job_rows as $row) {
                $result[ $row['date'] ] = $row['failed_jobs_count'];
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function countJobsByType()
    {
        $result = [];
        $type_rows = $this->connection->execute('SELECT `type`, COUNT(`id`) AS "queued_jobs_count" FROM `' . self::JOBS_TABLE_NAME . '` GROUP BY `type`');
        if (count($type_rows)) {
            foreach ($type_rows as $row) {
                $result[ $row['type'] ] = $row['queued_jobs_count'];
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function createBatch(DispatcherInterface &$dispatcher, $name)
    {
        if ($name) {
            $this->connection->execute('INSERT INTO `' . self::BATCHES_TABLE_NAME . '` (`name`, `created_at`) VALUES (?, UTC_TIMESTAMP())', $name);

            return new MySqlBatch($dispatcher, $this->connection, $this->connection->lastInsertId(), $name);
        } else {
            throw new InvalidArgumentException('Batch name is required');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function countBatches()
    {
        return $this->connection->count(self::BATCHES_TABLE_NAME);
    }
}
