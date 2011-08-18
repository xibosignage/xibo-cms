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
var text_callback = function()
{
    // Conjure up a text editor
    $("#ta_text").ckeditor();

    // Make sure when we close the dialog we also destroy the editor
    $("#div_dialog").bind("dialogclose.xibo", function(event, ui){
        $("#ta_text").ckeditorGet().destroy();
        $("#div_dialog").unbind("dialogclose.xibo");
    })

    var regionid = $("#iRegionId").val();
    var width = $("#region_"+regionid).width();
    var height = $("#region_"+regionid).height();

    // Min width
    if (width < 800) width = 800;

    // Adjust the width and height
    width = width + 80;
    height = height + 295;

    $('#div_dialog').height(height+"px");
    $('#div_dialog').dialog('option', 'width', width);
    $('#div_dialog').dialog('option', 'height', height);
    $('#div_dialog').dialog('option', 'position', 'center');

    return false; //prevent submit
}

var microblog_callback = function()
{
    // Conjure up a text editor
    $("#ta_template").ckeditor();
    $("#ta_nocontent").ckeditor();

    // Make sure when we close the dialog we also destroy the editor
    $("#div_dialog").bind("dialogclose.xibo", function(event, ui){
        $("#ta_template").ckeditorGet().destroy();
        $("#ta_nocontent").ckeditorGet().destroy();

        $("#div_dialog").unbind("dialogclose.xibo");
    })

    var regionid = $("#iRegionId").val();
    var width = $("#region_"+regionid).width();
    var height = $("#region_"+regionid).height();

    //Min width
    if (width < 800) width = 800;
    height = height - 170;

    // Min height
    if (height < 300) height = 300;

    width = width + 80;
    height = height + 480;

    $('#div_dialog').height(height+"px");
    $('#div_dialog').dialog('option', 'width', width);
    $('#div_dialog').dialog('option', 'height', height);
    $('#div_dialog').dialog('option', 'position', 'center');

    return false; //prevent submit
}