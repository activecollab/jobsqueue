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
use ActiveCollab\JobsQueue\Queue\QueueInterface;
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

        $this->setName('run_jobs')
            ->addOption('seconds', 's', InputOption::VALUE_REQUIRED, 'Run jobs for -s seconds before quitting the process', 50)
            ->addOption('channels', 'c', InputOption::VALUE_REQUIRED, 'Select one or more channels for jobs for process', QueueInterface::MAIN_CHANNEL)
            ->setDescription('Run jobs that are next in line for up to N seconds');
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
                if ($reason instanceof \Exception) {
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

        $output->writeln("There are {$this->dispatcher->getQueue()->count()} jobs in the queue. Preparing to work for {$max_execution_time} seconds.");

        $work_until = time() + $max_execution_time;

        // ---------------------------------------------------
        //  Set channels for the jobs in queue
        // ---------------------------------------------------

        $channels = $this->getChannels($input->getOption('channels'));

        // ---------------------------------------------------
        //  Enter the execution loop
        // ---------------------------------------------------

        do {
            if ($next_in_line = $this->dispatcher->getQueue()->nextInLine(...$channels)) {
                $this->log->info('Running job #{job_id} of {job_type} type', [
                    'job_type' => get_class($next_in_line),
                    'job_id' => $next_in_line->getQueueId(),
                ]);

                if ($output->getVerbosity()) {
                    $output->writeln("<info>OK:</info> Running job #{$next_in_line->getQueueId()} (" . get_class($next_in_line) . ')');
                }

                if (method_exists($next_in_line, 'setContainer')) {
                    $next_in_line->setContainer($this->getContainer());
                }

                $this->dispatcher->getQueue()->execute($next_in_line);

                if ($output->getVerbosity()) {
                    $output->writeln("<info>OK:</info> Job #{$next_in_line->getQueueId()} (" . get_class($next_in_line) . ") done");
                }

                $job_id = $next_in_line->getQueueId();

                if (!in_array($job_id, $jobs_ran)) {
                    $jobs_ran[] = $job_id;
                }
            } else {
                $sleep_for = mt_rand(900000, 1000000);

                $this->log->debug('Nothing to do at the moment, or job reservation collision. Sleeping for {sleep_for} microseconds', ['sleep_for' => $sleep_for]);

                if ($output->getVerbosity()) {
                    $output->writeln("<comment>Notice:</comment> Nothing to do at the moment, or job reservation collision. Sleeping for {$sleep_for} microseconds");
                }
                usleep($sleep_for);
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
        $output->writeln('Execution stats: ' . $execution_stats['jobs_ran'] . ' ran, ' . $execution_stats['jobs_failed'] . ' failed. ' . $execution_stats['left_in_queue'] . ' left in queue. Executed in ' . $execution_stats['exec_time']);
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
