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

namespace ActiveCollab\JobsQueue\Test\Commands;

use ActiveCollab\JobsQueue\Test\Base\IntegratedTestCase;
use ActiveCollab\JobsQueue\Command\CreateTables;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use ActiveCollab\JobsQueue\Queue\MySqlQueue\AdditionalTablesResolverInterface;

class CreateTablesTest extends IntegratedTestCase
{
    private CreateTables $command;

    /**
     * Set up test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->command = new CreateTables();
        $this->command->setContainer($this->container);
    }

    public function testExecuteInstallsDefaultTables(): void
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('create_tables');

        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertStringContainsString('Done', $command_tester->getDisplay());

        $this->assertTrue($this->connection->tableExists(MySqlQueue::BATCHES_TABLE_NAME));
        $this->assertTrue($this->connection->tableExists(MySqlQueue::JOBS_TABLE_NAME));
        $this->assertTrue($this->connection->tableExists(MySqlQueue::FAILED_JOBS_TABLE_NAME));
    }

    public function testExecuteInstallsAdditionalTables(): void
    {
        $application = new Application();
        $application->add($this->command);

        $this->container['additional_tables_resolver'] = new class implements AdditionalTablesResolverInterface {
            public function getAdditionalTables(): array
            {
                return [
                    "CREATE TABLE IF NOT EXISTS `email_log` (
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
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
                ];
            }
        };

        $command = $application->find('create_tables');

        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertStringContainsString('Done', $command_tester->getDisplay());
        $this->assertTrue($this->connection->tableExists('email_log'));
    }
}
