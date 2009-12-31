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
$(document).ready(function() {
	
});

var exec_filter_callback = function() {
	
}

var submit_form_callback = function(outputDiv) {
	
	//Just refresh
	//window.location = window.location.href;
	
	return false;
}

var region_options_callback = function(outputDiv)
{
	set_form_size(830,450);
	
	//Get all the tooltip_hidden
	$(".tooltip_hidden").parent().hover(function()
	{
		//Change the hidden div's content
		$('#tooltip_hover').html($(".tooltip_hidden",this).html()).css("left",$(this).css("left")).show();
	}, function() 
	{
		$('#tooltip_hover').hide();
	});
	
	//Make the elements draggable
	$(".timebar_ctl").draggable({
		containment: document.getElementById("timeline_ctl")
	});
	
	$(".mediabreak").droppable({
		accept: ".timebar_ctl",
		drop: function(ev, ui) {
			orderRegion(ui, this);
		}
	});
}

function orderRegion(timeBar, mediaBreak){
	var layoutid = $(timeBar.element.offsetParent).attr("layoutid");
	var regionid = $(timeBar.element.offsetParent).attr("regionid");
	var mediaid = $(timeBar.element).attr("mediaid");
	var sequence = $(mediaBreak).attr("breakid");
	
	$.ajax({
		type: "post",
		url: "index.php?p=layout&q=RegionOrder&layoutid=" + layoutid + "&callingpage=mediamanager&ajax=true",
		cache: false,
		datatype: "html",
		data: {
			"mediaid": mediaid,
			"sequence": sequence,
			"regionid": regionid
		},
		success: function(transport){
		
			var response = transport.split('|');
			
			if (response[0] == '0') {
				//success
				//Post notice somewhere?
			}
			else 
				if (response[0] == '1') //failure
				{
				
					alert(response[1]);
				}
				else 
					if (response[0] == '2') //login
					{
						alert("You need to login");
					}
					else 
						if (response[0] == '3') {
							window.location = response[1]; //redirect
						}
						else 
							if (response[0] == '6') //success, load form
							{
								//we need: uri, callback, onsubmit
								var uri = response[1];
								var callback = response[2];
								var onsubmit = response[3];
								
								load_form(uri, $('#div_dialog'), callback, onsubmit);
							}
							else {
								alert("An unknown error occured");
							}
			
			return false;
		}
	});
}

function dialog_filter() {
	exec_filter('stack_filter_form','dialog_grid');
	
	return false;
}

/**
 * Handles the tRegionOptions trigger
 */
function tRegionOptions()
{
	var regionid = gup("regionid");
	var layoutid = gup("layoutid");
	
	load_form('index.php?p=layout&layoutid='+layoutid+'&regionid='+regionid+'&q=RegionOptions', $('#div_dialog'),'',region_options_callback);
}