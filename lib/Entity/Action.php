<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

namespace Xibo\Entity;

use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Action
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Action implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Action Id")
     * @var int
     */
    public $actionId;

    /**
     * @SWG\Property(description="The Owner Id")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The Action trigger type")
     * @var string
     */
    public $triggerType;

    /**
     * @SWG\Property(description="The Action trigger code")
     * @var string
     */
    public $triggerCode;

    /**
     * @SWG\Property(description="The Action type")
     * @var string
     */
    public $actionType;

    /**
     * @SWG\Property(description="The Action source (layout, region or widget)")
     * @var string
     */
    public $source;

    /**
     * @SWG\Property(description="The Action source Id (layoutId, regionId or widgetId)")
     * @var int
     */
    public $sourceId;

    /**
     * @SWG\Property(description="The Action target (region)")
     * @var string
     */
    public $target;

    /**
     * @SWG\Property(description="The Action target Id (regionId)")
     * @var int
     */
    public $targetId;

    /**
     * @SWG\Property(description="Widget ID that will be loaded as a result of navigate to Widget Action type")
     * @var int
     */
    public $widgetId;

    /**
     * @SWG\Property(description="Layout Code identifier")
     * @var string
     */
    public $layoutCode;

    /**
     * @SWG\Property(description="Layout Id associated with this Action")
     * @var int
     */
    public $layoutId;

    /** @var \Xibo\Factory\PermissionFactory  */
    private $permissionFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function __clone()
    {
        $this->hash = null;
        $this->actionId = null;
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->actionId;
    }

    /**
     * Get the OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('ActionId %d, Trigger Type %s, Trigger Code %s, Action Type %s, Source %s, SourceId %s, Target %s, TargetId %d', $this->actionId, $this->triggerType, $this->triggerCode, $this->actionType, $this->source, $this->sourceId, $this->target, $this->targetId);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        // on add we expect only layoutId, actionType, target and targetId
        if ($this->layoutId == null) {
            throw new InvalidArgumentException(__('No layoutId specified'), 'layoutId');
        }

        if (!in_array($this->actionType, ['next', 'previous', 'navLayout', 'navWidget'])) {
            throw new InvalidArgumentException(__('Invalid action type'), 'actionType');
        }

        if (!in_array(strtolower($this->source), ['layout', 'region', 'widget'])) {
            throw new InvalidArgumentException(__('Invalid source'), 'source');
        }

        if (!in_array(strtolower($this->target), ['region', 'screen'])) {
            throw new InvalidArgumentException(__('Invalid target'), 'target');
        }

        if ($this->target == 'region' && $this->targetId == null) {
            throw new InvalidArgumentException(__('Please select a Region'), 'targetId');
        }

        // on edit, we should validate other parameters.
        if ($this->actionId != null) {
            if ($this->triggerType === 'webhook' && $this->triggerCode === null) {
                throw new InvalidArgumentException(__('Please provide trigger code'), 'triggerCode');
            }

            if (!in_array($this->triggerType, ['touch', 'webhook'])) {
                throw new InvalidArgumentException(__('Invalid trigger type'), 'triggerType');
            }

            if ($this->actionType === 'navLayout' && $this->layoutCode == '') {
                throw new InvalidArgumentException(__('Please enter Layout code'), 'layoutCode');
            }

            if ($this->actionType === 'navWidget' && $this->widgetId == null) {
                throw new InvalidArgumentException(__('Please select a Widget'), 'widgetId');
            }
        }
    }

    /**
     * @param array $options
     * @throws InvalidArgumentException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'notifyLayout' => false
        ], $options);

        $this->getLog()->debug('Saving ' . $this);

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->actionId == null || $this->actionId == 0) {
            $this->add();
            $this->loaded = true;
        } else {
            $this->update();
        }

        if ($options['notifyLayout'] && $this->layoutId != null) {
            $this->notifyLayout($this->layoutId);
        }
    }

    public function add()
    {
        $this->actionId = $this->getStore()->insert('INSERT INTO `action` (ownerId, triggerType, triggerCode, actionType, source, sourceId, target, targetId, widgetId, layoutCode, layoutId) VALUES (:ownerId, :triggerType, :triggerCode, :actionType, :source, :sourceId, :target, :targetId, :widgetId, :layoutCode, :layoutId)', [
            'ownerId' => $this->ownerId,
            'triggerType' => $this->triggerType,
            'triggerCode' => $this->triggerCode,
            'actionType' => $this->actionType,
            'source' => $this->source,
            'sourceId' => $this->sourceId,
            'target' => $this->target,
            'targetId' => $this->targetId,
            'widgetId' => $this->widgetId,
            'layoutCode' => $this->layoutCode,
            'layoutId' => $this->layoutId
        ]);

    }

    public function update()
    {
        $this->getStore()->update('UPDATE `action` SET ownerId = :ownerId, triggerType = :triggerType, triggerCode = :triggerCode, actionType = :actionType, source = :source, sourceId = :sourceId, target = :target, targetId = :targetId, widgetId = :widgetId, layoutCode = :layoutCode, layoutId = :layoutId WHERE actionId = :actionId', [
            'ownerId' => $this->ownerId,
            'triggerType' => $this->triggerType,
            'triggerCode' => $this->triggerCode,
            'actionType' => $this->actionType,
            'source' => $this->source,
            'sourceId' => $this->sourceId,
            'target' => $this->target,
            'targetId' => $this->targetId,
            'actionId' => $this->actionId,
            'widgetId' => $this->widgetId,
            'layoutCode' => $this->layoutCode,
            'layoutId' => $this->layoutId
        ]);
    }

    public function delete()
    {
        $this->getStore()->update('DELETE FROM `action` WHERE actionId = :actionId', ['actionId' => $this->actionId]);
    }

    /**
     * Notify the Layout (set to building)
     * @param $layoutId
     */
    public function notifyLayout($layoutId)
    {
        $this->getLog()->debug(sprintf('Saving Interactive Action ID %d triggered layout ID %d build', $this->actionId, $layoutId));

        $this->getStore()->update('
            UPDATE `layout` SET `status` = 3, `modifiedDT` = :modifiedDt WHERE layoutId = :layoutId
        ', [
            'layoutId' => $layoutId,
            'modifiedDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat())
        ]);
    }

}