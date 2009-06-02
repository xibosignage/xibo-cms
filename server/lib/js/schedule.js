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

$(document).ready(function() {
	
	// Store the default view for first load
	$('#Calendar').data('view', 'month');
	
	//CallGenerateCalendar();
	
	// Navigational Calendar
	$('#NavCalendar').datepicker({
		onChangeMonthYear: function(year, month) {
			// Store the date on our hidden form (or in the data object)
			$('#Calendar').data('view', 'month').data("date", { year: year, month: month });
			
			// Call the AJAX to refresh
			CallGenerateCalendar();
		}
	});
	
	
});

function CallGenerateCalendar() {
	
	// Pull the data out
	var url			= 'index.php?p=schedule&q=GenerateCalendar&ajax=true';
	var calendar 	= $('#Calendar');
	var view 		= calendar.data('view') || 'month';
	var date		= calendar.data('date') || {year:'', month:''};
	
	var data 		= $.extend(date, {view: view});
	
	$.ajax({
        type: "post",
        url: url,
		data: data,
        dataType: "json",
        success: function(response) {
        
            var respHtml;
            
            if (response.success) {
                respHtml = response.html;
            }
            else {
                // Login Form needed?
                if (response.login) {
                    LoginBox(response.message);
                    return false;
                }
                else {
                    // Just an error we dont know about
                    respHtml = response.message;
                }
            }
            
            $('#Calendar').html(respHtml);
			
			// Call XiboInitialise for this form
			XiboInitialise('#Calendar');
            
            return false;
        }
    });
}


function set_time(hour_period) {
	/* The form fields */
	var form_start = document.getElementById("form_starttime");
	var form_end = document.getElementById("form_endtime");
	
	/* The radio buttons */
	var set_start_time = document.getElementById("heading_select_start");
	var set_end_time = document.getElementById("heading_select_end");
	
	/* The start/end tds */
	var time_for_start = document.getElementById("time_for_start");
	var time_for_end = document.getElementById("time_for_end");
	

	if (set_start_time.checked) {	
		form_start.value = hour_period;
		set_end_time.checked = true;
	}
	else  {
		form_end.value = hour_period;
		set_start_time.checked = true;
	}
	
	
	form_start = document.getElementById("form_starttime");
	form_end = document.getElementById("form_endtime");
	
	time_for_start.innerHTML = format_date(form_start.value);
	time_for_end.innerHTML = format_date(form_end.value)
	
	eval_add_cells(get_hours(form_start.value),get_hours(form_end.value));
	
	return;
}

function format_date(date) {
	
	var d = new Date(date*1000);

	var hours = d.getHours();
	var minutes = d.getMinutes();
	
	if (hours < 10) hours = "0"+hours;
	if (minutes < 10) minutes = "0"+minutes;
	
	
	return hours + ":" + minutes;

}

function get_hours(date) {
	var d = new Date(date*1000);

	var hours = d.getHours();
	
	return hours;
}

function eval_add_cells(start, end) {
	/* Set them all to no color */
	for (var i=0; i<=23; i++) {
		var cell = document.getElementById("add_cell_"+i);
		
		if (i >= start && i <= end) {
			/* Want to set the color of the hour cells based on start and end time */
			//cell.style.backgroundColor = "red;";
			cell.className = "add";
		}
		else {
			/* set to no color */
			//cell.style.backgroundColor = "#f5f5f5";
			cell.className = "";
		}
	}
	return true;
}

function setupScheduleForm() {
    //set up any date fields we have with the date picker
    $('.date-pick').datepicker({
		dateFormat: "dd/mm/yy",
		showOn: "button", 
    	buttonImage: "img/calendar.png", 
    	buttonImageOnly: true,
		beforeShow: customRange
	});

	bindRecurrenceCtl();
	
	$('#rec_type').change(function() {
		bindRecurrenceCtl();
	});
}

function customRange(input) { 
    return {minDate: (input.id == "endtime" ? $("#starttime").datepicker("getDate") : null), 
        maxDate: (input.id == "starttime" ? $("#endtime").datepicker("getDate") : null)}; 
} 

function bindRecurrenceCtl() 
{
	/*
	 * Recurrence
	 * 	If the recurrence type value is NULL then hide the rec_detail and rec_range fields
	 */
	var rec_type 	= $('#rec_type');
	var rec_detail 	= $('#rec_detail');
	var rec_range 	= $('#rec_range');
	
	if (rec_type.val()=="null") {
		rec_detail.parent().parent().hide();
		rec_range.parent().parent().hide();
	}
	else {
		rec_detail.parent().parent().show();
		rec_range.parent().parent().show();
	}
}

function edit_event_callback() {
    init_callback();
    
    set_form_size('900','600');
}