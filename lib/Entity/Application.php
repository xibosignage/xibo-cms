<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Application.php)
 */


namespace Xibo\Entity;
use League\OAuth2\Server\Util\SecureKey;
use Xibo\Storage\PDOConnect;

/**
 * Class Application
 * @package Xibo\Entity
 *
 * @SWG\Definition
 */
class Application implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *  description="Application Key"
     * )
     * @var string
     */
    public $key;

    /**
     * @SWG\Property(
     *  description="Private Secret Key"
     * )
     * @var string
     */
    public $secret;

    /**
     * @SWG\Property(
     *  description="Application Name"
     * )
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(
     *  description="Application Session Expiry"
     * )
     * @var int
     */
    public $expires;

    /**
     * @SWG\Property(
     *  description="The Owner of this Application"
     * )
     * @var int
     */
    public $userId;


    public function save()
    {
        if ($this->key == null || $this->key == '')
            $this->add();
        else
            $this->edit();
    }

    public function resetKeys()
    {
        $this->secret = SecureKey::generate(254);
    }

    private function add()
    {
        $this->key = SecureKey::generate();

        // Simple Insert for now
        PDOConnect::insert('
            INSERT INTO `oauth_clients` (`id`, `secret`, `name`, `userId`)
              VALUES (:id, :secret, :name, :userId)
        ', [
            'id' => $this->key,
            'secret' => $this->secret,
            'name' => $this->name,
            'userId' => $this->userId
        ]);

        // Update the URI
        //PDOConnect::insert('INSERT INTO `oauth_client_redirect_uris` (client_id, redirect_uri) VALUES (:clientId, :redirectUri)', [
        //    'clientId' => $this->key,
        //    'redirectUri' => Sanitize::getString('redirectUri')
        //]);
    }

    private function edit()
    {
        PDOConnect::update('
            UPDATE `oauth_clients` SET
              `id` = :id,
              `secret` = :secret,
              `name` = :name,
              `userId` = :userId
             WHERE `id` = :id
        ', [
            'id' => $this->key,
            'secret' => $this->secret,
            'name' => $this->name,
            'userId' => $this->userId
        ]);
    }
}