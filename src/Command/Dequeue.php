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

use ActiveCollab\JobsQueue\Jobs\JobInterface;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Dequeue extends Command
{
    protected function configure()
    {
        parent::configure();

        $this->setName('dequeue')
            ->addArgument('type', InputArgument::REQUIRED, 'Full job type class name (including namespace)')
            ->setDescription('Remove jobs from queue');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $type = $this->getType($input);

            $count_before = (int) $this->dispatcher->getQueue()->countByType($type);
            $output->writeln("There are currently {$count_before} jobs of type {$type} on queue");

            $this->dispatcher->getQueue()->dequeueByType($type);

            $count_after = (int) $this->dispatcher->getQueue()->countByType($type);

            return $this->success("{($count_before - $count_after)} jobs of type {$type} removed from queue", $input, $output);
        } catch (Exception $e) {
            return $this->abortDueToException($e, $input, $output);
        }
    }

    /**
     * @param  InputInterface $input
     * @return string
     */
    private function getType(InputInterface $input)
    {
        $type = $input->getArgument('type');

        if (class_exists($type)) {
            $reflection_class = new ReflectionClass($type);

            if ($reflection_class->implementsInterface(JobInterface::class) && !$reflection_class->isAbstract()) {
                return $type;
            } else {
                throw new InvalidArgumentException('Valid job class expected');
            }
        } else {
            throw new InvalidArgumentException("Class '$type' does not exist");
        }
    }
}
