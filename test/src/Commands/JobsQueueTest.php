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

use ActiveCollab\JobsQueue\Command\JobsQueue;
use ActiveCollab\JobsQueue\Dispatcher;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @package ActiveCollab\JobQueue\Test\Commands
 */
class JobsQueueTest extends TestCase
{
    /**
     * @var JobsQueue
     */
    private $command;

    public function setUp(): void
    {
        parent::setUp();

        $this->command = new JobsQueue();
        $this->command->setContainer($this->container);
    }

    /**
     * Test if command send friendly message when no job is found.
     */
    public function testExecuteNoJobFound()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['countJobsByType'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('countJobsByType')
            ->will($this->returnValue([]));

        $this->container['dispatcher'] = new Dispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('jobs_queue');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $this->assertMatchesRegularExpression('/No jobs in the queue/', $commandTester->getDisplay());
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
            ->setMethods(['countJobsByType'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('countJobsByType')
            ->will($this->throwException(new Exception($error_message)));

        $this->container['dispatcher'] = new Dispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('jobs_queue');
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
            ->setMethods(['countJobsByType'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('countJobsByType')
            ->will($this->returnValue([
                'type1' => 123,
                'type2' => 345,
            ]));

        $this->container['dispatcher'] = new Dispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('jobs_queue');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $this->assertMatchesRegularExpression('/type1/', $commandTester->getDisplay());
        $this->assertMatchesRegularExpression('/123/', $commandTester->getDisplay());
        $this->assertMatchesRegularExpression('/type2/', $commandTester->getDisplay());
        $this->assertMatchesRegularExpression('/345/', $commandTester->getDisplay());
    }
}
