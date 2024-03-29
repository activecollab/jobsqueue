<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace ActiveCollab\JobsQueue\Queue;

use ActiveCollab\DatabaseConnection\ConnectionInterface;
use ActiveCollab\JobsQueue\Batches\BatchInterface;
use ActiveCollab\JobsQueue\Batches\MySqlBatch;
use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\JobsDispatcherInterface;
use ActiveCollab\JobsQueue\Queue\PropertyExtractors\IntPropertyExtractor;
use ActiveCollab\JobsQueue\Queue\PropertyExtractors\PropertyExtractorInterface;
use ActiveCollab\JobsQueue\Queue\PropertyExtractors\StringPropertyExtractor;
use ActiveCollab\JobsQueue\Signals\SignalInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class MySqlQueue extends Queue
{
    const BATCHES_TABLE_NAME = 'job_batches';
    const JOBS_TABLE_NAME = 'jobs_queue';
    const FAILED_JOBS_TABLE_NAME = 'jobs_queue_failed';

    const TABLE_NAMES = [
        self::BATCHES_TABLE_NAME,
        self::JOBS_TABLE_NAME,
        self::FAILED_JOBS_TABLE_NAME,
    ];

    /**
     * @var PropertyExtractorInterface[]
     */
    private array $property_extractors;

    public function __construct(
        private ConnectionInterface $connection,
        array $additional_extractors = [],
        bool $create_tables_if_missing = true,
        LoggerInterface $logger = null
    )
    {
        parent::__construct($logger);

        $this->property_extractors = array_merge(
            [
                new IntPropertyExtractor('priority'),
            ],
            $additional_extractors,
        );

        if ($create_tables_if_missing) {
            $this->createTables();
        }
    }

    public function createTables(string ...$additional_tables): void
    {
        $existing_table_names = $this->connection->getTableNames();

        try {
            foreach (self::TABLE_NAMES as $table_name) {
                if (!in_array($table_name, $existing_table_names)) {
                    $this->logger?->info(
                        'Creating {table_name} MySQL queue table',
                        [
                            'table_name' => $table_name,
                        ]
                    );

                    $this->connection->execute(
                        file_get_contents(
                            sprintf(__DIR__ . '/MySqlQueue/table.%s.sql', $table_name)
                        )
                    );
                }
            }

            foreach ($additional_tables as $additional_table) {
                $this->connection->execute($additional_table);
            }

            $after_column = 'data';

            foreach ($this->property_extractors as $property_extractor) {
                if (!$this->connection->fieldExists(self::JOBS_TABLE_NAME, $property_extractor->getName())) {
                    $this->connection->execute(
                        sprintf(
                            'ALTER TABLE `%s` ADD %s AFTER `%s`',
                            self::JOBS_TABLE_NAME,
                            $this->prepareExtractionDefinition($property_extractor),
                            $after_column
                        )
                    );

                    $after_column = $property_extractor->getName();
                }
            }
        } catch (Exception $e) {
            throw new Exception('Error on create table execute. MySql error message:' . $e->getMessage());
        }
    }

    private function prepareExtractionDefinition(PropertyExtractorInterface $property_extractor): string
    {
        return sprintf(
            "`%s` %s GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`data`, '%s'))) STORED",
            $property_extractor->getName(),
            $this->getExtractorTypeDefinition($property_extractor),
            $property_extractor->getDataPath(),
        );
    }

    private function getExtractorTypeDefinition(PropertyExtractorInterface $property_extractor): string
    {
        if ($property_extractor instanceof StringPropertyExtractor) {
            return sprintf('VARCHAR(%d)', $property_extractor->getLength());
        } elseif ($property_extractor instanceof IntPropertyExtractor) {
            return 'INT UNSIGNED';
        }

        throw new RuntimeException(sprintf('Unsupported extractor type %s.', get_class($property_extractor)));
    }

    public function enqueue(
        JobInterface $job,
        string $channel = QueueInterface::MAIN_CHANNEL,
    ): int
    {
        $job_data = $job->getData();

        $available_at_timestamp = date('Y-m-d H:i:s', time() + $job->getFirstJobDelay());

        $this->connection->execute(
            sprintf(
                'INSERT INTO `%s` (`type`, `channel`, `batch_id`, `data`, `available_at`) VALUES (?, ?, ?, ?, ?)',
                self::JOBS_TABLE_NAME,
            ),
            get_class($job),
            $channel,
            $job->getBatchId(),
            json_encode($job_data),
            $available_at_timestamp
        );

        $job_id = $this->connection->lastInsertId();

        $this->logger?->info(
            'Job #{job_id} ({job_type}) enqueued. Becomes available at {available_at}',
            [
                'job_id' => $job_id,
                'job_type' => get_class($job),
                'available_at' => $available_at_timestamp,
            ]
        );

        return $job_id;
    }

    public function dequeue(int $job_id): void
    {
        $this->connection->execute('DELETE FROM `' . self::JOBS_TABLE_NAME . '` WHERE `id` = ?', $job_id);
    }

    public function dequeueByType(string $type, array $properties = null): void
    {
        $conditions = $this->propertiesToConditions(
            $properties,
            [
                $this->connection->prepare('`type` = ? AND `reservation_key` IS NULL', $type),
            ]
        );

        $this->connection->execute(
            sprintf(
                'DELETE FROM `%s` WHERE %s',
                self::JOBS_TABLE_NAME,
                implode(' AND ', $conditions)
            ),
        );
    }

    public function execute(JobInterface $job, bool $silent = true): mixed
    {
        try {
            $this->logger?->info('Executing #{job_id} ({job_type})', [
                'job_id' => $job->getQueueId(),
                'job_type' => get_class($job),
                'event' => 'job_started',
            ]);

            $result = $job->execute();

            if ($result instanceof SignalInterface && $result->keepJobInQueue()) {
                $log_message = 'Job #{job_id} ({job_type}) executed and kept in the queue';
            } else {
                $log_message = 'Job #{job_id} ({job_type}) executed';

                $this->deleteJob($job);
            }

            $this->logger?->info(
                $log_message,
                [
                    'job_id' => $job->getQueueId(),
                    'job_type' => get_class($job),
                    'event' => 'job_executed',
                ]
            );

            return $result;
        } catch (Exception $e) {
            $this->failJob($job, $e, $silent);
        }

        return null;
    }

    public function countByChannel(string $channel): int
    {
        return (int) $this->connection->executeFirstCell(
            sprintf('SELECT COUNT(`id`) AS "row_count" FROM `%s` WHERE `channel` = ?', self::JOBS_TABLE_NAME),
            $channel
        );
    }

    public function exists(string $job_type, array $properties = null): bool
    {
        $conditions = $this->propertiesToConditions(
            $properties,
            [
                $this->connection->prepare('`type` = ?', $job_type),
            ]
        );

        return (bool) $this->connection->executeFirstCell(
            sprintf(
                'SELECT COUNT(`id`) AS "row_count" FROM `%s` WHERE %s',
                self::JOBS_TABLE_NAME,
                implode(' AND ', $conditions)
            ),
        );
    }

    public function changePriority(
        string $job_type,
        int $new_priority,
        array $properties = null
    ): void
    {
        $conditions = $this->propertiesToConditions(
            $properties,
            [
                $this->connection->prepare('`type` = ? AND `reservation_key` IS NULL', $job_type),
            ]
        );

        $this->connection->execute(
            sprintf(
                'UPDATE `%s` SET `data` = JSON_SET(`data`, "$.priority", %d) WHERE %s',
                self::JOBS_TABLE_NAME,
                $new_priority,
                implode(' AND ', $conditions)
            ),
        );
    }

    private function propertiesToConditions(?array $properties, array $conditions = []): array
    {
        if ($properties) {
            foreach ($properties as $property => $value_to_match) {
                if (is_bool($value_to_match)) {
                    $prepared_value = $value_to_match ? 'true' : 'false';
                } else {
                    $prepared_value = $this->connection->escapeValue($value_to_match);
                }

                if ($this->connection->fieldExists(self::JOBS_TABLE_NAME, $property)) {
                    $conditions[] = $this->connection->prepare(
                        sprintf('`%s` = ?', $property),
                        $value_to_match
                    );

                    continue;
                }

                if (is_bool($value_to_match)) {
                    $conditions[] = sprintf(
                        'IF(JSON_EXTRACT(`data`, "$.%s"), TRUE, FALSE) IS %s',
                        $property,
                        $value_to_match ? 'TRUE' : 'FALSE'
                    );

                    continue;
                }

                $conditions[] = $this->connection->prepare(
                    sprintf('JSON_UNQUOTE(JSON_EXTRACT(`data`, "$.%s")) = ?', $property),
                    $value_to_match
                );
            }
        }

        return $conditions;
    }

    /**
     * Handle a job failure (attempts, removal from queue, exception handling etc).
     */
    private function failJob(
        JobInterface $job,
        Exception $reason = null,
        bool $silent = true,
    ): void
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

                if ($this->logger) {
                    $log_message = 'Job #{job_id} ({job_type}) failed after {attempts} attempts';
                    $log_arguments['attempts'] = $previous_attempts + 1;

                    if ($reason instanceof Exception) {
                        $log_message .= '. Exception: {exception}';
                    }

                    $this->logger->error($log_message, $log_arguments);
                }

                $this->logFailedJob($job, ($reason instanceof Exception ? $reason->getMessage() : ''));
            } else {
                $log_arguments['event'] = 'job_attempt_failed';

                if ($this->logger) {
                    $log_message = 'Job #{job_id} ({job_type}) failed at attempt {attempt}';
                    $log_arguments['attempt'] = $previous_attempts + 1;

                    if ($reason instanceof Exception) {
                        $log_message .= '. Exception: {exception}';
                    }

                    $this->logger->error($log_message, $log_arguments);
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

    public function getJobById(int $job_id): ?JobInterface
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
     */
    private function getJobFromRow(array $row): JobInterface
    {
        $type = $row['type'];

        /** @var Job $job */
        $job = new $type($this->jsonDecode($row['data']));
        $job->setChannel($row['channel']);
        $job->setBatchId($row['batch_id']);
        $job->setQueue($this, (int) $row['id']);

        return $job;
    }

    /**
     * Return number of previous attempts that we recorded for the given job.
     */
    private function getPreviousAttemptsByJobId(int $job_id): int
    {
        return (int) $this->connection->executeFirstCell(
            'SELECT `attempts` FROM `' . self::JOBS_TABLE_NAME . '` WHERE `id` = ?',
            $job_id,
        );
    }

    /**
     * Increase number of attempts by job ID.
     */
    private  function prepareForNextAttempt(
        int $job_id,
        int $previous_attempts,
        int $delay = 0,
    ): void
    {
        $this->connection->execute('UPDATE `' . self::JOBS_TABLE_NAME . '` SET `available_at` = ?, `reservation_key` = NULL, `reserved_at` = NULL, `attempts` = ? WHERE `id` = ?', date('Y-m-d H:i:s', time() + $delay), $previous_attempts + 1, $job_id);
    }

    /**
     * Log failed job and delete it from the main queue.
     */
    private function logFailedJob(JobInterface $job, string $reason): void
    {
        if (mb_strlen($reason) > 191) {
            $reason = mb_substr($reason, 0, 191);
        }

        $this->connection->transact(
            function () use ($job, $reason) {
                $this->connection->insert(
                    self::FAILED_JOBS_TABLE_NAME,
                    [
                        'type' => get_class($job),
                        'channel' => $job->getChannel(),
                        'batch_id' => $job->getBatchId(),
                        'data' => json_encode($job->getData()),
                        'failed_at' => date('Y-m-d H:i:s'),
                        'reason' => $reason,
                    ],
                );

                $this->deleteJob($job);
            },
        );
    }

    public function deleteJob(JobInterface $job): void
    {
        if ($job_id = $job->getQueueId()) {
            $this->dequeue($job_id);
        }
    }

    public function nextInLine(string ...$from_channels): ?JobInterface
    {
        $reserved_job_ids = $this->reserveNextJobs($from_channels);

        if (count($reserved_job_ids) === 1) {
            return $this->getJobById($reserved_job_ids[0]);
        }

        return null;
    }

    /**
     * Return a batch of jobs that are next in line to be executed.
     *
     * @return JobInterface[]
     */
    public function nextBatchInLine(int $jobs_to_run, string ...$from_channels): array
    {
        if ($jobs_to_run < 1) {
            throw new InvalidArgumentException('Jobs to run needs to be a number larger than zero.');
        }

        $result = [];

        foreach ($this->reserveNextJobs($from_channels, $jobs_to_run) as $job_id) {
            $result[] = $this->getJobById($job_id);
        }

        return $result;
    }

    /**
     * @var callable|null
     */
    private $on_reservation_key_ready;

    /**
     * Callback that is called when reservation key is prepared for a particular job, but not yet set.
     *
     * This callback is useful for testing job snatching when we have multiple workers
     */
    public function onReservationKeyReady(callable $callback = null): void
    {
        $this->on_reservation_key_ready = $callback;
    }

    /**
     * Reserve next job ID.
     */
    private function reserveNextJobs(array $from_channels = null, int $number_of_jobs_to_reserve = 1): array
    {
        $reserved_job_ids = [];

        $timestamp = date('Y-m-d H:i:s');
        $channel_conditions = empty($from_channels) ? '' : $this->connection->prepareConditions(['`channel` IN ? AND ', $from_channels]);

        $limit = $number_of_jobs_to_reserve + 100;

        $job_ids = $this->connection->executeFirstColumn(
            sprintf(
                'SELECT `id` 
                    FROM `%s` 
                    WHERE %s`reserved_at` IS NULL AND `available_at` <= ? 
                    ORDER BY `priority` DESC, `id` 
                    LIMIT 0, %d',
                self::JOBS_TABLE_NAME,
                $channel_conditions,
                $limit,
            ),
            $timestamp,
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

    private function prepareNewReservationKey(): string
    {
        do {
            $reservation_key = sha1(microtime(true) . mt_rand(10000, 90000));
        } while ($this->reservationKeyExists($reservation_key));

        return $reservation_key;
    }

    private function reservationKeyExists(string $reservation_key): bool
    {
        return (bool) $this->connection->executeFirstCell(
            sprintf(
                'SELECT COUNT(`id`) AS "row_count" FROM `%s` WHERE `reservation_key` = ?',
                self::JOBS_TABLE_NAME,
            ),
            $reservation_key,
        );
    }

    public function restoreFailedJobById(int $job_id, array $update_data = null): JobInterface
    {
        $row = $this->connection->executeFirstRow(
            sprintf(
                'SELECT `type`, `channel`, `data` FROM `%s` WHERE `id` = ?',
                self::FAILED_JOBS_TABLE_NAME,
            ),
            $job_id,
        );

        if (empty($row)) {
            throw new RuntimeException(sprintf("Failed job #%d not found.", $job_id));
        }

        $job = null;

        $this->connection->transact(
            function () use (&$job, $job_id, $update_data, $row) {
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
            },
        );

        return $job;
    }

    public function restoreFailedJobsByType(string $job_type, array $update_data = null): void
    {
        if ($job_ids = $this->connection->executeFirstColumn('SELECT `id` FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `type` LIKE ?', "%$job_type%")) {
            foreach ($job_ids as $job_id) {
                $this->restoreFailedJobById($job_id, $update_data);
            }
        }
    }

    public function count(): int
    {
        return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::JOBS_TABLE_NAME . '`');
    }

    public function countByType(string $type): int
    {
        if (func_num_args()) {
            return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::JOBS_TABLE_NAME . '` WHERE `type` IN ?', func_get_args());
        } else {
            return 0;
        }
    }

    public function countFailed(): int
    {
        return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::FAILED_JOBS_TABLE_NAME . '`');
    }

    public function countFailedByType(string $type): int
    {
        if (func_num_args()) {
            return $this->connection->executeFirstCell('SELECT COUNT(`id`) AS "row_count" FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `type` IN ?', func_get_args());
        }

        return 0;
    }

    public function reportBackgroundProcess(JobInterface $job, int $process_id): void
    {
        if ($job->getQueue() && get_class($job->getQueue()) == get_class($this)) {
            if ($job_id = $job->getQueueId()) {
                if ($process_id > 0) {
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

    public function getBackgroundProcesses(): array
    {
        if ($result = $this->connection->execute('SELECT `id`, `type`, `process_id` FROM `' . self::JOBS_TABLE_NAME . '` WHERE `reserved_at` IS NOT NULL AND `process_id` > ? ORDER BY `reserved_at`', 0)) {
            return $result->toArray();
        }

        return [];
    }

    public function checkStuckJobs(): void
    {
        if ($rows = $this->connection->execute('SELECT * FROM `' . self::JOBS_TABLE_NAME . '` WHERE `reserved_at` < ?', date('Y-m-d H:i:s', time() - 3600))) {
            foreach ($rows as $row) {
                if ($row['process_id'] > 0) {
                    if ($this->isProcessRunning($row['process_id'])) {
                        continue; // Skip jobs that launched long-running background processes
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
     */
    private function isProcessRunning(int $process_id): bool
    {
        return DIRECTORY_SEPARATOR != '\\' && posix_kill($process_id, 0); // Note: 0 signal does not kill the process, but kill will check for process existence
    }

    public function cleanUp(): void
    {
        $this->connection->execute('DELETE FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `failed_at` < ?', date('Y-m-d H:i:s', strtotime('-7 days')));
    }

    private array $on_job_failure = [];

    public function onJobFailure(callable $callback = null): void
    {
        $this->on_job_failure[] = $callback;
    }

    /**
     * Decode JSON and throw an exception in case of any error.
     */
    private function jsonDecode(string $serialized_data): mixed
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

    public function clear(): void
    {
        $this->connection->execute('TRUNCATE TABLE `' . self::JOBS_TABLE_NAME . '`');
        $this->connection->execute('TRUNCATE TABLE `' . self::FAILED_JOBS_TABLE_NAME . '`');
        $this->connection->execute('TRUNCATE TABLE `' . self::BATCHES_TABLE_NAME . '`');
    }

    public function getFailedJobReasons(string $job_type): array
    {
        if ($result = $this->connection->execute('SELECT DISTINCT(`reason`) AS "reason" FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `type` = ?', $job_type)) {
            return $result->toArray();
        }

        return [];
    }

    public function unfurlType(string $search_for): ?array
    {
        return $this->connection->executeFirstColumn('SELECT DISTINCT(`type`) FROM `' . self::FAILED_JOBS_TABLE_NAME . '` WHERE `type` LIKE ?', '%' . $search_for . '%');
    }

    public function failedJobStatistics(): array
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

    public function failedJobStatisticsByType(string $event_type): array
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

    public function countJobsByType(): array
    {
        $result = [];
        $type_rows = $this->connection->execute(
            sprintf(
                'SELECT `type`, COUNT(`id`) AS "queued_jobs_count" FROM `%s` GROUP BY `type`',
                self::JOBS_TABLE_NAME
            )
        );
        if (!empty($type_rows)) {
            foreach ($type_rows as $row) {
                $result[ $row['type'] ] = $row['queued_jobs_count'];
            }
        }

        return $result;
    }

    public function createBatch(JobsDispatcherInterface $dispatcher, string $name): BatchInterface
    {
        if (!$name) {
            throw new InvalidArgumentException('Batch name is required');
        }

        $this->connection->execute(
            sprintf(
                'INSERT INTO `%s` (`name`, `created_at`) VALUES (?, UTC_TIMESTAMP())',
                self::BATCHES_TABLE_NAME
            ),
            $name
        );

        return new MySqlBatch(
            $dispatcher,
            $this->connection,
            $this->connection->lastInsertId(),
            $name
        );
    }

    public function countBatches(): int
    {
        return $this->connection->count(self::BATCHES_TABLE_NAME);
    }
}
