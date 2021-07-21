<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobQueue\Test\Commands;

use ActiveCollab\JobsQueue\Command\CreateTables;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @package ActiveCollab\JobQueue\Test\Commands
 */
class CreateTablesTest extends TestCase
{
    /**
     * @var CreateTables
     */
    private $command;

    /**
     * Set up test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->command = new CreateTables();
        $this->command->setContainer($this->container);
    }

    /**
     * Test if create db script is run correctly.
     */
    public function testExecuteRunsOK()
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
        $this->assertTrue($this->connection->tableExists('email_log'));
    }
}
