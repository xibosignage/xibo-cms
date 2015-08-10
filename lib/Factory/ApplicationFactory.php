<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ApplicationFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Application;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class ApplicationFactory extends BaseFactory
{
    public static function getByUserId($userId)
    {
        return ApplicationFactory::query(null, ['userId' => $userId]);
    }

    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        $select = 'SELECT oauth_clients.id AS `key`, oauth_clients.secret, oauth_clients.name ';

        $body = '
              FROM `oauth_clients`
                LEFT OUTER JOIN `oauth_client_redirect_uris`
                ON `oauth_client_redirect_uris`.client_id = `oauth_clients`.id
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