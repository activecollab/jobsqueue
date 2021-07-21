<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Test;

use ActiveCollab\JobsQueue\Test\Jobs\PortHelperJob;
use InvalidArgumentException;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class PortHelperTest extends TestCase
{
    /**
     * Test if port is properly set when it is omitted.
     */
    public function testGetDefaultPortWhenPropertyIsMissing()
    {
        $job = new PortHelperJob();
        $this->assertSame(PortHelperJob::DEFAULT_PORT, $job->getData()['port']);
    }

    /**
     * Test good port.
     */
    public function testGoodPort()
    {
        $job = new PortHelperJob(['port' => 4321]);
        $this->assertSame(4321, $job->getData()['port']);
    }

    /**
     * Test good port, passed as string.
     */
    public function testGoodPortAsString()
    {
        $job = new PortHelperJob(['port' => '4321']);
        $this->assertSame(4321, $job->getData()['port']);
    }

    public function testPortIsLessThan1()
    {
        $this->expectException(InvalidArgumentException::class);

        new PortHelperJob(['port' => -1]);
    }

    public function testPortIsLessThan1String()
    {
        $this->expectException(InvalidArgumentException::class);

        new PortHelperJob(['port' => '-1']);
    }

    public function testPortIsNotNumericString()
    {
        $this->expectException(InvalidArgumentException::class);

        new PortHelperJob(['port' => 'Some Strange Value']);
    }
}
