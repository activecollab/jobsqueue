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

namespace ActiveCollab\JobsQueue\Command;

use ActiveCollab\JobsQueue\Queue\MySqlQueue\AdditionalTablesResolverInterface;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTables extends Command
{
    protected function configure()
    {
        parent::configure();

        $this->setName('create_tables')
            ->setDescription('Create tables that are needed for MySQL queue to work')
            ->addOption(
                'additional-tables-resolver',
                '',
                InputOption::VALUE_REQUIRED,
                'Container key where additional tables resolver is available.',
                'additional_tables_resolver'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->dispatcher->getQueue()->createTables(...$this->getAdditionalTables($input));

            return $this->success('Done', $input, $output);
        } catch (Exception $e) {
            return $this->abortDueToException($e, $input, $output);
        }
    }

    private function getAdditionalTables(InputInterface $input): array
    {
        $container_key = $input->getOption('additional-tables-resolver');

        if ($container_key && $this->getContainer()->has($container_key)) {
            $additional_tables_resolver = $this->getContainer()->get($container_key);

            if ($additional_tables_resolver instanceof AdditionalTablesResolverInterface) {
                return $additional_tables_resolver->getAdditionalTables();
            }
        }

        return [];
    }
}
