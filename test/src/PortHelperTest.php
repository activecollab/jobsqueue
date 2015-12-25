<?php

namespace ActiveCollab\JobsQueue\Test;

use ActiveCollab\JobsQueue\Test\Jobs\PortHelperJob;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class PortHelperTest extends TestCase
{
    /**
     * Test if port is properly set when it is omitted
     */
    public function testGetDefaultPortWhenPropertyIsMissing()
    {
        $job = new PortHelperJob();
        $this->assertSame(PortHelperJob::DEFAULT_PORT, $job->getData()['port']);
    }

    /**
     * Test good port
     */
    public function testGoodPort()
    {
        $job = new PortHelperJob(['port' => 4321]);
        $this->assertSame(4321, $job->getData()['port']);
    }

    /**
     * Test good port, passed as string
     */
    public function testGoodPortAsString()
    {
        $job = new PortHelperJob(['port' => '4321']);
        $this->assertSame(4321, $job->getData()['port']);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPortIsLessThan1()
    {
        new PortHelperJob(['port' => -1]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPortIsLessThan1String()
    {
        new PortHelperJob(['port' => '-1']);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPortIsNotNumericString()
    {
        new PortHelperJob(['port' => 'Some Strange Value']);
    }
}
