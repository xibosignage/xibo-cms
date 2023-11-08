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


namespace Xibo\Entity;


use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ApplicationRedirectUri
 * @package Xibo\Entity
 */
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
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function __serialize(): array
    {
        return $this->jsonSerialize();
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }

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
        $this->getStore()->update('DELETE FROM `oauth_client_redirect_uris` WHERE `id` = :id', ['id' => $this->id]);
    }

    private function add()
    {
        $this->id = $this->getStore()->insert('
            INSERT INTO `oauth_client_redirect_uris` (`client_id`, `redirect_uri`)
              VALUES (:clientId, :redirectUri)
        ', [
            'clientId' => $this->clientId,
            'redirectUri' => $this->redirectUri
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('
            UPDATE `oauth_client_redirect_uris`
                SET `redirect_uri` = :redirectUri
              WHERE `id` = :id
        ',[
            'id' => $this->id,
            'redirectUri' => $this->redirectUri
        ]);
    }
}