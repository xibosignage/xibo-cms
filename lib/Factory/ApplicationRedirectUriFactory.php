<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ApplicationRedirectUriFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\ApplicationRedirectUri;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class ApplicationRedirectUriFactory extends BaseFactory
{
    /**
     * Get by ID
     * @param $id
     * @return ApplicationRedirectUri
     * @throws NotFoundException
     */
    public static function getById($id)
    {
        $clientRedirectUri = self::query(null, ['id' => $id]);

        if (count($clientRedirectUri) <= 0)
            throw new NotFoundException();

        return $clientRedirectUri[0];
    }

    /**
     * Get by Client Id
     * @param $clientId
     * @return array[ApplicationRedirectUri]
     * @throws NotFoundException
     */
    public static function getByClientId($clientId)
    {
        return self::query(null, ['clientId' => $clientId]);
    }

    /**
     * Query
     * @param null $sortOrder
     * @param null $filterBy
     * @return array
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        $select = 'SELECT id, client_id AS clientId, redirect_uri AS redirectUri ';

        $body = ' FROM `oauth_client_redirect_uris` WHERE 1 = 1 ';

        if (Sanitize::getString('clientId', $filterBy) != null) {
            $body .= ' AND `oauth_client_redirect_uris`.client_id = :clientId ';
            $params['clientId'] = Sanitize::getString('clientId', $filterBy);
        }

        if (Sanitize::getString('id', $filterBy) != null) {
            $body .= ' AND `oauth_client_redirect_uris`.client_id = :id ';
            $params['id'] = Sanitize::getString('id', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new ApplicationRedirectUri())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}