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

namespace ActiveCollab\JobsQueue\Test;

use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Queue\QueueInterface;
use ActiveCollab\JobsQueue\Test\Base\IntegratedMySqlQueueTest;
use ActiveCollab\JobsQueue\Test\Jobs\Failing;
use ActiveCollab\JobsQueue\Test\Jobs\Inc;
use InvalidArgumentException;

class ChannelsTest extends IntegratedMySqlQueueTest
{
    /**
     * Test if main channel is registered by default.
     */
    public function testMainChannelIsRegisteredByDefault(): void
    {
        $this->assertSame([QueueInterface::MAIN_CHANNEL], $this->dispatcher->getRegisteredChannels());
    }

    /**
     * Channels can be registered.
     */
    public function testChannelsCanBeRegistered(): void
    {
        $this->assertFalse($this->dispatcher->isChannelRegistered('new'));
        $this->dispatcher->registerChannel('new');
        $this->assertTrue($this->dispatcher->isChannelRegistered('new'));
        $this->assertSame([QueueInterface::MAIN_CHANNEL, 'new'], $this->dispatcher->getRegisteredChannels());
    }

    /**
     * Test if multiple channels can be registered.
     */
    public function testMultipleChannelsCanBeRegistered(): void
    {
        $this->assertFalse($this->dispatcher->isChannelRegistered('old'));
        $this->assertFalse($this->dispatcher->isChannelRegistered('new'));

        $this->dispatcher->registerChannels('old', 'new');

        $this->assertTrue($this->dispatcher->isChannelRegistered('old'));
        $this->assertTrue($this->dispatcher->isChannelRegistered('new'));

        $this->assertSame([QueueInterface::MAIN_CHANNEL, 'old', 'new'], $this->dispatcher->getRegisteredChannels());
    }

    public function testChannelWithTheSameNameCantBeRegisteredTwice(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->dispatcher->registerChannel('new');
        $this->dispatcher->registerChannel('new');
    }

    public function testChannelCantBeEmptyOnDispatch(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->dispatcher->dispatch(new Inc(['number' => 123]), '    ');
    }

    public function testJobCantBeDispatchedToAnUnknownChannelByDefault(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->dispatcher->dispatch(new Inc(['number' => 123]), 'not registered');
    }

    /**
     * Test if we can turn off exception for unregistered channel.
     */
    public function testJobDispatchedToUnknownChannelGoToDefaultChannel(): void
    {
        $this->assertTrue($this->dispatcher->getExceptionOnUnregisteredChannel());
        $this->dispatcher->exceptionOnUnregisteredChannel(false);
        $this->assertFalse($this->dispatcher->getExceptionOnUnregisteredChannel());

        $job_id = $this->dispatcher->dispatch(new Inc(['number' => 123]), 'not registered');

        $job = $this->dispatcher->getQueue()->getJobById($job_id);

        $this->assertInstanceOf(Inc::class, $job);
        $this->assertEquals(QueueInterface::MAIN_CHANNEL, $job->getChannel());
    }

    /**
     * Test dispatching the same job to multiple channels.
     */
    public function testDispatchJobToMultipleChannels(): void
    {
        $this->dispatcher->registerChannels('second', 'third');

        $job = new Inc(['number' => 123]);

        $this->dispatcher->dispatch($job, QueueInterface::MAIN_CHANNEL);
        $this->dispatcher->dispatch($job, 'second');
        $this->dispatcher->dispatch($job, 'third');

        $this->assertEquals(3, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->getQueue()->countByChannel(QueueInterface::MAIN_CHANNEL));
        $this->assertEquals(1, $this->dispatcher->getQueue()->countByChannel('second'));
        $this->assertEquals(1, $this->dispatcher->getQueue()->countByChannel('third'));
    }

    /**
     * Test next in line from any channel.
     */
    public function testNextInLineFromAnyChannel(): void
    {
        $this->dispatcher->registerChannels('second', 'third');

        $this->dispatcher->dispatch(new Inc(['number' => 1]), QueueInterface::MAIN_CHANNEL);
        $this->dispatcher->dispatch(new Inc(['number' => 2]), 'second');
        $this->dispatcher->dispatch(new Inc(['number' => 3]), 'third');

        /** @var Inc $next_in_line */
        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
        $this->assertEquals(1, $next_in_line->getData()['number']);
    }

    /**
     * test next in line from a single channel.
     */
    public function testNextInLineFromSingleChannel(): void
    {
        $this->dispatcher->registerChannels('second', 'third');

        $this->dispatcher->dispatch(new Inc(['number' => 1]), QueueInterface::MAIN_CHANNEL);
        $this->dispatcher->dispatch(new Inc(['number' => 2]), 'second');
        $this->dispatcher->dispatch(new Inc(['number' => 3]), 'third');

        /** @var Inc $next_in_line */
        $next_in_line = $this->dispatcher->getQueue()->nextInLine('third');

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
        $this->assertEquals(3, $next_in_line->getData()['number']);
    }

    /**
     * Test next in line form a list of channels (order of channel names should not be relevant).
     */
    public function testNextInLineFromMultipleChannels(): void
    {
        $this->dispatcher->registerChannels('second', 'third');

        $this->dispatcher->dispatch(new Inc(['number' => 1]), QueueInterface::MAIN_CHANNEL);
        $this->dispatcher->dispatch(new Inc(['number' => 2]), 'second');
        $this->dispatcher->dispatch(new Inc(['number' => 3]), 'third');

        /** @var Inc $next_in_line */
        $next_in_line = $this->dispatcher->getQueue()->nextInLine('third', 'second');

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
        $this->assertEquals(2, $next_in_line->getData()['number']);
    }

    /**
     * Test if failed jobs keep their channel information.
     */
    public function testFailedJobKeepsChannel(): void
    {
        $this->dispatcher->registerChannels('second');

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->dispatch(new Failing(), 'second'));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->getQueue()->countFailed());

        $job = $this->connection->executeFirstRow('SELECT * FROM `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '`');

        $this->assertIsArray($job);
        $this->assertEquals('second', $job['channel']);
    }

    /**
     * Test if failed job restoration keeps the original job channel.
     */
    public function testRestoredFailedJobKeepsChannel(): void
    {
        $this->dispatcher->registerChannels('second');

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->dispatch(new Failing(), 'second'));

        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $next_in_line);
        $this->assertEquals(1, $next_in_line->getQueueId());

        $this->dispatcher->getQueue()->execute($next_in_line);

        $this->assertEquals(0, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->getQueue()->countFailed());

        $job = $this->connection->executeFirstRow('SELECT * FROM `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '`');

        $this->assertIsArray($job);
        $this->assertEquals('second', $job['channel']);

        $this->dispatcher->getQueue()->restoreFailedJobById(1);

        $this->assertEquals(1, $this->dispatcher->getQueue()->count());
        $this->assertEquals(0, $this->dispatcher->getQueue()->countFailed());

        $job = $this->connection->executeFirstRow('SELECT * FROM `' . MySqlQueue::JOBS_TABLE_NAME . '`');

        $this->assertIsArray($job);
        $this->assertEquals('second', $job['channel']);
    }
}
