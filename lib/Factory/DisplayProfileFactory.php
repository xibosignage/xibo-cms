<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayProfileFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DisplayProfile;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class DisplayProfileFactory extends BaseFactory
{
    /**
     * @param int $displayProfileId
     * @return DisplayProfile
     * @throws NotFoundException
     */
    public static function getById($displayProfileId)
    {
        $profiles = DisplayProfileFactory::query(null, ['disableUserCheck' => 1, 'displayProfileId' => $displayProfileId]);

        if (count($profiles) <= 0)
            throw new NotFoundException();

        $profile = $profiles[0];
        /* @var DisplayProfile $profile */

        $profile->load();
        return $profile;
    }

    /**
     * @param string $type
     * @return DisplayProfile
     * @throws NotFoundException
     */
    public static function getDefaultByType($type)
    {
        $profiles = DisplayProfileFactory::query(null, ['disableUserCheck' => 1, 'type' => $type]);

        if (count($profiles) <= 0)
            throw new NotFoundException();

        $profile = $profiles[0];
        /* @var DisplayProfile $profile */

        $profile->load();
        return $profile;
    }

    /**
     * Get by Command Id
     * @param $commandId
     * @return array[DisplayProfile]
     * @throws NotFoundException
     */
    public static function getByCommandId($commandId)
    {
        return DisplayProfileFactory::query(null, ['disableUserCheck' => 1, 'commandId' => $commandId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayProfile]
     * @throws NotFoundException
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $profiles = array();

        try {
            $params = array();
            $select = 'SELECT displayProfileId, name, type, config, isDefault, userId ';

            $body = ' FROM `displayprofile` WHERE 1 = 1 ';

            if (Sanitize::getInt('displayProfileId', $filterBy) !== null) {
                $body .= ' AND displayProfileId = :displayProfileId ';
                $params['displayProfileId'] = Sanitize::getInt('displayProfileId', $filterBy);
            }

            // Filter by DisplayProfile Name?
            if (Sanitize::getString('displayProfile', $filterBy) != null) {
                // convert into a space delimited array
                $names = explode(' ', Sanitize::getString('displayProfile', $filterBy));

                $i = 0;
                foreach ($names as $searchName) {
                    $i++;
                    // Not like, or like?
                    if (substr($searchName, 0, 1) == '-') {
                        $body .= " AND  `displayprofile`.name NOT LIKE :search$i ";
                        $params['search' . $i] = '%' . ltrim(($searchName), '-') . '%';
                    }
                    else {
                        $body .= " AND  `displayprofile`.name LIKE :search$i ";
                        $params['search' . $i] = '%' . $searchName . '%';
                    }
                }
            }

            if (Sanitize::getString('type', $filterBy) != null) {
                $body .= ' AND type = :type ';
                $params['type'] = Sanitize::getString('type', $filterBy);
            }

            if (Sanitize::getInt('commandId', $filterBy) !== null) {
                $body .= '
                    AND `displayprofile`.displayProfileId IN (
                        SELECT `lkcommanddisplayprofile`.displayProfileId
                          FROM `lkcommanddisplayprofile`
                         WHERE `lkcommanddisplayprofile`.commandId = :commandId
                    )
                ';

                $params['commandId'] = Sanitize::getInt('commandId', $filterBy);
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

            $sql = $select . $body . $order . $limit;

            Log::sql($sql, $params);

            foreach (PDOConnect::select($sql, $params) as $row) {
                $profiles[] = (new DisplayProfile())->hydrate($row);
            }

            // Paging
            if ($limit != '' && count($profiles) > 0) {
                $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
                self::$_countLast = intval($results[0]['total']);
            }

            return $profiles;

        } catch (\Exception $e) {

            Log::error($e);

            throw new NotFoundException();
        }
    }
}