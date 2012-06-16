<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2012 Daniel Garner
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

$msgFilter		= __('Filter');
$msgShowFilter	= __('Show Filter');

// We can do this because this is included from the page class in "displayPage"
$user =& $this->user;

$p = Kit::GetParam('p', _REQUEST, _WORD);
$q = Kit::GetParam('q', _REQUEST, _WORD);
?>
<div id="form_container">
	<div id="form_header">
		<div id="form_header_left">
		</div>
            <div id="secondaryMenu">
                    <ul id="menu" style="padding-left: 26.5em;">
<?php
// Put a menu here
if (!$menu = new MenuManager($db, $user, 'Design Menu')) trigger_error($menu->message, E_USER_ERROR);

while ($menuItem = $menu->GetNextMenuItem())
{
    $uri 	= Kit::ValidateParam($menuItem['name'], _WORD);
    $args 	= Kit::ValidateParam($menuItem['Args'], _STRING);
    $class 	= Kit::ValidateParam($menuItem['Class'], _WORD);
    $title 	= Kit::ValidateParam($menuItem['Text'], _STRING);
    $title 	= __($title);

    // Extra style for the current one
    if ($p == $uri)
        $class = 'current ' . $class;

    $href = 'index.php?p=' . $uri . '&' . $args;

    echo '<li class="' . $class . '"><a href="' . $href . '" class="' . $class . '">' . $title . '</a></li>';
}
?>
                    </ul>
                </div>
		<div id="form_header_right">
		</div>
	</div>

	<div id="form_body">
		<div class="SecondNav">
			<!-- Maybe at a later date we could have these buttons generated from the DB - and therefore passed through the security system ? -->
			<ul>
				<li><a title="<?php echo $msgShowFilter; ?>" href="#" onclick="ToggleFilterView('TemplateFilter')"><span><?php echo $msgFilter; ?></span></a></li>
			</ul>
		</div>
		<?php $this->TemplateFilter(); ?>
	</div>
	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>	