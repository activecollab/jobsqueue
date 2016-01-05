<?php

namespace ActiveCollab\JobsQueue\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

/**
 * @package ActiveCollab\JobQueue\Command
 */
class FailedJobReasons extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('failed_job_reasons')
            ->addArgument('type', InputArgument::REQUIRED, 'Name of the job type, or part that matches only one job')
            ->setDescription('List distinct reasons why a particular job type failed');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $queue = $this->dispatcher->getQueue();
            $event_type_names = $this->dispatcher->unfurlType($input->getArgument('type'));

            if (count($event_type_names) > 1) {
                throw new Exception('More than one job type found');
            } elseif (count($event_type_names) == 0) {
                throw new Exception('No job type that matches type argument found under failed jobs');
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
