<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (PlayerAction.php)
 */


namespace Xibo\XMR;


abstract class PlayerAction implements PlayerActionInterface
{
    // The action
    public $action;

    // TTL
    public $createdDt;
    public $ttl;

    // Channel and key
    private $channel;
    private $publicKey;

    /**
     * Set the identity of this Player Action
     * @param string $channel
     * @param string $key
     * @return $this
     * @throws PlayerActionException if the key is invalid
     */
    public final function setIdentity($channel, $key)
    {
        $this->channel = $channel;
        if (!$this->publicKey = openssl_get_publickey($key))
            throw new PlayerActionException('Invalid Public Key');

        return $this;
    }

    /**
     * Set the message TTL
     * @param int $ttl
     * @return $this
     */
    public final function setTtl($ttl = 120)
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * Serialize this object to its JSON representation
     * @param array $include
     * @return string
     */
    public final function serializeToJson($include = [])
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
    public final function getEncryptedMessage()
    {
        $message = null;

        if (!openssl_seal($this->getMessage(), $message, $eKeys, [$this->publicKey]))
            throw new PlayerActionException('Cannot seal message');

        return [
            'key' => base64_encode($eKeys[0]),
            'message' => base64_encode($message)
        ];
    }

    /**
     * Send the action to the specified connection and wait for a reply (acknowledgement)
     * @param string $connection
     * @return string
     * @throws PlayerActionException
     */
    public final function send($connection)
    {
        try {
            // Set the message create date
            $this->createdDt = date('c');

            // Set the TTL if not already set
            if ($this->ttl == 0)
                $this->setTtl();

            // Get the encrypted message
            $encrypted = $this->getEncryptedMessage();

            // Envelope our message
            $message = [
                'channel' => $this->channel,
                'message' => $encrypted['message'],
                'key' => $encrypted['key']
            ];

            // Issue a message payload to XMR.
            $requester = new \ZMQSocket(new \ZMQContext(), \ZMQ::SOCKET_REQ);
            $requester->connect($connection);

            $requester->send(json_encode($message));

            return $requester->recv();
        }
        catch (\ZMQSocketException $sockEx) {
            throw new PlayerActionException('XMR connection failed.');
        }
    }
}