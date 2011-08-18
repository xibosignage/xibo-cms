<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011 Daniel Garner
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
defined('XIBO') or die('Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.');

$msgDataSet = __('Add DataSet');
$msgFilter = __('Filter');
$msgShowFilter = __('Show Filter');

?>
<div id="form_container">
	<div id="form_header">
		<div id="form_header_left"></div>
		<div id="form_header_right"></div>
	</div>
	
	<div id="form_body">
		<div class="SecondNav">
			<!-- Maybe at a later date we could have these buttons generated from the DB - and therefore passed through the security system ? -->
			<ul>
				<li><a title="<?php echo $msgDataSet; ?>" class="XiboFormButton" href="index.php?p=dataset&q=AddDataSetForm" ><span><?php echo $msgDataSet; ?></span></a></li>
				<li><a title="<?php echo $msgShowFilter; ?>" href="#" onclick="ToggleFilterView('LayoutFilter')"><span><?php echo $msgFilter; ?></span></a></li>
			</ul>
		</div>
		<?php $this->DataSetFilter(); ?>
	</div>	
		
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>