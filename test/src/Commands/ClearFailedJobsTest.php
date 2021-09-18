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
use ActiveCollab\JobsQueue\Command\ClearFailedJobs;
use ActiveCollab\JobsQueue\JobsDispatcher;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ClearFailedJobsIntegratedTest extends IntegratedTestCase
{
    /**
     * @var ClearFailedJobs
     */
    private $command;

    public function setUp(): void
    {
        parent::setUp();

        $this->command = new ClearFailedJobs();
        $this->command->setContainer($this->container);
    }

    /**
     * Test if execute will delete all records from failed job table.
     */
    public function testExecuteRunsOK()
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('clear_failed_jobs');
        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertMatchesRegularExpression('/Done/', $command_tester->getDisplay());
        $this->assertFailedRecordsCount(0);
    }

    /**
     * Test if unexpected exception  is handel.
     */
    public function testExecuteThrowErrorToDisplay()
    {
        $error_message = 'Expected test exception.';

        /** @var MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['clear'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('clear')
            ->will($this->throwException(new Exception($error_message)));

        $this->container['dispatcher'] = new JobsDispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('clear_failed_jobs');
        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertStringContainsString('Expected test exception.', $command_tester->getDisplay());
    }
}
