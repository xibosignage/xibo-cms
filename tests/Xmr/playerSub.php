<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

// This is a simple XMR client which connects to the display added in XMDS.http
// performing actions in the CMS which affect the display should be logged here.

define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));

require PROJECT_ROOT . '/vendor/autoload.php';

// RSA key
$fp = fopen(PROJECT_ROOT . '/library/certs/private.key', 'r');
$privateKey = openssl_get_privatekey(fread($fp, 8192));
fclose($fp);

// Sub
$loop = React\EventLoop\Factory::create();

$context = new React\ZMQ\Context($loop);

$sub = $context->getSocket(ZMQ::SOCKET_SUB);
$sub->connect('tcp://xmr:9505');
$sub->subscribe('H');
$sub->subscribe('XMR_test_channel');

$sub->on('messages', function ($msg) use ($privateKey) {
    try {
        if ($msg[0] == 'H') {
            echo '[' . date('Y-m-d H:i:s') . '] Heartbeat...' . PHP_EOL;
            return;
        }

        // Expect messages to have a length of 3
        if (count($msg) != 3) {
            throw new InvalidArgumentException('Incorrect Message Length');
        }

        // Message will be: channel, key, message
        if ($msg[0] != 'XMR_test_channel') {
            throw new InvalidArgumentException('Channel does not match');
        }

        // Decrypt
        $output = null;
        openssl_open(base64_decode($msg[2]), $output, base64_decode($msg[1]), $privateKey, 'RC4');

        echo '[' . date('Y-m-d H:i:s') . '] Received: ' . $output . PHP_EOL;
    } catch (InvalidArgumentException $e) {
        echo '[' . date('Y-m-d H:i:s') . '] E: ' . $e->getMessage() . PHP_EOL;
    }
});

$loop->run();

openssl_free_key($privateKey);