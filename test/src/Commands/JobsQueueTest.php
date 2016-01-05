<?php

namespace ActiveCollab\JobQueue\Test\Commands;

use ActiveCollab\JobsQueue\Command\JobsQueue;
use ActiveCollab\JobsQueue\Dispatcher;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Exception;

/**
 * @package ActiveCollab\JobQueue\Test\Commands
 */
class JobsQueueTest extends TestCase
{
    /**
     * @var JobsQueue
     */
    private $command;

    /**
     * Set up test environment
     */
    public function setUp(){
        parent::setUp();

        $this->command =  new JobsQueue();
        $this->command->setContainer($this->container);
    }

    /**
     * Test if command send friendly message when no job is found
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
        $this->assertRegExp('/No jobs in the queue/', $commandTester->getDisplay());

    }
    /**
     * Test if unexpected exception from queue is handel
     */
    public function testExecuteThrowErrorOnQueueCall()
    {
        $error_message = 'Expected test exception.';

        /** @var \PHPUnit_Framework_MockObject_MockObject|QueueInterface $mock_queue */
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
        $this->assertRegExp("/$error_message/", $commandTester->getDisplay());

    }
    /**
     * Test data is displayed correctly
     */
    public function testExecuteDisplayCorrectResponse()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['countJobsByType'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('countJobsByType')
            ->will($this->returnValue([
                'type1' => 123,
                'type2' => 345
            ]));

        $this->container['dispatcher'] = new Dispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('jobs_queue');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $this->assertRegExp('/type1/', $commandTester->getDisplay());
        $this->assertRegExp('/123/', $commandTester->getDisplay());
        $this->assertRegExp('/type2/', $commandTester->getDisplay());
        $this->assertRegExp('/345/', $commandTester->getDisplay());
    }
}
