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

use ActiveCollab\JobsQueue\Batches\Batch;
use ActiveCollab\JobsQueue\Batches\BatchInterface;
use ActiveCollab\JobsQueue\Test\Jobs\Inc;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
class BatchesTest extends AbstractMySqlQueueTest
{
    /**
     * Test if job batches table exists.
     */
    public function testBatchesTableExists()
    {
        $this->assertTrue($this->connection->tableExists('job_batches'));
    }

    /**
     * Test if call to Dispatcher's batch method creates a batch.
     */
    public function testDispatcherBatchCallCreatesBatch()
    {
        $this->assertEquals(0, $this->dispatcher->countBatches());

        $batch = $this->dispatcher->batch('Testing batch');
        $this->assertInstanceOf(BatchInterface::class, $batch);
        $this->assertEquals(1, $batch->getQueueId());

        $this->assertEquals(1, $this->dispatcher->countBatches());
    }

    /**
     * Test if batch can be set for an unsaved job.
     */
    public function testBatchCanBeSetForUnqueuedJob()
    {
        $batch = $this->dispatcher->batch('Testing batch');
        $this->assertEquals(1, $batch->getQueueId());

        $job = new Inc(['number' => 123]);
        $this->assertNull($job->getBatchId());

        $job->setBatch($batch);

        $this->assertEquals($batch->getQueueId(), $job->getBatchId());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testBatchCantBeSetForQueuedJob()
    {
        $batch = $this->dispatcher->batch('Testing batch');
        $this->assertEquals(1, $batch->getQueueId());

        $job = new Inc(['number' => 123]);

        $job_id = $this->dispatcher->dispatch($job);

        /** @var Inc $job */
        $job = $this->dispatcher->getQueue()->getJobById($job_id);
        $this->assertInstanceOf(Inc::class, $job);
        $this->assertNotEmpty($job->getQueueId());

        $job->setBatch($batch);
    }

    public function testCountBatchJobs()
    {
        $batch = $this->dispatcher->batch('Testing batch', function (Batch &$batch) {
            for ($i = 1; $i <= 5; ++$i) {
                $batch->dispatch(new Inc(['number' => $i]));
            }
        });

        $this->assertEquals(5, $this->dispatcher->getQueue()->count());
        $this->assertEquals(5, $batch->countJobs());
    }

    public function testCountBatchProgress()
    {
        $batch = $this->dispatcher->batch('Testing batch', function (Batch &$batch) {
            for ($i = 1; $i <= 5; ++$i) {
                $batch->dispatch(new Inc(['number' => $i]));
            }
        });

        $this->assertEquals(5, $batch->countJobs());
        $this->assertEquals(5, $batch->countPendingJobs());
        $this->assertEquals(0, $batch->countFailedJobs());
        $this->assertEquals(0, $batch->countCompletedJobs());

        $this->assertFalse($batch->isComplete());

        $this->dispatcher->executeNextInLine();
        $this->dispatcher->executeNextInLine();

        $this->assertEquals(5, $batch->countJobs());

        $this->assertEquals(5, $batch->countJobs());
        $this->assertEquals(3, $batch->countPendingJobs());
        $this->assertEquals(0, $batch->countFailedJobs());
        $this->assertEquals(2, $batch->countCompletedJobs());

        $this->assertFalse($batch->isComplete());
    }

    public function testAllBatchJobsExecution()
    {
        $batch = $this->dispatcher->batch('Testing batch', function (Batch &$batch) {
            for ($i = 1; $i <= 5; ++$i) {
                $batch->dispatch(new Inc(['number' => $i]));
            }
        });

        $this->assertEquals(5, $batch->countJobs());
        $this->assertEquals(5, $batch->countPendingJobs());
        $this->assertEquals(0, $batch->countFailedJobs());
        $this->assertEquals(0, $batch->countCompletedJobs());

        $this->assertFalse($batch->isComplete());

        $this->dispatcher->executeNextInLine();
        $this->dispatcher->executeNextInLine();
        $this->dispatcher->executeNextInLine();
        $this->dispatcher->executeNextInLine();
        $this->dispatcher->executeNextInLine();

        $this->assertEquals(5, $batch->countJobs());

        $this->assertEquals(5, $batch->countJobs());
        $this->assertEquals(0, $batch->countPendingJobs());
        $this->assertEquals(0, $batch->countFailedJobs());
        $this->assertEquals(5, $batch->countCompletedJobs());

        $this->assertTrue($batch->isComplete());
    }
}
