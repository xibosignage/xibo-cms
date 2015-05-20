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
use Xibo\Storage\PDOConnect;

class DisplayProfileFactory
{
    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayProfile]
     * @throws NotFoundException
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        try {
            $dbh = PDOConnect::init();

            $params = array();
            $SQL = 'SELECT displayProfileId, name, type, config, isDefault, userId FROM displayprofile ';

            $type = \Kit::GetParam('type', $filterBy, _WORD);
            if (!empty($type)) {
                $SQL .= ' WHERE type = :type ';
                $params['type'] = $type;
            }

            // Sorting?
            if (is_array($sortOrder))
                $SQL .= 'ORDER BY ' . implode(',', $sortOrder);

            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            $profiles = array();

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