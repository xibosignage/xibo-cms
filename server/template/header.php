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

$username 	= Kit::GetParam('username', _SESSION, _USERNAME);
$p 			= Kit::GetParam('p', _REQUEST, _WORD);
$q 			= Kit::GetParam('q', _REQUEST, _WORD);

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

$help 		= new HelpManager($db, $user);
$helpLink 	= $help->Link();
 
?>
<!DOCTYPE html PUBLIC "-//W3C/DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>Xibo: Digital Signage</title>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<link rel="shortcut icon" href="img/favicon.ico" />
		<!-- Javascript Libraries -->
		<script type="text/javascript" src="3rdparty/jQuery/jquery.min.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery-ui.packed.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.form.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.bgiframe.min.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.tablesorter.pack.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.tablesorter.pager.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.ifixpng.js"></script>
		<script type="text/javascript" src="3rdparty/jQuery/jquery.contextmenu.r2.packed.js"></script>
		<link rel="stylesheet" type="text/css" href="3rdparty/jQuery/datePicker.css" />
		<link rel="stylesheet" type="text/css" href="3rdparty/jQuery/ui-elements.css" />
		
		<!-- Our own -->
		<link rel="stylesheet" type="text/css" href="template/css/presentation.css" />
		<script type="text/javascript" src="lib/js/functions.js"></script>
		<script type="text/javascript" src="lib/js/core.js"></script>
        <?php
		if ($p != '') 
		{
	        echo "<script src=\"lib/js/".$_SESSION['pagename'].".js\"></script>";
			
			if ($p == 'layout')
			{
				?>
					<script type="text/javascript" src="3rdparty/fckeditor/fckeditor.js"></script>
					<script type="text/javascript" src="lib/js/text-render.js"></script>
				<?php
			}
		}
        ?>
	</head>
<?php

	$body = '<body>';
	
	if ($q != '') $body = '<body ' . $this->thePage->on_page_load() . '>';

	echo $body;
?>

	<div id="container">
		<div id="headercontainer">
	  		<div id="header"></div>
			<div class="HelpLink">
				<a title="Logout" href="index.php?q=logout"><img src="img/logout.gif" alt="Logout" /></a>
			</div>
			<div class="HelpLink">
				<a title="Show <?php echo ucfirst($p); ?> Help" class="XiboHelpButton" href="<?php echo $helpLink; ?>"><img src="img/help.png" alt="Help" /></a>
			</div>
			<div id="headerback">
				<h5><?php echo $username; ?>  | </h5>
			</div>
			<?php displayMessage(); ?>
		</div>
		<div id="navigation">
			<ul id="nav">
				<?php
					// Always have access to your own homepage
					echo '<li><a href="index.php?p=' . $homepage . '">Dashboard</a></li>';
				
					// Put a menu here
					if (!$menu = new MenuManager($db, $user, 'Top Nav')) trigger_error($menu->message, E_USER_ERROR);
					
					while ($menuItem = $menu->GetNextMenuItem())
					{
						$uri 	= Kit::ValidateParam($menuItem['name'], _WORD);
						$args 	= Kit::ValidateParam($menuItem['Args'], _STRING);
						$class 	= Kit::ValidateParam($menuItem['Class'], _WORD);
						$title 	= Kit::ValidateParam($menuItem['Text'], _STRING);
						
						// Extra style for the current one
						if ($p == $uri) $class = 'current ' . $class;
						
						if ($uri == 'user')
						{
							// This is the management menu, so behave differently
							// Code duplication here - i wonder if we could be more effective?
							if (!$mgmMenu = new MenuManager($db, $user, 'Management')) trigger_error($mgmMenu->message, E_USER_ERROR);
							
							$menuTitle = $title;
							
							echo '<li><ul>';
							
							while ($menuItem = $mgmMenu->GetNextMenuItem())
							{
								$uri 	= Kit::ValidateParam($menuItem['name'], _WORD);
								$args 	= Kit::ValidateParam($menuItem['Args'], _STRING);
								$class 	= Kit::ValidateParam($menuItem['Class'], _WORD);
								$title 	= Kit::ValidateParam($menuItem['Text'], _STRING);
								
								// Extra style for the current one
								if ($p == $uri) $class = 'current ' . $class;
								
								$href = 'index.php?p=' . $uri . '&' . $args;
									
								echo '<li><a href="' . $href . '" class="' . $class . '">' . $title . '</a></li>';
							}
							
							echo '</ul><a href="#" class="' . $class . '">' . $menuTitle . '</a></li>';
						}
						else
						{
							$href = 'index.php?p=' . $uri . '&' . $args;
							
							echo '<li class="' . $class . '"><a href="' . $href . '" class="' . $class . '">' . $title . '</a></li>';
						}
					}
				?>
			</ul>
		</div>
		<div id="contentwrap">
			<div id="content">

<!--The remaining content follows here in the page that included this template file. The footer.php file then closes off any block elements that remain open-->