<?php

namespace ActiveCollab\JobQueue\Test\Commands;

use ActiveCollab\JobsQueue\Command\FailedJobs;
use ActiveCollab\JobsQueue\Dispatcher;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Exception;

/**
 * @package ActiveCollab\JobQueue\Test\Commands
 */
class FailedJobsTest extends TestCase
{
    private $command;

    /**
     * Set up test environment
     */
    public function setUp(){
        parent::setUp();

        $this->command =  new FailedJobs();
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
            ->setMethods(['failedJobStatistics'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('failedJobStatistics')
            ->will($this->returnValue([]));

        $this->container['dispatcher'] = new Dispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('failed_jobs');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $this->assertRegExp('/No failed jobs found/', $commandTester->getDisplay());
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
            ->setMethods(['failedJobStatistics'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('failedJobStatistics')
            ->will($this->throwException(new Exception($error_message)));

        $this->container['dispatcher'] = new Dispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);
        $command = $application->find('failed_jobs');
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
                ]
            ]));

        $this->container['dispatcher'] = new Dispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);
        $command = $application->find('failed_jobs');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $this->assertRegExp('/type1/', $commandTester->getDisplay());
        $this->assertRegExp('/2.4.2015/', $commandTester->getDisplay());
        $this->assertRegExp('/12377/', $commandTester->getDisplay());
        $this->assertRegExp('/91/', $commandTester->getDisplay());
    }
}
