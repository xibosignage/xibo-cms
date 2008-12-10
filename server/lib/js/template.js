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
var exec_filter_callback = function(outputDiv) {
	
	exec_filter('filter_form','data_table');
	
	return false;
}


//when the DOM is ready
$(document).ready(function(){
	
	exec_filter('filter_form','data_table'); //exec the filter onload
	
	//init the filter bind
	$(' :input','#filter_form').change(function(){
		$('#pages').attr("value","1");
		
		exec_filter('filter_form','data_table');
	});
	
	//make sure the form doesnt get submitted using the traditional method
	$('#filter_form').submit(function(){
		return false;
	});
	
	/**
	 * Buttons
	 *
	 */
	$('#add_button').click(function() {
		
		init_button(this,'Add Template',exec_filter_callback);

		return false;
		
	});
	
});