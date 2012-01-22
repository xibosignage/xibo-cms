<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class mediamanagerDAO 
{
    private $db;
    private $user;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;
    }

    function on_page_load()
    {
        return "";
    }

    function echo_page_heading()
    {
        global $user;

        $userid = Kit::GetParam('userid', _SESSION, _INT);
        $uid 	= $user->getNameFromID($userid);

        echo "$uid's " . __('Dashboard');
        return true;
    }

    function displayPage()
    {
        $db =& $this->db;
        $user =& $this->user;

        include_once("template/pages/mediamanager.php");
    }

    public function MediaManagerFilter()
    {
        $msgLayout = __('Layout');
        $msgRegion = __('Region');
        $msgMedia = __('Media');
        $msgMediaType = __('Type');

        $modules = $this->db->GetArray("SELECT Module, Name FROM module WHERE Enabled = 1 ORDER BY 2", true);
        $modules[] = array('Module' => 'all', 'Name' => 'All');

        $mediaTypeList = Kit::SelectList('filterMediaType', $modules, 'Module', 'Name', 'all');

        $id = uniqid();

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
                <div class="XiboFilter">
                        <form onsubmit="return false">
				<input type="hidden" name="p" value="mediamanager">
				<input type="hidden" name="q" value="MediaManagerGrid">
                            <table class="mediamanager_filterform">
                                <tr>
                                    <td>$msgLayout</td>
                                    <td><input type="text" name="filterLayout"></td>
                                    <td>$msgMedia</td>
                                    <td><input type="text" name="filterMediaName"></td>
				</tr>
                                <tr>
                                    <td>$msgRegion</td>
                                    <td><input type="text" name="filterRegion"></td>
                                    <td>$msgMediaType</td>
                                    <td>$mediaTypeList</td>
				</tr>
                            </table>
                        </form>
                </div>
                <div class="XiboData">

                </div>
        </div>
HTML;
        echo $xiboGrid;
    }

    public function MediaManagerGrid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $filterLayout = Kit::GetParam('filterLayout', _POST, _STRING);
        $filterRegion = Kit::GetParam('filterRegion', _POST, _STRING);
        $filterMediaName = Kit::GetParam('filterMediaName', _POST, _STRING);
        $filterMediaType = Kit::GetParam('filterMediaType', _POST, _STRING);
        
        // We would like a list of all layouts, media and media assignments that this user
        // has access to.
        $layouts = $user->LayoutList($filterLayout);

        $msgLayout = __('Layout');
        $msgRegion = __('Region');
        $msgMedia = __('Media');
        $msgMediaType = __('Type');
        $msgSeq = __('Sequence');

        $msgAction = __('Action');
        $msgEdit = __('Edit');
        $msgDelete = __('Delete');

        $output = <<<END
        <div class="info_table">
        <table style="width:100%">
            <thead>
                <tr>
                    <th>$msgLayout</th>
                    <th>$msgRegion</th>
                    <th>$msgMedia</th>
                    <th>$msgMediaType</th>
                    <th>$msgSeq</th>
                    <th>$msgAction</th>
                </tr>
            </thead>
            <tbody>
END;

        foreach ($layouts as $layout)
        {
            // We have edit permissions?
            if (!$layout['edit'])
                continue;

            Debug::LogEntry($db, 'audit', 'Permission to edit layout ' . $layout['layout'], 'mediamanager', 'MediaManagerGrid');

            // Every layout this user has access to.. get the regions
            $layoutXml = new DOMDocument();
            $layoutXml->loadXML($layout['xml']);

            // Get ever region
            $regionNodeList = $layoutXml->getElementsByTagName('region');
            $regionNodeSequence = 0;

            //get the regions
            foreach ($regionNodeList as $region)
            {
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

                Debug::LogEntry($db, 'audit', 'Permissions granted for ' . $regionId . ' owned by ' . $ownerId, 'mediamanager', 'MediaManagerGrid');

                // Media
                $xpath = new DOMXPath($layoutXml);
		$mediaNodes = $xpath->query("//region[@id='$regionId']/media");
                $mediaNodeSequence = 0;

		foreach ($mediaNodes as $mediaNode)
		{
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

                            Debug::LogEntry($db, 'audit', $filterMediaType . ' ' . $mediaType);

                    if ($filterMediaType != 'all' && $mediaType != strtolower($filterMediaType))
                        continue;
                    
                    $editLink = '<button class="XiboFormButton" href="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=EditForm&showRegionOptions=0&layoutid=' . $layout['layoutid'] . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '">' . $msgEdit . '</button>';
                    $mediaNodeSequence++;

                    $output .= '<tr>';
                    $output .= '    <td>' . $layout['layout'] . '</td>';
                    $output .= '    <td>' . $regionName . '</td>';
                    $output .= '    <td>' . $mediaName . '</td>';
                    $output .= '    <td>' . $mediaType . '</td>';
                    $output .= '    <td>' . $mediaNodeSequence . '</td>';
                    $output .= '    <td>' . $editLink . '</td>';
                    $output .= '</tr>';
                }
            }
        }

        $output .= '</tbody></table></div>';

        $response->SetGridResponse($output);
        $response->Respond();
    }
}
?>