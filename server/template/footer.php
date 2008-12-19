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

</div> <!-- Ends the content div -->
</div> <!-- Ends contentwrap div -->
</div> <!-- Ends the container div -->

</body>
</html>