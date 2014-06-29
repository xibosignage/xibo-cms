<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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

interface ModuleInterface
{
	/**
	 * Returns an XML string representing this module at the time of calling.
	 * @return 
	 */
	public function AsXml();
	
	/**
	 * Set the layout and region IDs for this module.
	 * Should be called if a module has been created without this information (such as during the AssignFromLibrary)
	 * Each module should override this method to fill in missing module information they want added to the assignment
	 * Such information should always include generating the Options and RAW xml nodes.
	 * @return 
	 * @param $layoutid Object
	 * @param $regionid Object
	 */
	public function SetRegionInformation($layoutid, $regionid);
	
	/**
	 * Updates this module on the Region it is associated with
	 * Mainly used to push changes back to the region.
	 * @return 
	 */
	public function UpdateRegion();
	
	// Some Default Add/Edit/Delete functionality each module should have
	public function AddForm();
	public function EditForm();
	public function DeleteForm();
	public function AddMedia();
	public function EditMedia();
	public function DeleteMedia();

	// Return the name of the media as input by the user
	public function GetName();

	/**
	 * HTML Content to completely render this module.
	 */
    public function GetResource();

    /**
     * Install or Upgrade this module
     * 	Expects $this->codeSchemaVersion to be set by the module.
     */
    public function InstallOrUpdate();
    public function InstallModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings);
    public function UpgradeModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings);
    public function ModuleSettingsForm();
    public function ModuleSettings();
}
?>
