<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Command;

use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearFailedJobs extends Command
{
    protected function configure()
    {
        parent::configure();

        $this->setName('clear_failed_jobs')
            ->setDescription('Clear all failed jobs');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->dispatcher->getQueue()->clear();

            return $this->success('Done', $input, $output);
        } catch (Exception $e) {
            return $this->abortDueToException($e, $input, $output);
        }
    }
}
