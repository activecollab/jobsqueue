<?php

namespace ActiveCollab\JobsQueue\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

/**
 * @package ActiveCollab\JobQueue\Command
 */
class CreateTables extends Command
{
    /**
     * Configure command
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('create_tables')
            ->setDescription('Create tables that are needed for MySQL queue to work');
    }

    /**
     * @param  InputInterface $input
     * @param  OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->dispatcher->getQueue()->createTables("CREATE TABLE IF NOT EXISTS `email_log` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `instance_id` int(10) unsigned NOT NULL DEFAULT '0',
              `parent_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `parent_id` int(10) unsigned DEFAULT NULL,
              `sender` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `recipient` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `subject` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `message_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `sent_on` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `message_id` (`message_id`),
              KEY `instance_id` (`instance_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            return $this->success('Done', $input, $output);
        } catch (Exception $e) {
            return $this->abortDueToException($e, $input, $output);
        }
    }
}
