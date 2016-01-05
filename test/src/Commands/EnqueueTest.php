<?php

namespace ActiveCollab\JobQueue\Test\Commands;

use ActiveCollab\JobsQueue\Command\Enqueue;
use ActiveCollab\JobsQueue\Test\Jobs\Inc;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use ActiveCollab\JobsQueue\Command\CreateTables;

/**
 * @package ActiveCollab\JobQueue\Test\Commands
 */
class EnqueueTest extends TestCase
{
    /**
     * @var CreateTables
     */
    private $command;

    /**
     * {@inheritdoc}
     */
    public function setUp(){
        parent::setUp();

        $this->command =  new Enqueue();
        $this->command->setContainer($this->container);
    }

    /**
     * @expectedException \Symfony\Component\Console\Exception\RuntimeException
     * @expectedExceptionMessage Not enough arguments (missing: "type").
     */
    public function testTypeArgumentIsRequired()
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('enqueue');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
    }

    /**
     * Test if command rejects invalid job type
     */
    public function testInvalidJobTypeExit()
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('enqueue');

        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
            'type' => get_class($this),
        ]);

        $this->assertEquals(1, $command_tester->getStatusCode());
        $this->assertContains('Valid job class expected', $command_tester->getDisplay());
    }

    /**
     * Test if command rejects invalid data (malformatted JSON)
     */
    public function testInvalidJsonExit()
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('enqueue');

        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
            'type' => Inc::class,
            '--data' => '{"invalid"json'
        ]);

        $this->assertEquals(1, $command_tester->getStatusCode());
        $this->assertContains('Failed to parse JSON. Reason: Syntax error', $command_tester->getDisplay());
    }

    /**
     * Test if invalid job data throws an error
     */
    public function testInvalidJobDataExit()
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('enqueue');

        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
            'type' => Inc::class,
            '--data' => '{"here":"you go"}'
        ]);

        $this->assertEquals(1, $command_tester->getStatusCode());
        $this->assertContains('Number is required and it needs to be an integer value', $command_tester->getDisplay());
    }

    /**
     * Test a successful command run
     */
    public function testRunsOk()
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('enqueue');

        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
            'type' => Inc::class,
            '--data' => '{"number":12}'
        ]);

        $this->assertEquals(0, $command_tester->getStatusCode());
        $this->assertContains('Job #1 enqueued', $command_tester->getDisplay());

        $this->assertEquals(1, $this->dispatcher->getQueue()->count());

        $job = $this->dispatcher->getQueue()->getJobById(1);

        $this->assertInstanceOf(Inc::class, $job);
        $this->assertEquals(12, $job->getData()['number']);
    }
}
