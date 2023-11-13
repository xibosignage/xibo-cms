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


namespace Xibo\XMR;

/**
 * An adstract class which implements a Player Action Interface
 * This class should be extended for each different action type
 *
 * When sending it will check that a default QOS/TTL has been configured. It is the responsibility
 * of the extending class to set this on initialisation.
 */
abstract class PlayerAction implements PlayerActionInterface
{
    /** @var string The Action */
    public $action;

    /** @var string Created Date */
    public $createdDt;

    /** @var int TTL */
    public $ttl;

    /** @var int QOS */
    private $qos;

    /** @var string Channel */
    private $channel;

    /** @var string Public Key */
    private $publicKey;

    /**
     * Set the identity of this Player Action
     * @param string $channel
     * @param string $key
     * @return $this
     * @throws PlayerActionException if the key is invalid
     */
    final public function setIdentity(string $channel, string $key): PlayerActionInterface
    {
        $this->channel = $channel;
        $this->publicKey = openssl_get_publickey($key);
        if (!$this->publicKey) {
            throw new PlayerActionException('Invalid Public Key');
        }

        return $this;
    }

    /**
     * Set the message TTL
     * @param int $ttl
     * @return $this
     */
    final public function setTtl(int $ttl = 120): PlayerAction
    {
        $this->ttl = $ttl;
        return $this;
    }

    /**
     * Set the message QOS
     * @param int $qos
     * @return $this
     */
    final public function setQos(int $qos = 1): PlayerAction
    {
        $this->qos = $qos;
        return $this;
    }

    /**
     * Serialize this object to its JSON representation
     * @param array $include
     * @return string
     */
    final public function serializeToJson(array $include = []): string
    {
        $include = array_merge(['action', 'createdDt', 'ttl'], $include);

        $json = [];
        foreach (get_object_vars($this) as $key => $value) {
            if (in_array($key, $include)) {
                $json[$key] = $value;
            }
        }
        return json_encode($json);
    }

    /**
     * Return the encrypted message and keys
     * @return array
     * @throws PlayerActionException
     */
    final public function getEncryptedMessage(): array
    {
        $message = null;

        $seal = openssl_seal($this->getMessage(), $message, $eKeys, [$this->publicKey], 'RC4');
        if (!$seal) {
            throw new PlayerActionException('Cannot seal message');
        }

        return [
            'key' => base64_encode($eKeys[0]),
            'message' => base64_encode($message)
        ];
    }

    /**
     * Send the action to the specified connection and wait for a reply (acknowledgement)
     * @param string $connection
     * @return bool
     * @throws PlayerActionException
     */
    final public function send($connection): bool
    {
        try {
            // Set the message create date
            $this->createdDt = date('c');

            // Set the TTL if not already set
            if (empty($this->ttl)) {
                $this->setTtl();
            }

            // Set the QOS if not already set
            if (empty($this->qos)) {
                $this->setQos();
            }

            // Get the encrypted message
            $encrypted = $this->getEncryptedMessage();

            // Envelope our message
            $message = [
                'channel' => $this->channel,
                'message' => $encrypted['message'],
                'key' => $encrypted['key'],
                'qos' => $this->qos
            ];

            // Issue a message payload to XMR.
            $context = new \ZMQContext();

            // Connect to socket
            $socket = new \ZMQSocket($context, \ZMQ::SOCKET_REQ);
            $socket->setSockOpt(\ZMQ::SOCKOPT_LINGER, 2000);
            $socket->connect($connection);

            // Send the message to the socket
            $socket->send(json_encode($message));

            // Need to replace this with a non-blocking recv() with a retry loop
            $retries = 15;
            $reply = false;

            do {
                try {
                    // Try and receive
                    // if ZMQ::MODE_NOBLOCK/MODE_DONTWAIT is used and the operation would block boolean false
                    // shall be returned.
                    $reply = $socket->recv(\ZMQ::MODE_DONTWAIT);

                    if ($reply !== false) {
                        break;
                    }
                } catch (\ZMQSocketException $sockEx) {
                    if ($sockEx->getCode() !== \ZMQ::ERR_EAGAIN) {
                        throw $sockEx;
                    }
                }

                usleep(100000);
            } while (--$retries);

            // Disconnect socket
            $socket->disconnect($connection);

            // Return the reply, if we couldn't connect then the reply will be false
            return $reply !== false;
        } catch (\ZMQSocketException $ex) {
            throw new PlayerActionException('XMR connection failed. Error = ' . $ex->getMessage());
        }
    }
}
