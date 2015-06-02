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

class DisplayProfileFactory
{
    /**
     * @param int $displayProfileId
     * @return DisplayProfile
     * @throws NotFoundException
     */
    public static function getById($displayProfileId)
    {
        $profiles = DisplayProfileFactory::query(null, ['displayProfileId' => $displayProfileId]);

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
        $profiles = DisplayProfileFactory::query(null, ['type' => $type]);

        if (count($profiles) <= 0)
            throw new NotFoundException();

        $profile = $profiles[0];
        /* @var DisplayProfile $profile */

        $profile->load();
        return $profile;
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
            $SQL = 'SELECT displayProfileId, name, type, config, isDefault, userId FROM displayprofile WHERE 1 = 1 ';

            if (Sanitize::getInt('displayProfileId', $filterBy) != null) {
                $SQL .= ' AND displayProfileId = :displayProfileId ';
                $params['displayProfileId'] = Sanitize::getInt('displayProfileId', $filterBy);
            }

            if (Sanitize::getString('type', $filterBy) != null) {
                $SQL .= ' AND type = :type ';
                $params['type'] = Sanitize::getString('type', $filterBy);
            }

            // Sorting?
            if (is_array($sortOrder))
                $SQL .= 'ORDER BY ' . implode(',', $sortOrder);

            Log::sql($SQL, $params);

            foreach (PDOConnect::select($SQL, $params) as $row) {
                $profiles[] = (new DisplayProfile())->hydrate($row);
            }

            return $profiles;

        } catch (\Exception $e) {

            Log::error($e);

            throw new NotFoundException();
        }
    }
}