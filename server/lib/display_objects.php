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

DEFINE('HELP_BASE', "http://www.xibo.org.uk/manual/");

/**
 * Outputs a button
 *
 */
function button($page, $sp = "", $alt, $txt, $class = "", $id = "", $container = "", $onclick = "") 
{
	global $g_Security; //the page/group security settings
	
	if ($class!="") $class = "class='$class'";
	if ($id!="") $id = "id='$id'";
	if ($sp!="") $sp = "&sp=$sp";
	
	//we need to intercept the page and work out if the user is allowed to see it or not
	if (strpos($page,'&') === false) {
		if (!in_array($page, $g_Security)) 
		{
			return;
		}		
	}
	else {
		if (!in_array(substr($page, 0, strpos($page,'&')), $g_Security)) 
		{
			return;
		}
	}
	
	$button_html = <<<END
	<a $class $id href="index.php?p=$page$sp" alt="$alt" onclick="$onclick">$txt</a>	
END;

	if ($container != "") {
		$button_html = "<div class=\"$container\">$button_html</div>";
	}

	echo $button_html;
	
	return true;
}

/**
 * Outputs a link
 * @return 
 */
function navlink($href, $pageName, $pageText, $subPage, $class) 
{
	global $g_Security; //the page/group security settings
	
	//setup some vars
	$thisPage = $_SESSION['pagename']; 
	$sp = "";
	if(isset($_GET['sp'])) $sp = $_GET['sp'];
	
	//we need to intercept the page and work out if the user is allowed to see it or not
	if (!in_array($pageName, $g_Security)) 
	{
		return;
	}
	
	if ($thisPage != $pageName || ($subPage !="" && $subPage != $sp)) 
	{
		$class="";
	}
	
	$link = <<<END
	<li><a href="$href" class="$class">$pageText</a></li>
END;

	echo $link;
	
	return true;
}

function navheading($sublinks, $headingText, $class, $position) 
{
	global $g_Security; //the page/group security settings
	
	//setup some vars
	$thisPage = $_SESSION['pagename']; 
	$sp = "";
	if(isset($_GET['sp'])) $sp = $_GET['sp'];
	
	$sublink = explode(",",$sublinks);
	
	//we need to intercept the page and work out if the user is allowed to see it or not
	$allowed = false;
	foreach ($sublink as $sub) 
	{
		//if any of the sublinks are allowed, then output the main heading
		//otherwise allowd will be false and we should return without outputting anything
		if (in_array($sub, $g_Security)) 
		{
			$allowed = true;
		}
	}
	if (!$allowed) 
	{
		return;
	}
	
	if (!in_array($thisPage,$sublink)) 
	{
		$class="";
	}
	
	//output something different if we are at the beginning of this menu or at the end
	if ($position == 'begin') 
	{
		$link = <<<END
	<li><a href="#" class="$class">$headingText</a>
		<ul>	
END;
	}
	else 
	{
		$link = <<<END
		</ul>
	</li>
END;
	}

	echo $link;
	
	return true;
}

function pageSecurityCheck($page) 
{
	global $g_Security;
	
	if (!in_array($page, $g_Security)) 
	{
		//we dont have permission
		if (isset($_REQUEST['ajax'])) 
		{
			//ajax request handler
			$arh = new AjaxRequest();
			$arh->decode_response(false, "You do not have permission to access this page. [$page]");
		}
		else 
		{
			displayMessage(MSG_MODE_MANUAL,"You do not have permission to access this page. [$page]");
			exit;
		}
	}
	
	return true;
}

/**
 * Group page security array
 * @return 
 */
function groupPageSecurity($groupid) 
{
	global $db;
	$usertype = $_SESSION['usertype'];
	$security = array();
	
	//if the usertype is 1 (admin) then we have access to all the pages
	if ($usertype == 1) 
	{
		$SQL = "SELECT name FROM pages";
	}
	//we have access to only the pages assigned to this group
	else 
	{
		$SQL = "SELECT name FROM pages INNER JOIN lkpagegroup ON lkpagegroup.pageid = pages.pageid ";
		$SQL .= " WHERE lkpagegroup.groupid = $groupid ";
	}
	
	if (!$results = $db->query($SQL)) 
	{
		trigger_error($db->error());
		trigger_error("Can not get the page security for this group [$groupid]");
	}
	
	while ($row = $db->get_row($results)) 
	{
		array_push($security,$row[0]);
	}
	
	return $security;
}

/**
 * 
 * @return 
 * @param $location Object
 * @param $return Object[optional]
 * @param $class Object[optional]
 * @param $text Object[optional]
 * @param $target Object[optional]
 */
function HelpButton($location, $return = false) 
{
	$link = HELP_BASE . "?p=$location";
	
	$button = <<<END
	<input type="button" onclick="window.open('$link')" value="Help" />
END;

	if ($return)
	{
		return $button;
	}
	else
	{
		echo $button;
		return true;
	}
}

/**
 * 
 * @return 
 * @param $title Object
 * @param $return Object[optional]
 * @param $image Object[optional]
 * @param $alt Object[optional]
 */
function HelpIcon($title, $return = false, $image = "img/forms/info_icon.gif", $alt = "Hover for more info")
{
	$button = <<<END
	<img src="$image" alt="$alt" title="$title">
END;
	
	if ($return)
	{
		return $button;
	}
	else
	{
		echo $button;
		return true;
	}
}

/**
 * Checks whether a Module is enabled, if so returns a button
 * @return 
 * @param $href Object
 * @param $title Object
 * @param $onclick Object
 * @param $image Object
 * @param $text Object
 */
function ModuleButton($module, $href, $title, $onclick, $image, $text)
{
	global $db;
	
	// Check that the module is enabled
	$SQL = "SELECT Enabled FROM module WHERE Module = '$module' AND Enabled = 1";
	if (!$result = $db->query($SQL))
	{
		trigger_error($db->error());
		return "";
	}
	// If there were no modules enabled with that name, then return nothing (i.e. we dont output a button)
	if ($db->num_rows($result) == 0 )
	{
		return "";
	}
	
	$button = <<<HTML
	<div class="regionicons">
		<a title="$title" href="$href" onclick="$onclick">
		<img class="dash_button" src="$image" />
		<span class="dash_text">$text</span></a>
	</div>
HTML;

	return $button;
}

?>