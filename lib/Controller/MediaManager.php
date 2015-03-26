<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2013 Daniel Garner
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
namespace Xibo\Controller;
use baseDAO;
use DOMDocument;
use DOMXPath;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Form;
use Xibo\Helper\Log;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;


class MediaManager extends Base
{

    public function displayPage()
    {


        // Default options
        if (\Kit::IsFilterPinned('mediamanager', 'Filter')) {
            $filter_pinned = 1;
            $filter_layout_name = Session::Get('mediamanager', 'filter_layout_name');
            $filter_region_name = Session::Get('mediamanager', 'filter_region_name');
            $filter_media_name = Session::Get('mediamanager', 'filter_media_name');
            $filter_type = Session::Get('mediamanager', 'filter_type');
        } else {
            $filter_pinned = 0;
            $filter_layout_name = NULL;
            $filter_region_name = NULL;
            $filter_media_name = NULL;
            $filter_type = 0;
        }

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ApplicationState::Pager($id));
        Theme::Set('form_meta', '<input type="hidden" name="p" value="mediamanager"><input type="hidden" name="q" value="MediaManagerGrid">');

        $formFields = array();
        $formFields[] = Form::AddText('filter_layout_name', __('Layout'), $filter_layout_name, NULL, 'l');
        $formFields[] = Form::AddText('filter_region_name', __('Region'), $filter_region_name, NULL, 'r');
        $formFields[] = Form::AddText('filter_media_name', __('Media'), $filter_media_name, NULL, 'm');

        $types = $db->GetArray("SELECT moduleid AS moduleid, Name AS module FROM `module` WHERE Enabled = 1 ORDER BY 2");
        array_unshift($types, array('moduleid' => 0, 'module' => 'All'));

        $formFields[] = Form::AddCombo(
            'filter_type',
            __('Type'),
            $filter_type,
            $types,
            'moduleid',
            'module',
            NULL,
            't');

        $formFields[] = Form::AddCheckbox('XiboFilterPinned', __('Keep Open'),
            $filter_pinned, NULL,
            'k');

        // Call to render the template
        Theme::Set('header_text', __('Media Manager'));
        Theme::Set('form_fields', $formFields);
        $this->getState()->html .= Theme::RenderReturn('grid_render');
    }

    function actionMenu()
    {

        return array(
            array('title' => __('Filter'),
                'class' => '',
                'selected' => false,
                'link' => '#',
                'help' => __('Open the filter form'),
                'onclick' => 'ToggleFilterView(\'Filter\')'
            )
        );
    }

    public function MediaManagerGrid()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $filterLayout = \Xibo\Helper\Sanitize::getString('filter_layout_name');
        $filterRegion = \Xibo\Helper\Sanitize::getString('filter_region_name');
        $filterMediaName = \Xibo\Helper\Sanitize::getString('filter_media_name');
        $filterMediaType = \Xibo\Helper\Sanitize::getInt('filter_type');

        \Xibo\Helper\Session::Set('mediamanager', 'filter_layout_name', $filterLayout);
        \Xibo\Helper\Session::Set('mediamanager', 'filter_region_name', $filterRegion);
        \Xibo\Helper\Session::Set('mediamanager', 'filter_media_name', $filterMediaName);
        \Xibo\Helper\Session::Set('mediamanager', 'filter_type', $filterMediaType);
        \Xibo\Helper\Session::Set('mediamanager', 'Filter', \Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));

        // Lookup the module name
        if ($filterMediaType != 0) {

            $module = $this->getUser()->ModuleList(NULL, array('id' => $filterMediaType));
            if (count($module) > 0) {
                $filterMediaType = $module[0]['Name'];

                Log::notice('Matched module type ' . $filterMediaType, get_class(), __FUNCTION__);
            }
        }

        $cols = array(
            array('name' => 'layout', 'title' => __('Layout'), 'colClass' => 'group-word'),
            array('name' => 'region', 'title' => __('Region')),
            array('name' => 'media', 'title' => __('Media')),
            array('name' => 'mediatype', 'title' => __('Type')),
            array('name' => 'seq', 'title' => __('Sequence')),
        );
        Theme::Set('table_cols', $cols);

        // We would like a list of all layouts, media and media assignments that this user
        // has access to.
        $layouts = $user->LayoutList(NULL, array('layout' => $filterLayout));

        $rows = array();

        foreach ($layouts as $layout) {
            // We have edit permissions?
            if (!$layout['edit'])
                continue;

            // Every layout this user has access to.. get the regions
            $layoutXml = new DOMDocument();
            $layoutXml->loadXML($layout['xml']);

            // Get ever region
            $regionNodeList = $layoutXml->getElementsByTagName('region');
            $regionNodeSequence = 0;

            //get the regions
            foreach ($regionNodeList as $region) {
                $regionId = $region->getAttribute('id');
                $ownerId = ($region->getAttribute('userId') == '') ? $layout['ownerid'] : $region->getAttribute('userId');

                $regionAuth = $user->RegionAssignmentAuth($ownerId, $layout['layoutid'], $regionId, true);

                // Do we have permission to edit?
                if (!$regionAuth->edit)
                    continue;

                $regionNodeSequence++;
                $regionName = ($region->getAttribute('name') == '') ? 'Region ' . $regionNodeSequence : $region->getAttribute('name');

                if ($filterRegion != '' && !stristr($regionName, $filterRegion))
                    continue;

                // Media
                $xpath = new DOMXPath($layoutXml);
                $mediaNodes = $xpath->query("//region[@id='$regionId']/media");
                $mediaNodeSequence = 0;

                foreach ($mediaNodes as $mediaNode) {
                    $mediaId = $mediaNode->getAttribute('id');
                    $lkId = $mediaNode->getAttribute('lkid');
                    $mediaOwnerId = ($mediaNode->getAttribute('userId') == '') ? $layout['ownerid'] : $mediaNode->getAttribute('userId');
                    $mediaType = $mediaNode->getAttribute('type');

                    // Permissions
                    $auth = $user->MediaAssignmentAuth($mediaOwnerId, $layout['layoutid'], $regionId, $mediaId, true);

                    if (!$auth->edit)
                        continue;

                    // Create the media object without any region and layout information
                    require_once('modules/' . $mediaType . '.module.php');
                    $tmpModule = new $mediaType($db, $user, $mediaId, $layout['layoutid'], $regionId, $lkId);
                    $mediaName = $tmpModule->GetName();

                    if ($filterMediaName != '' && !stristr($mediaName, $filterMediaName))
                        continue;

                    if ($filterMediaType != '' && $mediaType != strtolower($filterMediaType))
                        continue;

                    $mediaNodeSequence++;

                    $layout['region'] = $regionName;
                    $layout['media'] = $mediaName;
                    $layout['mediatype'] = $mediaType;
                    $layout['seq'] = $mediaNodeSequence;
                    $layout['buttons'] = array();

                    // Edit
                    $layout['buttons'][] = array(
                        'id' => 'homepage_mediamanager_edit_button',
                        'url' => 'index.php?p=module&mod=' . $mediaType . '&q=Exec&method=EditForm&showRegionOptions=0&layoutid=' . $layout['layoutid'] . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId,
                        'text' => __('Edit')
                    );

                    $rows[] = $layout;
                }
            }
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('table_render');

        $response->SetGridResponse($output);

    }
}

?>
