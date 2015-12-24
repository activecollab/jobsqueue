# JobsQueue Library

[![Build Status](https://travis-ci.org/activecollab/jobsqueue.svg?branch=master)](https://travis-ci.org/activecollab/jobsqueue)

Reason for existence: it's light, with very few dependencies. It can be used with cron + database powered queues for people who are not allowed to run a proper messaging or job management server. Or you can execute jobs through proper messaging or job manager with it.

To install it, use Composer:

```json
{
    "require": {
        "activecollab/jobsqueue": "~0.1"
    }
}
```


This library uses three elements:

1. Dispatcher is here to dispatch jobs,
2. Queues make sure that jobs can be queued,
3. Jobs perform the actual work.

As a demonstration, we'll create a simple job that increments a number:

```php
<?php

use ActiveCollab\JobsQueue\Jobs\Job;

class Inc extends Job
{
    /**
     * Increment a number
     *
     * @return integer
     */
    public function execute()
    {
      return $this->getData()['number'] + 1;
    }
}
```

Tip: To fail an attempt, just throw an exception from within `execute()` method.

Now, lets create a dispatcher instance that manages one MySQL powered queue:

```php
<?php

use ActiveCollab\JobsQueue\Dispatcher;
use ActiveCollab\JobsQueue\Queue\MySqlQueue;
use mysqli;
use RuntimeException;

$database_link = new MySQLi('localhost', 'root', '', 'activecollab_jobs_queue_test');

if ($database_link->connect_error) {
    throw new RuntimeException('Failed to connect to database. MySQL said: ' . $database_link->connect_error);
}

$queue = new MySqlQueue($database_link);

// Not required but gives you flexibility with failure handling
$queue->onJobFailure(function(Job $job, Exception $reason) {
    throw new Exception('Job ' . get_class($job) . ' failed', 0, $reason);
});

$dispatcher = new Dispatcher($queue);
```

Lets add a job to the queue:

```php
$dispatcher->dispatch(new Inc([ 'number' => 123 ]));
```

Code that executes jobs from the queue will get this job as next available job:

```php
$next_in_line = $dispatcher->getQueue()->nextInLine();
$dispatcher->getQueue()->execute($next_in_line);
```

To run a job and wait for the result, use `execute()` instead of `dispatch()`:

```php
$result = $dispatcher->execute(new Inc([ 'number' => 123 ]));
```

When constructing a new `Job` instance, you can set an array of job data, as well as following job properties:

1. `priority` - Value between 0 and 4294967295 that determins how important the job is (a job with higher value has higher priority). Default is 0 (job is not a priority),
2. `attempts` - Number of attempts before job is considered to fail and is removed from the queue. Value can be between 1 and 256. Default is 1 (try once and fail if it does not go well),
3. `delay` - Number of seconds to wait before first execution (in case when `first_attempt_delay` is not set), as well as retries if the job fails and needs to be retried. Value can be between 1 and 3600 (one hour). Default is 0 (no delay),
4. `first_attempt_delay` - Number of seconds to wait before the first job execution.

```php
$job = new Inc([
    'number' => 123,
    'priority'            => Job::HAS_HIGHEST_PRIORITY,
    'attempts'            => 5,
    'delay'               => 5,
    'first_attempt_delay' => 1
]);
```

## Batches

Jobs can be added to the queue in batches. Once in a batch, job queue will execute them as any other job, but you will be able to track progress of batch:

```php
$batch = $dispatcher->batch('Testing batch', function(BatchInterface &$batch) {
    for ($i = 1; $i <= 1000; $i++) {
        $batch->dispatch(new Inc(['number' => $i]));
    }
});

sleep(1);

print $batch->countJobs() . " jobs in a batch\n";
print $batch->countPendingJobs() . " batch jobs still pending for execution\n";
print $batch->countFailedJobs() . " batch jobs have failed to complete\n";
print $batch->countCompletedJobs() . " batch jobs were completed successfully\n";
```

All batches have name, so they are easy to find using command line tools.

## Channels

In some situations, it is useful to have multiple channels and consumer listening on them. For example, you can have a consumer on a mailing server listening only on `mail` channel, but not listening on other channels (which jobs it is not suited to perform).

By default, all jobs go to main channel (`QueueInterface::MAIN_CHANNEL`), but channel can be specified when job is added to the queue:

```php
$dispatcher->registerChannels('new');
$dispatcher->execute(new Inc(['number' => 123]), 'new');
```

By default, dispatcher will throw an exception if you try to add a job to an unknown channel. This can be turned off:

```php
$dispatcher->exceptionOnUnregisteredChannel(false);

// This job will end up in the 'main' channel, but exception will not be thrown
$dispatcher->execute(new Inc(['number' => 123]), 'unknown channel');
```

## Background Process

Jobs can report that they launched a process:

```php
class ListAndForget extends Job
{
    /**
     * Report that we launched a background process
     */
    public function execute()
    {
        $output = [];
        exec("nohup ls -la > /dev/null 2>&1 & echo $!", $output);

        $pid = (integer) $output[1];

        if ($pid > 0) {
            $this->reportBackgroundProcess($pid);
        }
    }
}
```

When they do, queue clean up and maintenance routines will not consider this job as stuck as long as process with the given PID is running. When process is done (we can't find it), job is considered to be done.

Information about jobs that launched processes can be found using `QueueInterface::getBackgroundProcesses()` method. This method returns an array, where each record in an array contains a job ID, job type and process ID:

```php
print_r($dispatcher->getQueue()->getBackgroundProcesses());

```

will output something like this:

```
Array
(
    [0] => Array
        (
            [id] => 1
            [type] => ActiveCollab\JobsQueue\Test\Jobs\ProcessLauncher
            [process_id] => 12345
        )

)
```

Note: Process reporting and watching is not supported on Windows systems at the moment.
