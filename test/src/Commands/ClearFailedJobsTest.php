<?php

namespace ActiveCollab\JobQueue\Test\Commands;

use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use ActiveCollab\JobsQueue\Command\ClearFailedJobs;
use ActiveCollab\JobsQueue\Dispatcher;
use Exception;

/**
 * @package ActiveCollab\JobQueue\Test\Commands
 */
class ClearFailedJobsTest extends TestCase
{
    /**
     * @var ClearFailedJobs
     */
    private $command;

    /**
     * {@inheritdoc}
     */
    public function setUp(){
        parent::setUp();

        $this->command =  new ClearFailedJobs();
        $this->command->setContainer($this->container);
    }

    /**
     * Test if execute will delete all records from failed job table
     */
    public function testExecuteRunsOK(){
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('clear_failed_jobs');
        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertRegExp('/Done/', $command_tester->getDisplay());
        $this->assertFailedRecordsCount(0);
    }

    /**
     * Test if unexpected exception  is handel
     */
    public function testExecuteThrowErrorToDisplay(){

        $error_message = 'Expected test exception.';

        /** @var \PHPUnit_Framework_MockObject_MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['clear'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('clear')
            ->will($this->throwException(new Exception($error_message)));

        $this->container['dispatcher'] = new Dispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('clear_failed_jobs');
        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertContains('Expected test exception.', $command_tester->getDisplay());
    }
}
