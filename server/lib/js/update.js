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
	//check for updates with another server (cross domain scripting?)
	/*$.ajax({type:"post", url:"index.php?p=update&q=get_update", datatype:"html",
		success:function(transport) {
			//this was our get data
			
		}
	});*/

	$('form.update').submit(function() {
	
		//confirm
		if (!confirm("Are you sure you want to update?")) {
			return false
		}
		
		var url_loc = $(this).attr("action");
		
		$.ajax({type:"post", url:url_loc, datatype:"html", data:$(this).formSerialize(), 
			success:function(transport) {
				var response = transport.split('|');
				
				if (response[0] == '1') {
					//success
					$('#data_table').html(response[1]);
					
					$('#details').hide();
					
					$('#details_trigger').click(function() {
						toggle_div_view('details');
					});
				}
				else if (response[0] == '0') {
					//database failure
					alert(response[1]);
				}
				else {
					alert("An unknown error occured");
				}
			}
		});
		
		return false;		
	});
});