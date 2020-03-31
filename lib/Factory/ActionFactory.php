<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

use Xibo\Entity\Action;
use Xibo\Entity\User;
use Xibo\Helper\SanitizerService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class ActionFactory
 * @package Xibo\Factory
 */
class ActionFactory  extends BaseFactory
{
    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
    }

    /**
     * Create Empty
     * @return Action
     */
    public function createEmpty()
    {
        return new Action(
            $this->getStore(),
            $this->getLog(),
            $this->permissionFactory
        );
    }

    /**
     * Create a new action
     * @param string $triggerType
     * @param string|null $triggerCode
     * @param string $actionType
     * @param string $source
     * @param integer $sourceId
     * @param string $target
     * @param integer|null $targetId
     * @return Action
     */
    public function create(string $triggerType, $triggerCode, string $actionType, string $source, int $sourceId, string $target, $targetId)
    {

        $action = $this->createEmpty();
        $action->ownerId = $this->getUser()->userId;
        $action->triggerType = $triggerType;
        $action->triggerCode = $triggerCode;
        $action->actionType = $actionType;
        $action->source = $source;
        $action->sourceId = $sourceId;
        $action->target = $target;
        $action->targetId = $targetId;

        return $action;
    }

    /**
     * @param int $actionId
     * @return Action
     * @throws NotFoundException
     */
    public function getById(int $actionId)
    {
        $this->getLog()->debug('ActionFactory getById ' . $actionId);

        $actions = $this->query(null, ['disableUserCheck' => 1, 'actionId' => $actionId]);

        if (count($actions) <= 0) {
            $this->getLog()->debug('Action not found with ID '  . $actionId);
            throw new NotFoundException(__('Action not found'));
        }

        // Set our layout
        return $actions[0];
    }

    /**
     * @param string $source
     * @param int $sourceId
     * @return Action[]
     */
    public function getBySourceAndSourceId(string $source, int $sourceId)
    {
        $actions = $this->query(null, ['disableUserCheck' => 1, 'source' => $source, 'sourceId' => $sourceId]);

        return $actions;
    }

    /**
     * @param int $targetId
     * @return Action[]
     * @throws NotFoundException
     */
    public function getByTargetId(int $targetId)
    {
        $actions = $this->query(null, ['disableUserCheck' => 1, 'targetId' => $targetId]);

        if (count($actions) <= 0) {
            $this->getLog()->debug('Unable to find target ID ' . $targetId);
            throw new NotFoundException(__('not found'));
        }

        return $actions;
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return Action[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null) {
            $sortOrder = ['actionId DESC'];
        }

        $sanitizedFilter = $this->getSanitizer($filterBy);

        $params = [];
        $entries = [];

        $select = '
            SELECT action.actionId,
               action.ownerId,
               action.triggerType,
               action.triggerCode,
               action.actionType,
               action.source,
               action.sourceId,
               action.target,
               action.targetId
            ';

        $body = ' FROM action
                  WHERE 1 = 1 
        ';

        if ($sanitizedFilter->getInt('actionId') !== null) {
            $body .= ' AND `action`.actionId = :actionId ';
            $params['actionId'] = $sanitizedFilter->getInt('actionId');
        }

        if ($sanitizedFilter->getInt('ownerId') !== null) {
            $body .= ' AND `action`.ownerId = :ownerId ';
            $params['ownerId'] = $sanitizedFilter->getInt('ownerId');
        }

        if ($sanitizedFilter->getString('triggerType') !== null) {
            $body .= ' AND `action`.triggerType = :triggerType ';
            $params['triggerType'] = $sanitizedFilter->getString('triggerType');
        }

        if ($sanitizedFilter->getString('triggerCode') != null) {
            $body .= ' AND `action`.triggerCode = :triggerCode ';
            $params['triggerCode'] = $sanitizedFilter->getString('triggerCode');
        }

        if ($sanitizedFilter->getString('actionType') != null) {
            $body .= ' AND `action`.actionType  = :actionType  ';
            $params['actionType'] = $sanitizedFilter->getInt('actionType');
        }

        if ($sanitizedFilter->getString('source') != null) {
            $body .= ' AND `action`.source = :source ';
            $params['source'] = $sanitizedFilter->getString('source');
        }

        if ($sanitizedFilter->getInt('sourceId') != null) {
            $body .= ' AND `action`.sourceId = :sourceId ';
            $params['sourceId'] = $sanitizedFilter->getInt('sourceId');
        }

        if ($sanitizedFilter->getString('target') != null) {
            $body .= ' AND `action`.target = :target ';
            $params['target'] = $sanitizedFilter->getString('target');
        }

        if ($sanitizedFilter->getInt('targetId') != null) {
            $body .= ' AND `action`.targetId = :targetId ';
            $params['targetId'] = $sanitizedFilter->getInt('targetId');
        }

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $action = $this->createEmpty()->hydrate($row);
            $action->targetId = ($action->targetId === 0) ? null : $action->targetId;

            $entries[] = $action;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;

    }
}