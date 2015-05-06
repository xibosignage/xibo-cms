<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
 *
 * Theme variables:
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="row">
	<div class="col-md-12">
		<h2><?php echo Theme::Translate('Report a fault with Xibo'); ?></h2>
		<p><?php echo Theme::Translate('Before reporting a fault it would be appreciated if you follow the below steps.'); ?></p>

		<div class="ReportFault">
		<ol>
		<li><p><?php echo Theme::Translate('Check that the Environment passes all the Xibo Environment checks.'); ?></p>
		<?php echo Theme::Get('environment_check'); ?>
		</li>

		<li><p><?php echo Theme::Translate('Turn ON full auditing and debugging.'); ?></p>
			<form id="1" class="XiboAutoForm" action="index.php?p=admin" method="post">
				<input type="hidden" name="q" value="SetMaxDebug" />
				<input class="btn btn-default" type="submit" value="<?php echo Theme::Translate('Turn ON Debugging'); ?>" />
			</form>
		</li>

		<li><p><?php echo Theme::Translate('Recreate the Problem in a new window.'); ?></p>
		</li>

		<li><p><?php echo Theme::Translate('Automatically collect and export relevant information into a text file.'); ?> <?php echo Theme::Translate('Please save this file to your PC.'); ?></p>
		<a class="btn btn-default" href="<?php echo Theme::Get('collect_data_url'); ?>" title="Collect Data"><?php echo Theme::Translate('Collect and Save Data'); ?></a>
		</li>

		<li><p><?php echo Theme::Translate('Turn full auditing and debugging OFF.'); ?></p>
			<form id="2" class="XiboAutoForm" action="index.php?p=admin" method="post">
				<input type="hidden" name="q" value="SetMinDebug" />
				<input class="btn btn-default" type="submit" value="<?php echo Theme::Translate('Turn OFF Debugging'); ?>" />
			</form>
		</li>

		<li><p><?php echo Theme::Translate('Click on the below link to open the bug report page for this release.'); ?> <?php echo Theme::Translate('Describe the problem and upload the file you obtained earlier.'); ?></p>
		<a class="btn btn-default" href="https://community.xibo.org.uk/c/support" title="<?php echo Theme::Translate('Ask a question'); ?>" target="_blank"><?php echo Theme::Translate('Ask a question'); ?></a>
		</li>

		</ol>
		</div>

		<div class="ReportFault">
		 <h2>Further Action</h2>
		 <p><?php echo Theme::Translate('We will do our best to use the information collected above to solve your issue.'); ?>
		 <?php echo Theme::Translate('However sometimes this will not be enough and you will be asked to put your Xibo installation into "Test" mode.'); ?></p>

		<ol>

		<li><p><?php echo Theme::Translate('Switch to Test Mode.'); ?></p>
			<form class="XiboAutoForm" action="index.php?p=admin" method="post">
				<input type="hidden" name="q" value="SetServerTestMode" />
				<input class="btn btn-default" type="submit" value="<?php echo Theme::Translate('Switch to Test Mode'); ?>" />
			</form>
		</li>

		<li><p><?php echo Theme::Translate('Recreate the Problem in a new window and Capture a screenshot.'); ?><?php echo Theme::Translate('You should post your screenshot in the same topic as the question you asked previously.'); ?></p>
		</li>

		<li><p><?php echo Theme::Translate('Switch to Production Mode.'); ?></p>
			<form class="XiboAutoForm" action="index.php?p=admin" method="post">
				<input type="hidden" name="q" value="SetServerProductionMode" />
				<input class="btn btn-default" type="submit" value="<?php echo Theme::Translate('Switch to Production Mode'); ?>" />
			</form>
		</li>
			
		</ol>
		</div>
	</div>
</div>