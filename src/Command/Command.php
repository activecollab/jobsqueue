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

use ActiveCollab\ContainerAccess\ContainerAccessInterface;
use ActiveCollab\ContainerAccess\ContainerAccessInterface\Implementation as ContainerAccessInterfaceImplementation;
use ActiveCollab\JobsQueue\JobsDispatcherInterface;
use DateTime;
use DateTimeZone;
use Doctrine\Common\Inflector\Inflector;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @property LoggerInterface $log
 * @property JobsDispatcherInterface $dispatcher
 */
abstract class Command extends SymfonyCommand implements ContainerAccessInterface
{
    use ContainerAccessInterfaceImplementation;

    protected function configure()
    {
        $bits = explode('\\', get_class($this));

        $this->setName($this->getCommandNamePrefix() . Inflector::tableize(array_pop($bits)))
            ->addOption('debug', '', InputOption::VALUE_NONE, 'Output debug details')
            ->addOption('json', '', InputOption::VALUE_NONE, 'Output JSON')
            ->addOption('environment', 'e', InputOption::VALUE_REQUIRED, 'Environment in which we are are running (development, staging or production)', 'development');
    }

    /**
     * Return command name prefix.
     *
     * @return string
     */
    protected function getCommandNamePrefix()
    {
        return '';
    }

    /**
     * Abort due to error.
     *
     * @param  string          $message
     * @param  int             $error_code
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int
     */
    protected function abort($message, $error_code, InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('json')) {
            $output->writeln(json_encode([
                'ok' => false,
                'error_message' => $message,
                'error_code' => $error_code,
            ]));
        } else {
            $output->writeln($message);
        }

        return $error_code < 1 ? 1 : $error_code;
    }

    /**
     * Show success message.
     *
     * @param  string          $message
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int
     */
    protected function success($message, InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('json')) {
            $output->writeln(json_encode([
                'ok' => true,
                'message' => $message,
            ]));
        } else {
            $output->writeln($message);
        }

        return 0;
    }

    /**
     * @param  array|null      $data
     * @param  OutputInterface $output
     * @return int
     */
    protected function successJson(array $data = null, OutputInterface $output)
    {
        $result = ['ok' => true];

        if (!empty($data) && is_array($data)) {
            $result = array_merge($result, $data);

            if (!$result['ok'] === true) {
                $result['ok'] = true;
            }
        }

        $output->writeln(json_encode($result));

        return 0;
    }

    /**
     * Abort due to an exception.
     *
     * @param  Exception       $e
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int
     */
    protected function abortDueToException(Exception $e, InputInterface $input, OutputInterface $output)
    {
        $message = $e->getMessage();
        $code = $this->exceptionToErrorCode($e);

        if ($input->getOption('json')) {
            $response = [
                'ok' => false,
                'error_message' => $message,
                'error_code' => $code,
            ];

            if ($input->getOption('debug')) {
                $response['error_class'] = get_class($e);
                $response['error_file'] = $e->getFile();
                $response['error_line'] = $e->getLine();
                $response['error_trace'] = $e->getTraceAsString();
            }

            $output->writeln(json_encode($response));
        } else {
            if ($input->getOption('debug')) {
                $output->writeln('Jobs error: <'.get_class($e).'>'.$message.', in file '.$e->getFile().' on line '.$e->getLine());
                $output->writeln('');
                $output->writeln('Backtrace');
                $output->writeln('');
                $output->writeln($e->getTraceAsString());
            } else {
                $output->writeln('Jobs error: '.$message);
            }
        }

        return $code;
    }

    /**
     * Get command error code from exception.
     *
     * @param  Exception $e
     * @return int
     */
    protected function exceptionToErrorCode(Exception $e)
    {
        return $e->getCode() > 0 ? $e->getCode() : 1;
    }

    /**
     * Return a date time instance from input argument.
     *
     * @param  InputInterface $input
     * @param  string         $argument
     * @param  string         $default
     * @return DateTime
     */
    protected function getDateTimeFromArgument(InputInterface $input, $argument, $default)
    {
        $value = $input->getArgument($argument);

        if (empty($value)) {
            $value = $default;
        }

        return new DateTime($value, new DateTimeZone('GMT'));
    }
}
