/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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
function ManageMembersCallBack()
{
    $("#usersIn, #usersOut").sortable({
            connectWith: '.connectedSortable',
            dropOnEmpty: true
    }).disableSelection();

    $(".li-sortable", "#div_dialog").dblclick(switchLists);
}

function MembersSubmit() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#usersIn").attr('href') + "&ajax=true";

    // Get the two lists
    serializedData = $("#usersIn").sortable('serialize');

    $.ajax({
            type: "post",
            url: href,
            cache: false,
            dataType: "json",
            data: serializedData,
            success: XiboSubmitResponse
    });

    return;
}