<?php

/*
 * This file is part of the Active Collab Jobs Queue.
 *
 * (c) A51 doo <info@activecollab.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ActiveCollab\JobsQueue\Helpers;

use ActiveCollab\JobsQueue\Signals\SignalInterface;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * @package ActiveCollab\JobsQueue\Helpers
 */
trait ExecuteCliCommand
{
    /**
     * Validate and prepare job data.
     *
     * This method needs to be called from job's constructor. Expectations:
     *
     * - command is required
     * - in_background set to true works only on Unix like platform (it is not supported on Windows)
     *
     * @param array $data
     */
    protected function validateCommand(array &$data)
    {
        if (empty($data['command'])) {
            throw new InvalidArgumentException("'command' property is required");
        }

        if (empty($data['command_arguments'])) {
            $data['command_arguments'] = [];
        }

        if (empty($data['in_background'])) {
            $data['in_background'] = false;
        }

        if ($data['in_background'] && DIRECTORY_SEPARATOR == '\\') {
            throw new LogicException('Background jobs are not supported on Windows');
        }

        if (empty($data['log_output_to_file'])) {
            $data['log_output_to_file'] = '';
        }
    }

    /**
     * Use input data to prepare command and execute it.
     *
     * @param  array                $data
     * @param  string               $from_working_directory
     * @return SignalInterface|null
     */
    public function runCommandFromData(array $data, $from_working_directory = '')
    {
        return $this->runCommand($this->prepareCommandFromData($data), $from_working_directory, $data['log_to_file'], $data['in_background']);
    }

    /**
     * @param  string               $command
     * @param  string               $from_working_directory
     * @param  string               $log_to_file
     * @param  bool                 $in_background
     * @return SignalInterface|null
     */
    protected function runCommand($command, $from_working_directory = '', $log_to_file = '', $in_background = false)
    {
        // Check working directory if $from_working_directory is set and not current directory
        if ($from_working_directory) {
            $old_working_directory = getcwd();

            if ($old_working_directory != $from_working_directory) {
                if (!chdir($from_working_directory)) {
                    throw new RuntimeException("Failed to change working directory to '$from_working_directory'");
                }
            }
        }

        if (!empty($log_to_file)) {
            $log_to_file = escapeshellarg($log_to_file);
        }

        $output = [];
        $code = 0;

        if ($in_background) {
            if (empty($log_to_file)) {
                $log_to_file = '/dev/null';
            }

            $pid = 0;

            exec("nohup $command > $log_to_file 2>&1 & echo $!", $output, $code);

            if ($code === 0) {
                foreach ($output as $output_line) {
                    if (ctype_digit($output_line)) {
                        $pid = (integer) $output_line;
                        break;
                    }
                }
            }

            // Switch back to old working directory if we changed working directory
            if (isset($old_working_directory) && $old_working_directory != $from_working_directory) {
                chdir($old_working_directory);
            }

            if (!empty($pid)) {
                return $this->reportBackgroundProcess($pid);
            }
        } else {
            if (empty($log_to_file)) {
                exec($command, $output, $code);

                print implode("\n", $output);
            } else {
                exec("$command > $log_to_file", $output, $code);
            }

            if (isset($old_working_directory) && $old_working_directory != $from_working_directory) {
                chdir($old_working_directory); // Switch back to old working directory if we changed working directory
            }

            // Switch back to old working directory if we changed working directory
            if ($code !== 0) {
                throw new RuntimeException("Command exited with error #{$code}", $code);
            }
        }

        return null;
    }

    /**
     * @param  array  $data
     * @return string
     */
    public function prepareCommandFromData(array $data)
    {
        $command = $data['command'];

        foreach ($data['command_arguments'] as $k => $v) {
            if (is_int($k)) {
                if (is_string($v) && substr($v, 0, 1) == '-') {
                    $command .= " $v";
                } else {
                    $command .= ' ' . escapeshellarg($v);
                }
            } else {
                if (is_bool($v)) {
                    if ($v) {
                        $command .= " --{$k}";
                    }
                } else {
                    $command .= " --{$k}=" . escapeshellarg((is_array($v) ? implode(',', $v) : $v));
                }
            }
        }

        return $command;
    }

    /**
     * {@inheritdoc}
     */
    abstract protected function reportBackgroundProcess($process_id);
}
