<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ApplicationRedirectUri.php)
 */


namespace Xibo\Entity;


use Xibo\Storage\PDOConnect;

class ApplicationRedirectUri implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $clientId;

    /**
     * @var string
     */
    public $redirectUri;

    /**
     * Get Id
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Save
     */
    public function save()
    {
        if ($this->id == null)
            $this->add();
        else
            $this->edit();
    }

    public function delete()
    {
        PDOConnect::update('DELETE FROM `oauth_client_redirect_uris` WHERE `id` = :id', ['id' => $this->id]);
    }

    private function add()
    {
        $this->id = PDOConnect::insert('
            INSERT INTO `oauth_client_redirect_uris` (`client_id`, `redirect_uri`)
              VALUES (:clientId, :redirectUri)
        ', [
            'clientId' => $this->clientId,
            'redirectUri' => $this->redirectUri
        ]);
    }

    private function edit()
    {
        PDOConnect::update('
            UPDATE `oauth_client_redirect_uris`
                SET `redirect_uri` = :redirectUri
              WHERE `id` = :id
        ',[
            'id' => $this->id,
            'redirectUri' => $this->redirectUri
        ]);
    }
}