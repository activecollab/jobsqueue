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

use ActiveCollab\JobsQueue\Jobs\JobInterface;
use ActiveCollab\JobsQueue\Test\Jobs\Inc;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class JobDataTest extends TestCase
{
    /**
     * Check if data returns all properties.
     */
    public function testDataReturnsAllProperties()
    {
        $this->assertEquals(['number' => 1245, 'priority' => JobInterface::NOT_A_PRIORITY], (new Inc(['number' => 1245]))->getData());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage When provided, property can't be an empty value
     */
    public function testDataPropertyCannotBeEmpty()
    {
        (new Inc(['number' => 1245]))->getData('');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Property 'unknown property here' not found
     */
    public function testDataPropertyMustExist()
    {
        (new Inc(['number' => 1245]))->getData('unknown property here');
    }

    /**
     * Test if we can get a particular property from job data.
     */
    public function testDataGetIndividualPropety()
    {
        $this->assertEquals(1245, (new Inc(['number' => 1245]))->getData('number'));
    }

    /**
     * Test to confirm that empty property value is not interprted as missing property.
     */
    public function testEmptyPropertyIsNotMisinterpretedAsNotPresent()
    {
        $this->assertSame(0, (new Inc(['number' => 0]))->getData('number'));
    }
}
