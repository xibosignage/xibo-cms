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
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Application Message</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<link rel="stylesheet" type="text/css" href="template/css/error.css" />
	
	<link rel="shortcut icon" href="img/favicon.ico" />
	
</head>

<body>

	<div class="message_box">
		
		<div class="message_header">
			<h1>Application Message</h1>
			<p><div class="highlight"><?php echo $errorMessage; ?></div></p>
			<?php if($show_back) { echo "<a href=\"#\" onclick=\"history.go(-1);return false;\">Back</a>"; } ?>
		</div>
		
		<div class="message_body">
			<p>If there are no specfic instructions in the above message are several recovery options for you:
			<ul>
				<li>If this error was generated after a long period of inactivity try pressing back and completing your operation again</li>
				<li>If this error was generated after filling in a form please attempt to fill the form in again being wary of unusual characters (such as $%"^$*)</li>
				<li>If you have already done the steps above you can email the error to the Xstreamedia technical support team and we will respond as soon as possible.</li>
				<li>Alternatively you may ring the support team on the support number you were provided with.</li>
			</ul>
			</p>
			<p>A copy of this error has been send to the administrators of the system.</p>
			
		</div>
	</div>
	
</body>
</html>