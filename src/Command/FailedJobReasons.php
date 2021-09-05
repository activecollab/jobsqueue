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

use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FailedJobReasons extends Command
{
    protected function configure()
    {
        parent::configure();

        $this->setName('failed_job_reasons')
            ->addArgument('type', InputArgument::REQUIRED, 'Name of the job type, or part that matches only one job')
            ->setDescription('List distinct reasons why a particular job type failed');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $queue = $this->dispatcher->getQueue();
            $event_type_names = $this->dispatcher->unfurlType($input->getArgument('type'));

            if (empty($event_type_names)) {
                throw new Exception('No job type that matches type argument found under failed jobs');
            }

            if (count($event_type_names) > 1) {
                throw new Exception('More than one job type found');
            }

            $type = $event_type_names[0];

            $output->writeln("Reasons why <comment>'$type'</comment> job failed:");
            $output->writeln('');

            foreach ($queue->getFailedJobReasons($type) as $row) {
                $output->writeln("    <comment>*</comment> $row[reason]");
            }

            return $this->success('', $input, $output);
        } catch (Exception $e) {
            return $this->abortDueToException($e, $input, $output);
        }
    }
}
