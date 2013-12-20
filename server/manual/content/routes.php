<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
		'developer',
		'troubleshooting'
	);

$allowed_routes = array(
		'intro',
		'license/licenses',
		'install/install_server',
		'install/install_environment',
		'install/install_client',
		'install/troubleshooting',
		'admin/settings',
		'coreconcepts/overview',
		'coreconcepts/login',
		'coreconcepts/navbar',
		'coreconcepts/dashboard',
		'content/overview',
		'content/adding',
		'content/content_video',
		'content/content_image',
		'content/content_powerpoint',
		'content/content_flash',
		'content/content_dataset',
		'admin/modules',
		'layout/overview',
		'layout/addlayout',
		'layout/layoutdesigner',
		'layout/addregion',
		'layout/assigncontent',
		'layout/region_preview_timeline',
		'layout/content_text',
		'layout/content_ticker',
		'layout/content_counter',
		'layout/content_webpage',
		'layout/content_embedded',
		'layout/content_shellcommand',
		'layout/campaign_layout',
		'templates/overview',
		'templates/template_resolution',
		'templates/choosetemplate',
		'schedule/overview',
		'schedule/schedule_calendar',
		'schedule/schedule_event',
		'schedule/schedule_now',
		'users/users',
		'users/menu_page_security',
		'users/user_types',
		'users/groups',
		'users/user_permissions',
		'admin/displays',
		'admin/overview',
		'admin/api',
		'admin/blueprints',
		'admin/advanced'
	);

?>
