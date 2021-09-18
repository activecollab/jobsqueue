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

use ActiveCollab\JobsQueue\Test\Base\IntegratedContainerTestCase;
use ActiveCollab\JobsQueue\Command\FailedJobs;
use ActiveCollab\JobsQueue\JobsDispatcher;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class FailedJobsTest extends IntegratedContainerTestCase
{
    private $command;

    public function setUp(): void
    {
        parent::setUp();

        $this->command = new FailedJobs();
        $this->command->setContainer($this->container);
    }

    /**
     * Test if command send friendly message when no job is found.
     */
    public function testExecuteNoJobFound()
    {
        /** @var MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['failedJobStatistics'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('failedJobStatistics')
            ->will($this->returnValue([]));

        $this->container['dispatcher'] = new JobsDispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('failed_jobs');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $this->assertMatchesRegularExpression('/No failed jobs found/', $commandTester->getDisplay());
    }

    /**
     * Test if unexpected exception from queue is handel.
     */
    public function testExecuteThrowErrorOnQueueCall()
    {
        $error_message = 'Expected test exception.';

        /** @var MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['failedJobStatistics'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('failedJobStatistics')
            ->will($this->throwException(new Exception($error_message)));

        $this->container['dispatcher'] = new JobsDispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);
        $command = $application->find('failed_jobs');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $this->assertMatchesRegularExpression("/$error_message/", $commandTester->getDisplay());
    }
    /**
     * Test data is displayed correctly.
     */
    public function testExecuteDisplayCorrectResponse()
    {
        /** @var MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['failedJobStatistics'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('failedJobStatistics')
            ->will($this->returnValue([
                'type1' => [
                    '2.4.2015' => 3,
                    '2.5.2015' => 12377,
                    '2.6.2015' => 1,
                ],
                'type2' => [
                    '2.7.2015' => 91,
                ],
            ]));

        $this->container['dispatcher'] = new JobsDispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);
        $command = $application->find('failed_jobs');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $this->assertMatchesRegularExpression('/type1/', $commandTester->getDisplay());
        $this->assertMatchesRegularExpression('/2.4.2015/', $commandTester->getDisplay());
        $this->assertMatchesRegularExpression('/12377/', $commandTester->getDisplay());
        $this->assertMatchesRegularExpression('/91/', $commandTester->getDisplay());
    }
}
