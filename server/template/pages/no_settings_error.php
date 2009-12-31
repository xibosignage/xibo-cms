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
?>
<?php
 $install_file = false;
 
 if (file_exists("install.php")) {
 	$install_file = true;
 }

?>
<!DOCTYPE html PUBLIC "-//W3C/DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<meta http-equiv="Content-Language" content="en" />
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<link rel="stylesheet" type="text/css" href="template/css/presentation.css" />
	<link rel="shortcut icon" href="img/favicon.ico" />
	<title>Xibo: No Settings file detected.</title>
</head>
<body>

<div id="container">
	<div id="headercontainer">
		<div id="header">
	 
		</div>
		<div id="headerback">
			<h2>Settings Error</h2>
		</div>
	</div>
<div id="content">
<?php
 if ($install_file) {
?>
	<h1>Installation</h1>
	
	<p>Xibo can not detect a required settings file "config/settings.php".<br />
	This file is created when Xibo is installed!</p>
	
	<p>You have an install file available. If you would like to install now, please click below.</p>
	<div class="buttons"><a class="positive" href="install.php" alt="Install Now">Install</a></div>

<?php
 }
 else {
?>
	<h1>Settings Error</h1>
	
	<p>Xibo can not detect a required settings file "config/settings.php".<br />
	This file is created when Xibo is installed!</p>
	
	<p>You do not have an install file available, and you do not have the settings file. <br />
	This means your installtion has been corrupted. Please refer to your last good backup of the Xibo
	pages or contact <a href="mailto:info@xstreamedia.co.uk" alt="Mail us">info@xstreamedia.co.uk</a> and we will help you get your Xibo back up and running.</p>

<?php 
 }
?>	
</div>
</body>
</html>
  