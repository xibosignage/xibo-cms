<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Playlist.php)
 */


namespace Xibo\Controller;


use Xibo\Entity\Widget;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;

class Playlist extends Base
{
    /**
     * Grid
     */
    public function widgetGrid()
    {
        $this->getState()->template = 'grid';

        // Transitions
        $transIn = TransitionFactory::getEnabledByType('in');
        $transOut = TransitionFactory::getEnabledByType('out');
        $widgets = WidgetFactory::query($this->gridRenderSort(), $this->gridRenderFilter(['playlistId' => Sanitize::getInt('playlistId')]));

        foreach ($widgets as $widget) {

            /* @var Widget $widget */
            $widget->module = ModuleFactory::createWithWidget($widget);

            // Naughty dynamic assignment, but I am not sure how to get
            // the name to be available to DataTables otherwise
            $widget->name = $widget->module->getName();
            $widget->transition = sprintf('%s / %s', $widget->module->getTransition('in'), $widget->module->getTransition('out'));

            if ($this->isApi())
                continue;

            $widget->includeProperty('buttons');

            if ($this->getUser()->checkEditable($widget)) {
                $widget->buttons[] = array(
                    'id' => 'timeline_button_edit',
                    'url' => $this->urlFor('module.widget.edit.form', ['id' => $widget->widgetId]),
                    'text' => __('Edit')
                );
            }

            if ($this->getUser()->checkDeleteable($widget)) {
                $widget->buttons[] = array(
                    'id' => 'timeline_button_delete',
                    'url' => $this->urlFor('module.widget.delete.form', ['id' => $widget->widgetId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'multiselectlink', 'value' => $this->urlFor('module.widget.delete', ['id' => $widget->widgetId])),
                        array('name' => 'rowtitle', 'value' => $widget->module->getName()),
                        array('name' => 'options', 'value' => 'unassign')
                    )
                );
            }

            if ($this->getUser()->checkPermissionsModifyable($widget)) {
                $widget->buttons[] = array(
                    'id' => 'timeline_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'Widget', 'id' => $widget->widgetId]),
                    'text' => __('Permissions')
                );
            }

            if (count($transIn) > 0) {
                $widget->buttons[] = array(
                    'id' => 'timeline_button_trans_in',
                    'url' => $this->urlFor('module.widget.transition.edit.form', ['id' => $widget->widgetId, 'type' => 'in']),
                    'text' => __('In Transition')
                );
            }

            if (count($transOut) > 0) {
                $widget->buttons[] = array(
                    'id' => 'timeline_button_trans_in',
                    'url' => $this->urlFor('module.widget.transition.edit.form', ['id' => $widget->widgetId, 'type' => 'out']),
                    'text' => __('Out Transition')
                );
            }
        }

        // Store the table rows
        $this->getState()->recordsTotal = WidgetFactory::countLast();
        $this->getState()->setData($widgets);
    }

    public function libraryAssignForm($playlistId)
    {
        $playlist = PlaylistFactory::getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        $this->getState()->template = 'playlist-form-library-assign';
        $this->getState()->setData([
            'data' => $playlist,
            'help' => Help::Link('Library', 'Assign')
        ]);
    }

    /**
     * Add Library items to a Playlist
     * @param int $playlistId
     */
    public function libraryAssign($playlistId)
    {
        $playlist = PlaylistFactory::getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // Expect a list of mediaIds
        $media = Sanitize::getIntArray('media');

        if (count($media) <= 0)
            throw new \InvalidArgumentException(__('Please provide Media to Assign'));

        // Loop through all the media
        foreach ($media as $mediaId) {
            /* @var int $mediaId */
            $item = MediaFactory::getById($mediaId);

            if (!$this->getUser()->checkViewable($item))
                throw new AccessDeniedException(__('You do not have permissions to use this media'));

            $widget = WidgetFactory::create($this->getUser()->userId, $playlistId, $item->mediaType, $item->duration);
            $widget->assignMedia($item->mediaId);

            // Assign the widget to the playlist
            $playlist->assignWidget($widget);
        }

        // Save the playlist
        $playlist->save();

        // Success
        $this->getState()->hydrate([
            'message' => __('Media Assigned'),
            'data' => $playlist
        ]);
    }

    /**
     * Order a playlist and its widgets
     * @param int $playlistId
     */
    function order($playlistId)
    {
        $playlist = PlaylistFactory::getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // Load the widgets
        $playlist->load();

        // Get our list of widget orders
        $widgets = Sanitize::getParam('widgets', null);

        // Go through each one and move it
        foreach ($widgets as $widgetId => $position) {

            // Find this item in the existing list and add it to our new order
            foreach ($playlist->widgets as $widget) {
                /* @var \Xibo\Entity\Widget $widget */
                if ($widget->getId() == $widgetId) {
                    Log::debug('Setting Display Order ' . $position . ' on widgetId ' . $widgetId);
                    $widget->displayOrder = $position;
                    break;
                }
            }
        }

        $playlist->save();

        // Success
        $this->getState()->hydrate([
            'message' => __('Order Changed'),
            'data' => $playlist
        ]);
    }
}