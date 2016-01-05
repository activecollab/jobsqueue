<?php

namespace ActiveCollab\JobsQueue\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

/**
 * @package ActiveCollab\JobQueue\Command
 */
class JobsQueue extends Command
{
    /**
     * Configure command
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('jobs_queue')
             ->setDescription('List all jobs queues grouped by type');
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $type_rows = $this->dispatcher->getQueue()->countJobsByType();

            if (count($type_rows)) {
                $table = new Table($output);
                $table->setHeaders(['Event Type', 'Jobs Count']);

                foreach ($type_rows as $type => $queued_jobs_count) {
                    $table->addRow([$type, $queued_jobs_count]);
                }

                $table->render();
                $output->writeln('');

                return 0;
            } else {
                return $this->success('No jobs in the queue', $input, $output);
            }
        } catch (Exception $e) {
            return $this->abortDueToException($e, $input, $output);
        }
    }
}
