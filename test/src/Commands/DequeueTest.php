<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Test\Commands;

use ActiveCollab\JobsQueue\Test\Base\IntegratedContainerTestCase;
use ActiveCollab\JobsQueue\Command\CreateTables;
use ActiveCollab\JobsQueue\Command\Dequeue;
use ActiveCollab\JobsQueue\Test\Jobs\Inc;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

class DequeueTest extends IntegratedContainerTestCase
{
    /**
     * @var CreateTables
     */
    private $command;

    public function setUp(): void
    {
        parent::setUp();

        $this->command = new Dequeue();
        $this->command->setContainer($this->container);
    }

    public function testTypeArgumentIsRequired()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Not enough arguments (missing: \"type\").");

        $application = new Application();
        $application->add($this->command);

        $command = $application->find('dequeue');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
    }

    /**
     * Test if command rejects invalid job type.
     */
    public function testInvalidJobTypeExit()
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('dequeue');

        $command_tester = new CommandTester($command);
        $command_tester->execute([
            'command' => $command->getName(),
            'type' => get_class($this),
        ]);

        $this->assertEquals(1, $command_tester->getStatusCode());
        $this->assertStringContainsString('Valid job class expected', $command_tester->getDisplay());
    }

    /**
     * Test a successful command run.
     */
    public function testRunsOk()
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('dequeue');
        $command_tester = new CommandTester($command);

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->dispatcher->dispatch(
            new Inc(['number' => 12,
        ]));
        $this->assertEquals(1, $this->dispatcher->getQueue()->count());

        $command_tester->execute([
            'command' => $command->getName(),
            'type' => Inc::class,
        ]);
        $this->assertEquals(0, $command_tester->getStatusCode());
        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
    }
}
