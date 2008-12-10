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
	<title>Xibo Message</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<link rel="stylesheet" type="text/css" href="template/css/error.css" />
	
	<link rel="shortcut icon" href="img/favicon.ico" />
	
</head>

<body>

	<div class="message_box" style="padding:10px;">
			<div class="highlight"><?php echo $errorMessage; ?></div>
			<?php if($show_back) { echo "<a href=\"#\" onclick=\"history.go(-1);return false;\">Back</a>"; } ?>
	</div>
	
</body>

</html>