<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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

$allowed_toc = array(
		'getting_started',
		'app_overview',
		'library',
		'layouts',
		'scheduling',
		'user_and_display',
		'troubleshooting'
	);

if (INCLUDE_DEVELOPER) {

	$allowed_toc[] = 'developer';
	$allowed_toc[] = 'developer_api';
	$allowed_toc[] = 'developer_releasenotes';
}

$allowed_routes = array(
		'intro',
		'license/licenses',
		'install/install_server',
		'install/install_environment',
		'install/install_client',
		'install/install_python_client',
		'install/python_client_hardware',
		'install/offline_download_client',
		'install/troubleshooting',
		'admin/settings',
		'coreconcepts/overview',
		'coreconcepts/login',
		'coreconcepts/navbar',
		'coreconcepts/dashboard',
		'content/overview',
		'content/adding',
		'content/editing',
		'content/deleting',
		'content/content_video',
		'content/content_image',
		'content/content_powerpoint',
		'content/content_flash',
		'content/content_dataset',
		'content/content_genericfile',
		'admin/modules',
		'layout/overview',
		'layout/layoutdesigner',
		'layout/addregion',
		'layout/assigncontent',
		'layout/region_preview_timeline',
		'layout/content_text',
		'layout/content_ticker',
		'layout/content_counter',
		'layout/content_webpage',
		'layout/content_embedded',
		'layout/content_datasetview',
		'layout/content_shellcommand',
		'layout/transitions',
		'layout/campaign_layout',
		'templates/overview',
		'templates/template_resolution',
		'templates/choosetemplate',
		'schedule/overview',
		'schedule/schedule_calendar',
		'schedule/schedule_event',
		'schedule/schedule_now',
		'users/overview',
		'users/users',
		'users/menu_page_security',
		'users/user_types',
		'users/groups',
		'users/user_permissions',
		'admin/displays',
		'admin/displaygroups',
		'admin/displaystats',
		'admin/display_wakeonlan',
		'admin/overview',
		'admin/api_overview',
		'admin/api_oauth',
		'admin/api_methods',
		'admin/api_displays',
		'admin/api_displaygroups',
		'admin/api_layouts',
		'admin/api_library',
		'admin/api_datasets',
		'admin/api_schedule',
		'admin/api_template',
		'admin/api_resolution',
		'admin/api_modules',
		'admin/api_misc',
		'admin/theme',
		'admin/blueprints',
		'admin/advanced',
		'admin/contributing',
		'admin/fileassociations',
		'admin/database_model',
		'admin/release_notes',
		'admin/release_notes_archive',
		'admin/release_notes_clonedb',
		'admin/release_notes_1.0.0',
		'admin/release_notes_1.0.1',
		'admin/release_notes_1.0.2',
		'admin/release_notes_1.0.3',
		'admin/release_notes_1.0.4',
		'admin/release_notes_1.0.5',
		'admin/release_notes_1.0.6',
		'admin/release_notes_1.0.7',
		'admin/release_notes_1.0.8',
		'admin/release_notes_1.1.0',
		'admin/release_notes_1.1.1',
		'admin/release_notes_1.2.0rc1',
		'admin/release_notes_1.2.0rc2',
		'admin/release_notes_1.2.0',
		'admin/release_notes_1.2.1',
		'admin/release_notes_1.2.2',
		'admin/release_notes_1.2.3',
		'admin/release_notes_1.3.0',
		'admin/release_notes_1.3.1',
		'admin/release_notes_1.3.2',
		'admin/release_notes_1.3.3',
		'admin/release_notes_1.4.0rc1',
		'admin/release_notes_1.4.0',
		'admin/release_notes_1.4.1',
		'admin/release_notes_1.4.2',
		'admin/release_notes_1.5.0',
		'admin/release_notes_1.5.1',
		'admin/release_notes_1.5.2',
		'admin/release_notes_1.6.0-rc1',
		'admin/release_notes_1.6.0-rc2',
		'admin/release_notes_1.6.0',
		'admin/release_notes_1.6.1',
		'admin/release_notes_1.6.2',
		'admin/pyclient_libbrowsernode_build'
	);

?>
