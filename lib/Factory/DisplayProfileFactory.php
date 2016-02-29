<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayProfileFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DisplayProfile;
use Xibo\Exception\NotFoundException;

class DisplayProfileFactory extends BaseFactory
{
    /**
     * @param int $displayProfileId
     * @return DisplayProfile
     * @throws NotFoundException
     */
    public function getById($displayProfileId)
    {
        $profiles = $this->query(null, ['disableUserCheck' => 1, 'displayProfileId' => $displayProfileId]);

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
    public function getDefaultByType($type)
    {
        $profiles = $this->query(null, ['disableUserCheck' => 1, 'type' => $type, 'isDefault' => 1]);

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
    public function getByCommandId($commandId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'commandId' => $commandId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayProfile]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $profiles = array();

        try {
            $params = array();
            $select = 'SELECT displayProfileId, name, type, config, isDefault, userId ';

            $body = ' FROM `displayprofile` WHERE 1 = 1 ';

            if ($this->getSanitizer()->getInt('displayProfileId', $filterBy) !== null) {
                $body .= ' AND displayProfileId = :displayProfileId ';
                $params['displayProfileId'] = $this->getSanitizer()->getInt('displayProfileId', $filterBy);
            }

            if ($this->getSanitizer()->getInt('isDefault', $filterBy) !== null) {
                $body .= ' AND isDefault = :isDefault ';
                $params['isDefault'] = $this->getSanitizer()->getInt('isDefault', $filterBy);
            }

            // Filter by DisplayProfile Name?
            if ($this->getSanitizer()->getString('displayProfile', $filterBy) != null) {
                // convert into a space delimited array
                $names = explode(' ', $this->getSanitizer()->getString('displayProfile', $filterBy));

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

            if ($this->getSanitizer()->getString('type', $filterBy) != null) {
                $body .= ' AND type = :type ';
                $params['type'] = $this->getSanitizer()->getString('type', $filterBy);
            }

            if ($this->getSanitizer()->getInt('commandId', $filterBy) !== null) {
                $body .= '
                    AND `displayprofile`.displayProfileId IN (
                        SELECT `lkcommanddisplayprofile`.displayProfileId
                          FROM `lkcommanddisplayprofile`
                         WHERE `lkcommanddisplayprofile`.commandId = :commandId
                    )
                ';

                $params['commandId'] = $this->getSanitizer()->getInt('commandId', $filterBy);
            }

            // Sorting?
            $order = '';
            if (is_array($sortOrder))
                $order .= 'ORDER BY ' . implode(',', $sortOrder);

            $limit = '';
            // Paging
            if ($this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
                $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start'), 0) . ', ' . $this->getSanitizer()->getInt('length', 10);
            }

            $sql = $select . $body . $order . $limit;



            foreach ($this->getStore()->select($sql, $params) as $row) {
                $profiles[] = (new DisplayProfile())->hydrate($row)->setApp($this->getApp())->setApp($this->getApp());
            }

            // Paging
            if ($limit != '' && count($profiles) > 0) {
                $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
                $this->_countLast = intval($results[0]['total']);
            }

            return $profiles;

        } catch (\Exception $e) {

            $this->getLog()->error($e);

            throw new NotFoundException();
        }
    }
}