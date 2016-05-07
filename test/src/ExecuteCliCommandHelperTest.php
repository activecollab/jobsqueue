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

use ActiveCollab\JobsQueue\Test\Jobs\ExecuteCliCommandHelperJob;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class ExecuteCliCommandHelperTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage 'command' property is required
     */
    public function testCommandIsRequired()
    {
        new ExecuteCliCommandHelperJob([]);
    }

    /**
     * Test if command without arguments is properly prepared.
     */
    public function testCommandWithoutOptionsAndArguments()
    {
        $this->assertEquals('php', (new ExecuteCliCommandHelperJob(['command' => 'php']))->execute());
    }

    /**
     * Test if command arguments are properly prepared.
     */
    public function testCommandArguments()
    {
        $job = new ExecuteCliCommandHelperJob([
            'command' => 'php',
            'command_arguments' => [
                '-v',                    // as is
                '--debug',               // as is, second variation
                'treat as argument',     // argument
                's' => '127.0.0.1:8888', // option with value
                'p' => [1, 2, 3],         // option with array value
            ],
        ]);

        $this->assertEquals("php -v --debug 'treat as argument' --s='127.0.0.1:8888' --p='1,2,3'", $job->execute());
    }
}
