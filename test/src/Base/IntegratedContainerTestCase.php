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

namespace ActiveCollab\JobsQueue\Test\Base;

use ActiveCollab\JobsQueue\Test\Fixtures\Container;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class IntegratedContainerTestCase extends IntegratedMySqlQueueTest
{
    protected ContainerInterface $container;
    protected string $log_file_path;
    protected LoggerInterface $logger;

    public function setUp(): void
    {
        parent::setUp();

        $this->log_file_path = sprintf(
            '%s/%s.txt',
            dirname(__DIR__) . '/logs',
            date('Y-m-d')
        );

        if (is_file($this->log_file_path)) {
            unlink($this->log_file_path);
        }

        $this->logger = new Logger('cli');

        $handler = new StreamHandler($this->log_file_path, Logger::DEBUG);

        $formatter = new LineFormatter();
        $formatter->includeStacktraces(true);

        $handler->setFormatter($formatter);

        $this->logger->pushHandler($handler);

        $this->container = new Container(
            [
                'dispatcher' => $this->dispatcher,
                'log' => $this->logger,
            ]
        );
    }

    protected function tearDown(): void
    {
        if (is_file($this->log_file_path)) {
            unlink($this->log_file_path);
        }

        parent::tearDown();
    }
}
