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
use ActiveCollab\JobsQueue\Command\FailedJobReasons;
use ActiveCollab\JobsQueue\JobsDispatcher;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class FailedJobReasonsTest extends IntegratedContainerTestCase
{
    /**
     * @var FailedJobReasons
     */
    private $command;

    public function setUp(): void
    {
        parent::setUp();

        $this->command = new FailedJobReasons();
        $this->command->setContainer($this->container);
    }

    /**
     * Test search for not existing type.
     */
    public function testExecuteNoJobFound()
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('failed_job_reasons');
        $command_tester = new CommandTester($command);
        $command_tester->execute(
            [
                'command' => $command->getName(),
                'type' => 'not-existing-type-on-acctivecollab',
            ]
        );

        $this->assertMatchesRegularExpression('/No job type that matches type argument found under failed jobs/', $command_tester->getDisplay());
    }

    /**
     * Test if unexpected exception is handled.
     */
    public function testExecuteThrowErrorToDisplay()
    {
        /** @var MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['unfurlType'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('unfurlType')
            ->will($this->throwException(new Exception('Expected test exception.')));

        $this->container['dispatcher'] = new JobsDispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('failed_job_reasons');
        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
            'type' => 'type_one',
        ]);
        $this->assertStringContainsString('Expected test exception.', $command_tester->getDisplay());
    }

    /**
     * Test if more then one job is found.
     */
    public function testExecuteThrowErrorOnMoreThenOneJobFound()
    {

        /** @var MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['unfurlType'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('unfurlType')
            ->will($this->returnValue(['type1', 'type2']));

        $this->container['dispatcher'] = new JobsDispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('failed_job_reasons');
        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
            'type' => 'type_one',
        ]);
        $this->assertStringContainsString('More than one job type found', $command_tester->getDisplay());
    }
}
