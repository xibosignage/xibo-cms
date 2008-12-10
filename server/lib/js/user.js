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
$(document).ready(function(){
	
	exec_filter('filter_form','data_table'); //filter on load
	
	$(' :input','#filter_form').change(function(){		
		exec_filter('filter_form','data_table');
	});
	
	$('#add_button').click(function() {
		
		init_button(this,'Add User',exec_filter_callback, set_form_size(600,350));

		return false;
		
	});
	
});

/**
 * The exec filter callback function
 * @param {Object} outputDiv
 */
var exec_filter_callback = function(outputDiv) {
	
	exec_filter('filter_form','data_table');
	
	return false;
}


//function to check valid email address
function isValidEmail(strEmail){
  validRegExp = /^[^@]+@[^@]+.[a-z]{2,}$/i;

   // search email text for regular exp matches
    if (strEmail.search(validRegExp) == -1) {
      return false;
    } 
    return true; 
}