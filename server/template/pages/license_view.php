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
		<div class="leftbuttons">
				<div class="buttons">
					<a id="help_button" onclick="toggle_div_view('notes')"><div class="button_text">Help</div></a>
				</div>	
				<div id="notes" style="display:none;">
					<h4>License Help</h4>
					<div id="helptabs">
						<ul id="help" class="tabs-nav"><!-- the class on this element has to remain the same -->
							<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>
							<li><a href="#terms_div" class="help_tab"><span>Terms</span></a></li>	
						</ul>

						<div id="tips_div">
							<p>A license is required to use the xibo system at your site. Licenses restrict the number of displays
								that can be scheduled and maintained through the system.</p>
							<p>Licenses store the information of the server machine that it is licensed to.</p>
							<p>To add more displays you will be required to receive a new license txt file that will replace your current license
							and enable more displays on the system.</p>
							<p>The third party licenses section is used to honour the agreement of works used in conjunction with Xibo.</p>
						</div>
						<div id="terms_div">
							<p><strong>License</strong> this is a unique text file that is provided to you on purchase of the software. This contains all the details of the functionality 
							and displays registered to the server.</p>
							<p><strong>Licensed Servers</strong> provides details of the servers that your license is currently valid for.</p>
							<p><strong>Licensed Displays</strong> provides details of the number of displays currently applicable to your license.</p>			
						</div>
					</div>
				</div>			
			</div>
		<div class="license_info">
			<?php $this->license_info(); ?>
		</div>

	<div class="extralicenses">
		<h2>Third Party Licenses</h2>
		<p><a href="http://jquery.com/">Jquery</a> is provided under the <a href="http://dev.jquery.com/browser/trunk/jquery/MIT-LICENSE.txt">MIT license.</a></p>
		<p><a href="http://www.fckeditor.net/license">FCK Editor</a> is provided as the text editor, and is made available under GPL/LGPL/MPL</p>
		<p>The NuSOAP Library is used for the Web Service, and used on the Xibo Server under the GPL license.</p>
		<p>RSS feeds and other media is the sole property of the provider and their terms and conditions should be adhered too.</p>
	</div>
	</div>	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>		
	
</div>		