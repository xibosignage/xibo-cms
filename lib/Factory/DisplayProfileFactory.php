<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
namespace Xibo\Factory;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\DisplayProfile;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DisplayProfileFactory
 * @package Xibo\Factory
 */
class DisplayProfileFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /** @var EventDispatcherInterface  */
    private $dispatcher;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param ConfigServiceInterface $config
     * @param EventDispatcherInterface $dispatcher
     * @param CommandFactory $commandFactory
     */
    public function __construct($store, $log, $sanitizerService, $config, $dispatcher, $commandFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);

        $this->config = $config;
        $this->dispatcher = $dispatcher;
        $this->commandFactory = $commandFactory;
    }

    /**
     * @return DisplayProfile
     */
    public function createEmpty()
    {
        $displayProfile = new DisplayProfile(
            $this->getStore(),
            $this->getLog(),
            $this->config,
            $this->dispatcher,
            $this->commandFactory
        );
        $displayProfile->config = [];

        return $displayProfile;
    }

    /**
     * @param int $displayProfileId
     * @return DisplayProfile
     * @throws NotFoundException
     */
    public function getById($displayProfileId)
    {
        $profiles = $this->query(null, ['disableUserCheck' => 1, 'displayProfileId' => $displayProfileId]);

        if (count($profiles) <= 0) {
            throw new NotFoundException();
        }

        $profile = $profiles[0];
        /* @var DisplayProfile $profile */

        $profile->load([]);
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
     * @param $clientType
     * @return DisplayProfile
     */
    public function getUnknownProfile($clientType)
    {
        $profile = $this->createEmpty();
        $profile->type = 'unknown';
        $profile->setClientType($clientType);
        $profile->load();
        return $profile;
    }

    /**
     * Get by Command Id
     * @param $commandId
     * @return DisplayProfile[]
     * @throws NotFoundException
     */
    public function getByCommandId($commandId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'commandId' => $commandId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return DisplayProfile[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $profiles = [];
        $parsedFilter = $this->getSanitizer($filterBy);

        if ($sortOrder === null)
            $sortOrder = ['name'];

        try {
            $params = array();
            $select = 'SELECT displayProfileId, name, type, config, isDefault, userId ';

            $body = ' FROM `displayprofile` WHERE 1 = 1 ';

            if ($parsedFilter->getInt('displayProfileId') !== null) {
                $body .= ' AND displayProfileId = :displayProfileId ';
                $params['displayProfileId'] = $parsedFilter->getInt('displayProfileId');
            }

            if ($parsedFilter->getInt('isDefault') !== null) {
                $body .= ' AND isDefault = :isDefault ';
                $params['isDefault'] = $parsedFilter->getInt('isDefault');
            }

            // Filter by DisplayProfile Name?
            if ($parsedFilter->getString('displayProfile') != null) {
                $terms = explode(',', $parsedFilter->getString('displayProfile'));
                $this->nameFilter('displayprofile', 'name', $terms, $body, $params, ($parsedFilter->getCheckbox('useRegexForName') == 1));
            }

            if ($parsedFilter->getString('type') != null) {
                $body .= ' AND type = :type ';
                $params['type'] = $parsedFilter->getString('type');
            }

            if ($parsedFilter->getInt('commandId') !== null) {
                $body .= '
                    AND `displayprofile`.displayProfileId IN (
                        SELECT `lkcommanddisplayprofile`.displayProfileId
                          FROM `lkcommanddisplayprofile`
                         WHERE `lkcommanddisplayprofile`.commandId = :commandId
                    )
                ';

                $params['commandId'] = $parsedFilter->getInt('commandId');
            }

            // Sorting?
            $order = '';
            if (is_array($sortOrder))
                $order .= 'ORDER BY ' . implode(',', $sortOrder);

            $limit = '';
            // Paging
            if ($filterBy !== null && $parsedFilter->getInt('start') !== null && $parsedFilter->getInt('length') !== null) {
                $limit = ' LIMIT ' . intval($parsedFilter->getInt('start'), 0) . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
            }

            $sql = $select . $body . $order . $limit;



            foreach ($this->getStore()->select($sql, $params) as $row) {
                $profile = $this->createEmpty()->hydrate($row, ['intProperties' => ['isDefault']]);

                $profile->excludeProperty('configDefault');
                $profile->excludeProperty('configTabs');
                $profiles[] = $profile;
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