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
#The body tag
	echo "<body ";
	if($q != '') 
	{
		echo $pageObject->on_page_load();
	}
	echo ">";
?>

<div class="ui-dialog ui-draggable" style="display:block;overflow: hidden; position: absolute; width: 200px; height: 100px; top: 253.5px; left: 388px; display: none; z-index: 8000;">
	<div class="ui-dialog-container" style="position: relative;">
		<div class="ui-dialog-titlebar">
			<span class="ui-dialog-title">Xibo</span>
			<div class="ui-dialog-titlebar-close"></div>
		</div>
		<div id="system_working" class="ui-dialog-content">
			<img src="img/loading.gif"><span style="padding-left:10px">Please Wait ...</span>
		</div>
	</div>
</div>

<div class="ui-dialog ui-draggable" style="display:block;overflow: hidden; position: absolute; width: 300px; height: 150px; top: 0px; left: 0px; display: none; z-index: 10000;">
	<div class="ui-dialog-container" style="position: relative;">
		<div class="ui-dialog-titlebar">
			<span class="ui-dialog-title">Message</span>
			<div class="ui-dialog-titlebar-close" onclick="$(this).parent().parent().parent().hide('slow')"></div>
		</div>
		<div id="system_message" class="ui-dialog-content">
			<span>Message</span>
			<p style="align:center; width:100%;"><button onclick="$(this).parent().parent().parent().parent().hide('slow')">OK</button></p>
		</div>
	</div>
</div>

<div id="div_dialog"></div>

<div id="container">
 <div id="headercontainer">
  <div id="header">
  </div>
	<div id="headerback">
		<h5 align="right">Welcome back <?php echo $username; ?>.</h5>
	</div>
	<?php displayMessage(); ?>
 </div>
  <div id="navigation">
  	<?php include("navigation.php"); ?>
  </div>
 <div id="contentwrap">
  <div id="content">

     <!--The remaining content follows here in the page that included this template file
      The footer.php file then closes off any block elements that remain open-->