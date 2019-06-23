<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Command;

use ActiveCollab\JobsQueue\Jobs\Job;
use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package ActiveCollab\JobQueue\Command
 */
class RunJobs extends Command
{
    /**
     * Configure command.
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('run_jobs')
            ->setDescription('Run jobs that are next in line for up to N seconds')
            ->addOption(
                'seconds',
                's',
                InputOption::VALUE_REQUIRED,
                'Run jobs for -s seconds before quitting the process',
                50
            )
            ->addOption(
                'channels',
                'c',
                InputOption::VALUE_REQUIRED,
                'Select one or more channels for jobs for process',
                QueueInterface::MAIN_CHANNEL
            )
            ->addOption(
                'jobs-per-batch',
                '',
                InputOption::VALUE_REQUIRED,
                'Number of jobs that should be fetched and executed per single run',
                1
            );
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $reference_time = microtime(true);

        // ---------------------------------------------------
        //  Prepare dispatcher and success and error logs
        // ---------------------------------------------------

        $jobs_ran = $jobs_failed = [];

        $this->dispatcher->getQueue()->onJobFailure(function (Job $job, $reason) use (&$jobs_failed, $input, $output) {
            $job_id = $job->getQueueId();

            if (!in_array($job_id, $jobs_failed)) {
                $jobs_failed[] = $job_id;
            }

            $job_class = get_class($job);

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                if ($reason instanceof Exception) {
                    $output->writeln("<error>Error:</error> Job #{$job->getQueueId()} ($job_class) failed with message {$reason->getMessage()}");

                    if ($input->getOption('debug')) {
                        $output->writeln('');
                        $output->writeln('Exception: ' . get_class($reason));
                        $output->writeln('File: ' . $reason->getFile());
                        $output->writeln('Line: ' . $reason->getLine());
                        $output->writeln('Trace:');
                        $output->writeln('');
                        $output->writeln($reason->getTraceAsString());
                        $output->writeln('');
                    }
                } else {
                    $output->writeln("<error>Error:</error> Job #{$job->getQueueId()} ($job_class) failed");
                }
            }
        });

        // ---------------------------------------------------
        //  Set max execution time for the jobs in queue
        // ---------------------------------------------------

        $max_execution_time = (integer) $input->getOption('seconds');
        $jobs_per_batch = (integer) $input->getOption('jobs-per-batch');

        if ($max_execution_time < 1) {
            throw new InvalidArgumentException(
                sprintf("Max execution time can't be less than 1 second, %d given.", $max_execution_time)
            );
        }

        if ($jobs_per_batch < 1) {
            throw new InvalidArgumentException(
                sprintf("Number of jobs per batch can't be smaller than 1, %d given.", $jobs_per_batch)
            );
        }

        $output->writeln(
            sprintf(
                'There are <comment>%d</comment> jobs in the queue. System will run %d jobs per batch. Preparing to work for <comment>%d</comment> seconds...',
                $this->dispatcher->getQueue()->count(),
                $jobs_per_batch,
                $max_execution_time
            )
        );

        $work_until = time() + $max_execution_time;

        // ---------------------------------------------------
        //  Set channels for the jobs in queue
        // ---------------------------------------------------

        $channels = $this->getChannels($input->getOption('channels'));

        // ---------------------------------------------------
        //  Enter the execution loop
        // ---------------------------------------------------

        do {

            // Run a single job.
            if ($jobs_per_batch === 1) {
                if ($next_in_line = $this->dispatcher->getQueue()->nextInLine(...$channels)) {
                    $job_id = $this->runJob($next_in_line, $output);

                    if (!in_array($job_id, $jobs_ran)) {
                        $jobs_ran[] = $job_id;
                    }
                } else {
                    $this->sleep($output);
                }

            // Run a batch of jobs.
            } else {
                $batch_of_jobs = $this->dispatcher->getQueue()->nextBatchInLine($jobs_per_batch, ...$channels);

                if (!empty($batch_of_jobs)) {
                    foreach ($batch_of_jobs as $job) {
                        $job_id = $this->runJob($job, $output);

                        if (!in_array($job_id, $jobs_ran)) {
                            $jobs_ran[] = $job_id;
                        }
                    }
                } else {
                    $this->sleep($output);
                }
            }
        } while (time() < $work_until);

        // ---------------------------------------------------
        //  Print stats
        // ---------------------------------------------------

        $execution_stats = [
            'time_limit' => $max_execution_time,
            'exec_time' => round(microtime(true) - $reference_time, 3),
            'jobs_ran' => count($jobs_ran),
            'jobs_failed' => count($jobs_failed),
            'left_in_queue' => $this->dispatcher->getQueue()->count(),
        ];

        $this->log->info('{jobs_ran} jobs ran in {exec_time}s', $execution_stats);
        $output->writeln(
            sprintf(
                'Execution stats: %d ran, %d failed. %d left in queue. Executed in %s seconds.',
                $execution_stats['jobs_ran'],
                $execution_stats['jobs_failed'],
                $execution_stats['left_in_queue'],
                $execution_stats['exec_time']
            )
        );

        return 0;
    }

    private function runJob(JobInterface $job, OutputInterface $output)
    {
        $this->log->info('Running job #{job_id} of {job_type} type', [
            'job_type' => get_class($job),
            'job_id' => $job->getQueueId(),
        ]);

        if ($output->getVerbosity()) {
            $output->writeln("<info>OK:</info> Running job #{$job->getQueueId()} (" . get_class($job) . ')');
        }

        if (method_exists($job, 'setContainer')) {
            $job->setContainer($this->getContainer());
        }

        $this->dispatcher->getQueue()->execute($job);

        if ($output->getVerbosity()) {
            $output->writeln("<info>OK:</info> Job #{$job->getQueueId()} (" . get_class($job) . ") done");
        }

        return $job->getQueueId();
    }

    private function sleep(OutputInterface $output)
    {
        $sleep_for = mt_rand(900000, 1000000);

        $this->log->debug('Nothing to do at the moment, or job reservation collision. Sleeping for {sleep_for} microseconds', ['sleep_for' => $sleep_for]);

        if ($output->getVerbosity()) {
            $output->writeln(
                sprintf(
                    "<comment>Notice:</comment> Nothing to do at the moment, or job reservation collision. Sleeping for %d microseconds",
                    $sleep_for
                )
            );
        }
        usleep($sleep_for);
    }

    /**
     * Convert channels string to channel list.
     *
     * @param  string $channels
     * @return array
     */
    protected function getChannels($channels)
    {
        $channels = trim($channels);

        if (empty($channels)) {
            throw new InvalidArgumentException('No channel found.');
        }

        return $channels === '*' ? [] : explode(',', $channels);
    }
}
