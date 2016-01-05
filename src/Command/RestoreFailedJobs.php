<?php

namespace ActiveCollab\JobsQueue\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;
use InvalidArgumentException;
use LogicException;

/**
 * @package ActiveCollab\JobQueue\Command
 */
class RestoreFailedJobs extends Command
{
    /**
     * Configure command
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('restore_failed_jobs')
             ->addOption('by-ids', 'i', InputOption::VALUE_REQUIRED, 'Restore by ID-s from failed log')
             ->addOption('by-type', 't', InputOption::VALUE_REQUIRED, 'Restore by type')
             ->addOption('update-data', 'u', InputOption::VALUE_OPTIONAL, 'Update data with these attributes (JSON)')
             ->setDescription('Restore failed jobs by ID-s or by type');
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $by_ids = $input->getOption('by-ids');
            $by_type = $input->getOption('by-type');

            if ($by_ids && $by_type) {
                throw new LogicException("By ID-s and by type options can't be used at the same time");
            } else {
                if (!$by_ids && !$by_type) {
                    throw new InvalidArgumentException('By ID-s or by type option expected');
                } else {
                    $queue = $this->dispatcher->getQueue();
                    $update_data = $this->getUpdateData($input);

                    if ($by_ids) {
                        foreach (explode(',', $by_ids) as $id) {
                            $id = trim($id);

                            if (ctype_digit($id)) {
                                $job = $queue->restoreFailedJobById($id, $update_data);

                                if ($output->getVerbosity()) {
                                    $output->writeln('<info>OK</info> Job ' . get_class($job) . ' restored');
                                }
                            } else {
                                $output->writeln("<error>Error</error> '$id' is not a valid ID");
                            }
                        }
                    } else {
                        $queue->restoreFailedJobsByType($by_type, $update_data);
                    }
                }
            }

            return $this->success('Done', $input, $output);
        } catch (Exception $e) {
            return $this->abortDueToException($e, $input, $output);
        }
    }

    /**
     * Get update data values
     *
     * @param  InputInterface $input
     * @return array|null
     */
    private function getUpdateData(InputInterface $input)
    {
        $update_data = $input->getOption('update-data');

        if (empty($update_data)) {
            return null;
        }

        if (mb_substr($update_data, 0, 1) == '{' && mb_substr($update_data, mb_strlen($update_data) - 1) == '}') {
            $update_data = json_decode($update_data, true);

            if (json_last_error()) {
                $error_message = 'Failed to parse JSON';

                if (function_exists('json_last_error_msg')) {
                    $error_message .= '. Reason: ' . json_last_error_msg();
                }

                throw new InvalidArgumentException('Failed to parse updata data value. Error: ' . $error_message);
            }

            return $update_data;
        } else {
            throw new InvalidArgumentException("Invalid JSON: '$update_data'");
        }
    }
}
