<?php

namespace ActiveCollab\JobsQueue\Test;

use ActiveCollab\JobsQueue\Queue\QueueInterface;
use ActiveCollab\JobsQueue\Test\Jobs\Inc;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class ChannelsTest extends AbstractMySqlQueueTest
{
    /**
     * Test if main channel is registered by default
     */
    public function testMainChannelIsRegisteredByDefault()
    {
        $this->assertSame([QueueInterface::MAIN_CHANNEL], $this->dispatcher->getRegisteredChannels());
    }

    /**
     * Channels can be registered
     */
    public function testChannelsCanBeRegistered()
    {
        $this->assertFalse($this->dispatcher->isChannelRegistered('new'));
        $this->dispatcher->registerChannel('new');
        $this->assertTrue($this->dispatcher->isChannelRegistered('new'));
        $this->assertSame([QueueInterface::MAIN_CHANNEL, 'new'], $this->dispatcher->getRegisteredChannels());
    }

    /**
     * Test if multiple channels can be registered
     */
    public function testMultipleChannelsCanBeRegistered()
    {
        $this->assertFalse($this->dispatcher->isChannelRegistered('old'));
        $this->assertFalse($this->dispatcher->isChannelRegistered('new'));

        $this->dispatcher->registerChannels(['old', 'new']);

        $this->assertTrue($this->dispatcher->isChannelRegistered('old'));
        $this->assertTrue($this->dispatcher->isChannelRegistered('new'));

        $this->assertSame([QueueInterface::MAIN_CHANNEL, 'old', 'new'], $this->dispatcher->getRegisteredChannels());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testChannelWithTheSameNameCantBeRegisteredTwice()
    {
        $this->dispatcher->registerChannel('new');
        $this->dispatcher->registerChannel('new');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testChannelCantBeEmptyOnDispatch()
    {
        $this->dispatcher->dispatch(new Inc(['number' => 123]), '    ');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testChannelCantBeEmptyOnExecute()
    {
        $this->dispatcher->execute(new Inc(['number' => 123]), '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testChannelNeedsCantBeAnArrayOfChannels()
    {
        $this->dispatcher->dispatch(new Inc(['number' => 123]), [QueueInterface::MAIN_CHANNEL, 'another_channel']);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testJobCantBeDispatchedToAnUnknownChannel()
    {
        $this->dispatcher->dispatch(new Inc(['number' => 123]), 'not registered');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testJobCantBeExecutedInAnUnknownChannel()
    {
        $this->dispatcher->execute(new Inc(['number' => 123]), 'not registered');
    }

    /**
     * Test dispatching the same job to multiple channels
     */
    public function testDispatchJobToMultipleChannels()
    {
        $this->dispatcher->registerChannels(['second', 'third']);

        $job = new Inc(['number' => 123]);

        $this->dispatcher->dispatch($job, QueueInterface::MAIN_CHANNEL);
        $this->dispatcher->dispatch($job, 'second');
        $this->dispatcher->dispatch($job, 'third');

        $this->assertEquals(3, $this->dispatcher->getQueue()->count());
        $this->assertEquals(1, $this->dispatcher->getQueue()->countByChannel(QueueInterface::MAIN_CHANNEL));
        $this->assertEquals(1, $this->dispatcher->getQueue()->countByChannel('second'));
        $this->assertEquals(1, $this->dispatcher->getQueue()->countByChannel('third'));
    }

    public function testNextInLineFromAnyChannel()
    {
        $this->dispatcher->registerChannels(['second', 'third']);

        $this->dispatcher->dispatch(new Inc(['number' => 1]), QueueInterface::MAIN_CHANNEL);
        $this->dispatcher->dispatch(new Inc(['number' => 2]), 'second');
        $this->dispatcher->dispatch(new Inc(['number' => 3]), 'third');

        /** @var Inc $next_in_line */
        $next_in_line = $this->dispatcher->getQueue()->nextInLine();

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
        $this->assertEquals(1, $next_in_line->getData()['number']);
    }

    public function testNextInLineFromSingleChannel()
    {
        $this->dispatcher->registerChannels(['second', 'third']);

        $this->dispatcher->dispatch(new Inc(['number' => 1]), QueueInterface::MAIN_CHANNEL);
        $this->dispatcher->dispatch(new Inc(['number' => 2]), 'second');
        $this->dispatcher->dispatch(new Inc(['number' => 3]), 'third');

        /** @var Inc $next_in_line */
        $next_in_line = $this->dispatcher->getQueue()->nextInLine(['third']);

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
        $this->assertEquals(3, $next_in_line->getData()['number']);
    }

    public function testNextInLineFromMultipleChannels()
    {
        $this->dispatcher->registerChannels(['second', 'third']);

        $this->dispatcher->dispatch(new Inc(['number' => 1]), QueueInterface::MAIN_CHANNEL);
        $this->dispatcher->dispatch(new Inc(['number' => 2]), 'second');
        $this->dispatcher->dispatch(new Inc(['number' => 3]), 'third');

        /** @var Inc $next_in_line */
        $next_in_line = $this->dispatcher->getQueue()->nextInLine(['third', 'second']);

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Inc', $next_in_line);
        $this->assertEquals(2, $next_in_line->getData()['number']);
    }

//    public function testFailedJobKeepsChannel()
//    {
//
//    }
//
//    public function testRestoredFailedJobKeepsChannel()
//    {
//
//    }
}