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
 * 	pager_id = The ID of this pager control
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="pagination pull-right" id="<?php echo Theme::Get('pager_id'); ?>">
    <form class="form-inline">
        <span class="first glyphicon glyphicon-fast-backward"></span>
        <span class="prev glyphicon glyphicon-step-backward"></span>
        <input type="text" class="form-control pagedisplay"/>
        <span class="next glyphicon glyphicon-step-forward"></span>
        <span class="last glyphicon glyphicon-fast-forward"></span>
        <select class="form-control pagesize input-mini">
            <option value="5">5</option>
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="30">30</option>
            <option value="40">40</option>
        </select>
    </form>
</div>