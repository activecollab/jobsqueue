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

use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use ActiveCollab\JobsQueue\Test\Jobs\Failing;
use RuntimeException;

class RestoreFailedJobTest extends AbstractMySqlQueueTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->assertFailedRecordsCount(0);

        $this->dispatcher->dispatch(new Failing(['property1' => 'value1']));
        $this->dispatcher->dispatch(new Failing(['property2' => 'value2']));

        $this->dispatcher->getQueue()->execute($this->dispatcher->getQueue()->nextInLine());
        $this->dispatcher->getQueue()->execute($this->dispatcher->getQueue()->nextInLine());

        $this->assertFailedRecordsCount(2);
    }

    public function testRestoreByIdReturnsNullForNonExistingJob()
    {
        $this->expectException(RuntimeException::class);

        $this->dispatcher->getQueue()->restoreFailedJobById(1234);
    }

    public function testExceptinOnInvalidJobType()
    {
        $this->expectException(RuntimeException::class);

        $this->connection->execute('UPDATE `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '` SET `type` = ? WHERE `id` = ?', 'ThisClassDoesNotExist', 1);
        $this->dispatcher->getQueue()->restoreFailedJobById(1);
    }

    public function testExceptionOnInvalidJson()
    {
        $this->expectException(RuntimeException::class);

        $this->connection->execute('UPDATE `' . MySqlQueue::FAILED_JOBS_TABLE_NAME . '` SET `data` = ? WHERE `id` = ?', '{invalidJSON', 1);

        $this->dispatcher->getQueue()->restoreFailedJobById(1234);
    }

    /**
     * Test job failure.
     */
    public function testRestoreById()
    {
        $this->assertRecordsCount(0);

        /** @var Failing $job */
        $job = $this->dispatcher->getQueue()->restoreFailedJobById(1);

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $job);

        $this->assertEquals('value1', $job->getData()['property1']);

        $this->assertRecordsCount(1);
        $this->assertFailedRecordsCount(1);
    }

    /**
     * Test job failure.
     */
    public function testRestoreByIdWithDataUpdate()
    {
        /** @var Failing $job */
        $job = $this->dispatcher->getQueue()->restoreFailedJobById(1, ['attempts' => 5, 'first_attempt_delay' => 0, 'property1' => 'new value']);

        $this->assertInstanceOf('ActiveCollab\JobsQueue\Test\Jobs\Failing', $job);

        $this->assertEquals(5, $job->getData()['attempts']);
        $this->assertEquals(0, $job->getData()['first_attempt_delay']);
        $this->assertEquals('new value', $job->getData()['property1']);
    }

    /**
     * Test restore failed jobs by job type.
     */
    public function testRestoreByJobType()
    {
        $this->assertRecordsCount(0);

        $this->dispatcher->getQueue()->restoreFailedJobsByType('Failing');

        $this->assertRecordsCount(2);
        $this->assertFailedRecordsCount(0);
    }
}
