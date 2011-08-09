<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2010 Daniel Garner
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
class microblog extends Module
{
    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type = 'microblog';

        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm()
    {
        $db 		=& $this->db;
        $user		=& $this->user;

        // Would like to get the regions width / height
        $layoutid	= $this->layoutid;
        $regionid	= $this->regionid;
        $rWidth		= Kit::GetParam('rWidth', _REQUEST, _STRING);
        $rHeight	= Kit::GetParam('rHeight', _REQUEST, _STRING);

        $direction_list = listcontent("none|None,left|Left,right|Right,up|Up,down|Down", "direction");

        $form = <<<FORM
        <form id="ModuleForm" class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=AddMedia">
            <input type="hidden" name="layoutid" value="$layoutid">
            <input type="hidden" id="iRegionId" name="regionid" value="$regionid">
            <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
            <table>
                <tr>
                    <td colspan="2"><input type="checkbox" name="twitter" /><label for="twitter" title="">Twitter</label></td>
                    <td colspan="2"><input type="checkbox" name="identica" /><label for="identica" title="">Identica</label></td>
                </tr>
                <tr>
                    <td><label for="searchTerm" title="">Search Term<span class="required">*</span></label></td>
                    <td><input id="searchTerm" name="searchTerm" type="text"></td>
                    <td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration (s)<span class="required">*</span></label></td>
                    <td><input id="duration" name="duration" type="text"></td>
                </tr>
                <tr>
                    <td><label for="fadeInterval" title="">Fade Interval</label></td>
                    <td><input id="fadeInterval" name="fadeInterval" type="text" /></td>
                    <td><label for="speedInterval" title="">Speed (s)</label></td>
                    <td><input id="speedInterval" name="speedInterval" type="text" /></td>
                </tr>
                <tr>
                    <td><label for="updateInterval" title="">Update Interval</label></td>
                    <td><input id="updateInterval" name="updateInterval" type="text" /></td>
                    <td><label for="historySize" title="">History Size (items)</label></td>
                    <td><input id="historySize" name="historySize" type="text" /></td>
                </tr>
                <tr>
                    <td colspan="4">
                        <span>Message Template<span class="required">*</span></span>
                        <textarea id="ta_template" name="template"></textarea>
                    </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <span>Message to display when there are no messages</span>
                        <textarea id="ta_nocontent" name="nocontent"></textarea>
                    </td>
                </tr>
            </table>
        </form>
FORM;

        $this->response->html 		= $form;
        $this->response->dialogTitle    = 'Add Microblog';
        $this->response->callBack 	= 'microblog_callback';
        $this->response->AddButton(__('Help'), 'XiboHelpRender("index.php?p=help&q=Display&Topic=Microblog&Category=Media")');

        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=layout&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }

    /**
     * Return the Edit Form as HTML
     * @return
     */
    public function EditForm()
    {
        $db 		=& $this->db;

        $layoutid	= $this->layoutid;
        $regionid	= $this->regionid;
        $mediaid  	= $this->mediaid;

        // Permissions
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Get some options
        $searchTerm     = $this->GetOption('searchTerm');
        $fadeInterval   = $this->GetOption('fadeInterval');
        $speedInterval  = $this->GetOption('speedInterval');
        $updateInterval = $this->GetOption('updateInterval');
        $historySize    = $this->GetOption('historySize');
        $twitter        = $this->GetOption('twitter');
        $twitterChecked = '';
        $identica       = $this->GetOption('identica');
        $identicaChecked = '';

        // Is the transparency option set?
        if ($twitter)
            $twitterChecked = 'checked';

        if ($identica)
            $identicaChecked = 'checked';

        // Get Raw
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());

        Debug::LogEntry($db, 'audit', 'Raw XML returned: ' . $this->GetRaw());

        $templateNodes 	= $rawXml->getElementsByTagName('template');
        $templateNode 	= $templateNodes->item(0);
        $template	= $templateNode->nodeValue;

        $nocontentNodes	= $rawXml->getElementsByTagName('nocontent');
        $nocontentNode 	= $nocontentNodes->item(0);
        $nocontent	= $nocontentNode->nodeValue;

        $durationFieldEnabled = ($this->auth->modifyPermissions) ? '' : ' readonly';

        //Output the form
        $form = <<<FORM
        <form id="ModuleForm" class="XiboForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=EditMedia">
            <input type="hidden" name="layoutid" value="$layoutid">
            <input type="hidden" id="iRegionId" name="regionid" value="$regionid">
            <input type="hidden" name="mediaid" value="$mediaid">
            <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
            <table>
                <tr>
                    <td colspan="2"><input type="checkbox" name="twitter" $twitterChecked /><label for="twitter" title="">Twitter</label></td>
                    <td colspan="2"><input type="checkbox" name="identica" $identicaChecked /><label for="identica" title="">Identica</label></td>
                </tr>
                <tr>
                    <td><label for="searchTerm" title="">Search Term<span class="required">*</span></label></td>
                    <td><input id="searchTerm" name="searchTerm" type="text" value="$searchTerm"></td>
                    <td><label for="duration" title="The duration in seconds this webpage should be displayed">Duration (s)<span class="required">*</span></label></td>
                    <td><input id="duration" name="duration" type="text" value="$this->duration" $durationFieldEnabled></td>
                </tr>
                <tr>
                    <td><label for="fadeInterval" title="">Fade Interval</label></td>
                    <td><input id="fadeInterval" name="fadeInterval" type="text" value="$fadeInterval" /></td>
                    <td><label for="speedInterval" title="">Speed (s)</label></td>
                    <td><input id="speedInterval" name="speedInterval" type="text" value="$speedInterval" /></td>
                </tr>
                <tr>
                    <td><label for="updateInterval" title="">Update Interval</label></td>
                    <td><input id="updateInterval" name="updateInterval" type="text" value="$updateInterval" /></td>
                    <td><label for="historySize" title="">History Size (items)</label></td>
                    <td><input id="historySize" name="historySize" type="text" value="$historySize" /></td>
                </tr>
                <tr>
                    <td colspan="4">
                        <span>Message Template<span class="required">*</span></span>
                        <textarea id="ta_template" name="template">$template</textarea>
                    </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <span>Message to show when there are no messages</span>
                        <textarea id="ta_nocontent" name="nocontent">$nocontent</textarea>
                    </td>
                </tr>
            </table>
        </form>
FORM;

        $this->response->html 		= $form;
        $this->response->dialogTitle    = 'Edit MicroBlog';
        $this->response->callBack 	= 'microblog_callback';
        $this->response->AddButton(__('Help'), 'XiboHelpRender("index.php?p=help&q=Display&Topic=Microblog&Category=Media")');
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=layout&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');
        
        return $this->response;
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $db 		=& $this->db;

        $layoutid 	= $this->layoutid;
        $regionid 	= $this->regionid;
        $url 		= "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

        //Other properties
        $searchTerm	= Kit::GetParam('searchTerm', _POST, _STRING);
        $duration	= Kit::GetParam('duration', _POST, _INT, 0);
        $fadeInterval   = Kit::GetParam('fadeInterval', _POST, _INT);
        $speedInterval  = Kit::GetParam('speedInterval', _POST, _INT);
        $updateInterval = Kit::GetParam('updateInterval', _POST, _INT);
        $historySize    = Kit::GetParam('historySize', _POST, _INT);
        $twitter        = Kit::GetParam('twitter', _POST, _CHECKBOX, 'off');
        $identica       = Kit::GetParam('identica', _POST, _CHECKBOX, 'off');
        $template       = Kit::GetParam('template', _POST, _HTMLSTRING);
        $nocontent      = Kit::GetParam('nocontent', _POST, _HTMLSTRING);

        // Validation
        if ($duration == 0)
            $this->response->Error('You must enter a duration.', true);

        if ($template == '')
            $this->response->Error('You must enter a Message Template.', true);

        // Required Attributes
        $this->mediaid	= md5(uniqid());
        $this->duration = $duration;

        // Any Options
        $this->SetOption('searchTerm', $searchTerm);
        $this->SetOption('fadeInterval', $fadeInterval);
        $this->SetOption('speedInterval', $speedInterval);
        $this->SetOption('updateInterval', $updateInterval);
        $this->SetOption('historySize', $historySize);
        $this->SetOption('twitter', $twitter);
        $this->SetOption('identica', $identica);

        $this->SetRaw('<template><![CDATA[' . $template . ']]></template><nocontent><![CDATA[' . $nocontent . ']]></nocontent>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'microblog');

	if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }

        return $this->response;
    }

    /**
     * Edit Media in the Database
     * @return
     */
    public function EditMedia()
    {
        $db 		=& $this->db;

        $layoutid 	= $this->layoutid;
        $regionid 	= $this->regionid;
        $mediaid	= $this->mediaid;
        $url 		= "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        //Other properties
        $searchTerm	= Kit::GetParam('searchTerm', _POST, _STRING);
        $fadeInterval   = Kit::GetParam('fadeInterval', _POST, _INT);
        $speedInterval  = Kit::GetParam('speedInterval', _POST, _INT);
        $updateInterval = Kit::GetParam('updateInterval', _POST, _INT);
        $historySize    = Kit::GetParam('historySize', _POST, _INT);
        $twitter        = Kit::GetParam('twitter', _POST, _CHECKBOX, 'off');
        $identica       = Kit::GetParam('identica', _POST, _CHECKBOX, 'off');
        $template       = Kit::GetParam('template', _POST, _HTMLSTRING);
        $nocontent      = Kit::GetParam('nocontent', _POST, _HTMLSTRING);

        // Validation
        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);
            
        if ($this->duration == 0)
            $this->response->Error('You must enter a duration.', true);

        if ($template == '')
            $this->response->Error('You must enter a Message Template.', true);

        // Any Options
        $this->SetOption('searchTerm', $searchTerm);
        $this->SetOption('fadeInterval', $fadeInterval);
        $this->SetOption('speedInterval', $speedInterval);
        $this->SetOption('updateInterval', $updateInterval);
        $this->SetOption('historySize', $historySize);
        $this->SetOption('twitter', $twitter);
        $this->SetOption('identica', $identica);

        $this->SetRaw('<template><![CDATA[' . $template . ']]></template><nocontent><![CDATA[' . $nocontent . ']]></nocontent>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        if (!$this->UpdateRegion())
            $this->response->Error($this->message, true);

        //Set this as the session information
        setSession('content', 'type', 'microblog');

	if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = $url;
        }

        return $this->response;
    }
}

?>
