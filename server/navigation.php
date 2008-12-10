<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
 
$thisPage 	= Kit::GetParam('session', _SESSION, _WORD);
$userid		= Kit::GetParam('userid', _SESSION, _INT, 0);
$homepage 	= $user->homepage($userid);

if (strpos($homepage, '&') === false) 
{
	$homepageName = $homepage;
}
else 
{
	$homepageName = substr($homepage, 0, strpos($homepage, '&'));	
}

echo "<ul id=\"nav\">";
	 
navlink("index.php?p=$homepage", "$homepageName", "Dashboard", "", "current"); 
navlink("index.php?p=schedule", "schedule", "Schedule", "", "current"); 
navlink("index.php?p=layout&sp=view", "layout", "Layout", "", "current"); 

//navheading("content,datasets,media,xslt,chart", "Library", "current", 'begin'); 
navlink("index.php?p=content&sp=view", "content", "Library", "view", "current"); 

/*if (config::getSetting($db,"scanPath")!="") {
navlink("index.php?p=content&sp=scanner", "content", "Scanner", "scanner", "current");
}

navlink("index.php?p=dataset", "dataset", "Datasets", "", "current"); 

if (config::getSetting($db,"openflashchart_path")!="") {
navlink("index.php?p=chart", "chart", "Charts", "", "current");
}*/

//navheading("content,datasets,media,chart", "Content", "current", 'end'); 

navheading("update,admin,user,display,report,group,license", "Admin", "current", 'begin'); 
navlink("index.php?p=display", "display", "Display", "", "current"); 
navlink("index.php?p=group", "group", "Groups", "", "current"); 
navlink("index.php?p=user", "user", "Users", "", "current"); 
navlink("index.php?p=report&sp=log", "report", "Log", "log", "current"); 
navlink("index.php?p=license", "license", "License", "", "current");   
navlink("index.php?p=report&sp=sessions", "report", "Sessions", "sessions", "current");   
navlink("index.php?p=admin", "admin", "Settings", "", "current");   
navheading("update,admin,user,display,report,group,license", "Admin", "current", 'end'); 

if($userid == 0) 
{
	echo "<li><a href=\"index.php\">Log in</a>";
}
else {
	echo "<li><a href=\"index.php?q=logout\">Log out</a>";
}
    
echo "</ul>";
