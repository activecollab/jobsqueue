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

use InvalidArgumentException;

/**
 * @package ActiveCollab\JobsQueue\Helpers
 */
trait Port
{
    /**
     * @param array|null $data
     * @param int        $default_port
     * @param string     $property
     */
    protected function validatePort(array &$data = null, $default_port, $property = 'port')
    {
        if (!is_int($default_port) || $default_port < 1) {
            throw new InvalidArgumentException("'$default_port' is not a valid default port value");
        }

        if ($data === null) {
            $data = [];
        }

        if (array_key_exists($property, $data)) {
            if (is_string($data[ $property ]) && ctype_digit($data[ $property ])) {
                $data[ $property ] = (integer) $data[ $property ];
            }

            if (!is_int($data[ $property ]) || $data[ $property ] < 1) {
                throw new InvalidArgumentException("Invalid value '$data[$property]' for $property property");
            }
        } else {
            $data[ $property ] = $default_port;
        }
    }
}
