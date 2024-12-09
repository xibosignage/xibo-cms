<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

    /** @var bool Should the message be encrypted? */
    private bool $isEncrypted;

    /**
     * Set the identity of this Player Action
     * @param string $channel
     * @param bool $isEncrypted
     * @param string|null $key
     * @return $this
     * @throws \Xibo\XMR\PlayerActionException if the key is invalid
     */
    final public function setIdentity(string $channel, bool $isEncrypted, ?string $key): PlayerActionInterface
    {
        $this->channel = $channel;
        $this->isEncrypted = $isEncrypted;

        if ($isEncrypted) {
            $this->publicKey = openssl_get_publickey($key);
            if (!$this->publicKey) {
                throw new PlayerActionException('Invalid Public Key');
            }
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
    private function getEncryptedMessage(): array
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
     * Finalise the message to be sent
     * @throws \Xibo\XMR\PlayerActionException
     */
    final public function finaliseMessage(): array
    {
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

        // Envelope our message
        $message = [
            'channel' => $this->channel,
            'qos' => $this->qos,
        ];

        // Encrypt the message if needed.
        if ($this->isEncrypted) {
            $encrypted = $this->getEncryptedMessage();
            $message['message'] = $encrypted['message'];
            $message['key'] = $encrypted['key'];
            $message['isWebSocket'] = false;
        } else {
            $message['message'] = $this->getMessage();
            $message['key'] = 'none';
            $message['isWebSocket'] = true;
        }

        return $message;
    }
}
