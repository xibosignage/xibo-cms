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
	$('#tabs').tabs(1,{ fxSlide: true, fxFade: true, fxSpeed: 'fast' });

	//listener on the Test Email button
	$('#test_email').click(function() {
	
		var url = $(this).attr("href");
		
		//POST request to the admin page
		$.ajax({type:"post", url:url, datatype:"html", 
			success:function(transport) {
				var response = transport.split('|');
				
				if (response[0] == '1') {
					//sucess
					alert(response[1]);
				}
				else if (response[0] == '0') {
					//failure
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