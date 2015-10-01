<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ApplicationFactory.php)
 */


namespace Xibo\Factory;


use League\OAuth2\Server\Util\SecureKey;
use Xibo\Entity\Application;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class ApplicationFactory extends BaseFactory
{
    public static function create()
    {
        $application = new Application();
        // Make and ID/Secret
        $application->secret = SecureKey::generate(254);
        // Assign this user
        $application->userId = self::getUser()->userId;
        return $application;
    }

    /**
     * Get by ID
     * @param $clientId
     * @return Application
     * @throws NotFoundException
     */
    public static function getById($clientId)
    {
        $client = self::query(null, ['clientId' => $clientId]);

        if (count($client) <= 0)
            throw new NotFoundException();

        return $client[0];
    }

    public static function getByUserId($userId)
    {
        return ApplicationFactory::query(null, ['userId' => $userId]);
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        $select = '
            SELECT `oauth_clients`.id AS `key`,
                `oauth_clients`.secret,
                `oauth_clients`.name,
                `oauth_clients`.authCode,
                `oauth_clients`.clientCredentials,
                `oauth_clients`.userId ';

        $body = '
              FROM `oauth_clients`
        ';

        if (Sanitize::getInt('userId', $filterBy) !== null) {

            $select .= '
                , `oauth_auth_codes`.expire_time AS expires
            ';

            $body .= '
                INNER JOIN `oauth_sessions`
                ON `oauth_sessions`.client_id = `oauth_clients`.id
                    AND `oauth_sessions`.owner_id = :userId
                INNER JOIN `oauth_auth_codes`
                ON `oauth_auth_codes`.session_id = `oauth_sessions`.id
            ';

            $params['userId'] = Sanitize::getInt('userId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';


        if (Sanitize::getString('clientId', $filterBy) != null) {
            $body .= ' AND `oauth_clients`.id = :clientId ';
            $params['clientId'] = Sanitize::getString('clientId', $filterBy);
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
            $entries[] = (new Application())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}