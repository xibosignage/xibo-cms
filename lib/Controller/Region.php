<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Region.php)
 */


namespace Xibo\Controller;


use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;

class Region extends Base
{
    /**
     * Edit Form
     * @param int $regionId
     */
    public function editForm($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        $this->getState()->template = 'region-form-edit';
        $this->getState()->setData([
            'region' => $region,
            'layout' => LayoutFactory::getById($region->layoutId),
            'transitions' => [
                'in' => TransitionFactory::getEnabledByType('in'),
                'out' => TransitionFactory::getEnabledByType('out'),
                'compassPoints' => array(
                    array('id' => 'N', 'name' => __('North')),
                    array('id' => 'NE', 'name' => __('North East')),
                    array('id' => 'E', 'name' => __('East')),
                    array('id' => 'SE', 'name' => __('South East')),
                    array('id' => 'S', 'name' => __('South')),
                    array('id' => 'SW', 'name' => __('South West')),
                    array('id' => 'W', 'name' => __('West')),
                    array('id' => 'NW', 'name' => __('North West'))
                )
            ],
            'help' => Help::Link('Region', 'Edit')
        ]);
    }

    /**
     * Delete Form
     * @param int $regionId
     */
    public function deleteForm($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkDeleteable($region))
            throw new AccessDeniedException();

        $this->getState()->template = 'region-form-delete';
        $this->getState()->setData([
            'region' => $region,
            'layout' => LayoutFactory::getById($region->layoutId),
            'help' => Help::Link('Region', 'Delete')
        ]);
    }

    /**
     * Add a region
     * @param int $layoutId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function add($layoutId)
    {
        $layout = LayoutFactory::getById($layoutId);

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Add a new region
        $region = RegionFactory::create($this->getUser()->userId, $layout->layout . '-' . (count($layout->regions) + 1), 250, 250, 50, 50);

        $layout->regions[] = $region;
        $layout->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $region->name),
            'id' => $region->regionId,
            'data' => [$region]
        ]);
    }

    /**
     * @param int $regionId
     */
    public function edit($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkEditable($region))
            throw new AccessDeniedException();

        $region->name = Sanitize::getString('name');
        $region->width = Sanitize::getDouble('width');
        $region->height = Sanitize::getDouble('height');
        $region->top = Sanitize::getDouble('top');
        $region->left = Sanitize::getDouble('left');
        $region->zIndex = Sanitize::getInt('zIndex');

        // Loop
        $region->setOptionValue('loop', Sanitize::getCheckbox('loop'));

        // Transitions
        $region->setOptionValue('transitionType', Sanitize::getString('transitionType'));
        $region->setOptionValue('transitionDuration', Sanitize::getInt('transitionDuration'));
        $region->setOptionValue('transitionDirection', Sanitize::getString('transitionDirection'));

        // Save
        $region->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $region->name),
            'id' => $region->regionId,
            'data' => [$region]
        ]);
    }

    /**
     * Delete a region
     * @param int $regionId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function delete($regionId)
    {
        $region = RegionFactory::getById($regionId);

        if (!$this->getUser()->checkDeleteable($region))
            throw new AccessDeniedException();

        $region->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $region->name)
        ]);
    }
}