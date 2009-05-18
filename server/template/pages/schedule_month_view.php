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
<div id="form_container">
	<div id="form_header">
		<div id="form_header_left">
		</div>
		<div id="form_header_right">
		</div>
	</div>
	
	<div id="form_body">
		<div id="schedule_form_container">
			<div class="leftbuttons">
				<div class="buttons">
					<a id="whatson_button" class="XiboFormButton" href="index.php?p=schedule&q=WhatsOn"><div class="button_text">Broadcasting</div></a>
				</div>	
			</div>
			<div id="displaybuttons">
			<?php echo $displayDAO->display_tabs($this->displayid, "index.php?p=schedule&date=$this->start_date"); ?>
			</div>	
			
			<?php $this->generate_calendar(); ?>
		</div>
	</div>

	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>	