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
<div id="form_container">
	<div id="form_header">
		<div id="form_header_left">
		</div>
		<div id="form_header_right">
		</div>
	</div>

	<div id="form_body">
			<div id="dashbuttons">
				<?php
					// Put a menu here
					if (!$menu = new MenuManager($db, $user, 'Dashboard')) trigger_error($menu->message, E_USER_ERROR);
					
					while ($menuItem = $menu->GetNextMenuItem())
					{
						$uri 	= Kit::ValidateParam($menuItem['name'], _WORD);
						$args 	= Kit::ValidateParam($menuItem['Args'], _STRING);
						$class 	= Kit::ValidateParam($menuItem['Class'], _WORD);
						$title 	= Kit::ValidateParam($menuItem['Text'], _STRING);
						$title 	= __($title);
						$img 	= Kit::ValidateParam($menuItem['Img'], _STRING);
						
						$href = 'index.php?p=' . $uri . '&' . $args;
						
						// Override the HREF for the Manual Button
						if ($uri == 'manual')
							$href = $args;
						
						$out = <<<END
							<div class="dashicons">
								<a id="$class" alt="$title" href="$href">
								<img class="dash_button" src="$img"/>
								<span class="dash_text">$title</span></a>
							</div>
END;
						echo $out;
					}
				?>
			</div>	
	</div>
	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>