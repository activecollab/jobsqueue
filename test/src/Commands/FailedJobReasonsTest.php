<?php

namespace ActiveCollab\JobQueue\Test\Commands;

use ActiveCollab\JobsQueue\Dispatcher;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use ActiveCollab\JobsQueue\Command\FailedJobReasons;
use Exception;

/**
 * @package ActiveCollab\JobQueue\Test\Commands
 */
class FailedJobReasonsTest extends TestCase
{
    /**
     * @var \ActiveCollab\JobsQueue\Command\FailedJobReasons
     */
    private $command;

    /**
     * Set up test environment
     */
    public function setUp(){
        parent::setUp();

        $this->command =  new FailedJobReasons();
        $this->command->setContainer($this->container);
    }

    /**
     * Test search for not existing type
     */
    public function testExecuteNoJobFound(){
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('failed_job_reasons');
        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
            'type'    => 'not-existing-type-on-acctivecollab'
        ]);

        $this->assertRegExp('/No job type that matches type argument found under failed jobs/', $command_tester->getDisplay());
    }

    /**
     * Test if unexpected exception is handled
     */
    public function testExecuteThrowErrorToDisplay()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['unfurlType'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('unfurlType')
            ->will($this->throwException(new Exception('Expected test exception.')));

        $this->container['dispatcher'] = new Dispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('failed_job_reasons');
        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
            'type'    => 'type_one'
        ]);
        $this->assertContains('Expected test exception.', $command_tester->getDisplay());
    }

    /**
     * Test if more then one job is found
     */
    public function testExecuteThrowErrorOnMoreThenOneJobFound(){

        /** @var \PHPUnit_Framework_MockObject_MockObject|QueueInterface $mock_queue */
        $mock_queue = $this->getMockBuilder('ActiveCollab\\JobsQueue\\Queue\\MySqlQueue')
            ->disableOriginalConstructor()
            ->setMethods(['unfurlType'])
            ->getMock();

        $mock_queue->expects($this->any())
            ->method('unfurlType')
            ->will($this->returnValue(['type1','type2']));

        $this->container['dispatcher'] = new Dispatcher($mock_queue);

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('failed_job_reasons');
        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
            'type'    => 'type_one'
        ]);
        $this->assertContains('More than one job type found', $command_tester->getDisplay());
    }
}
