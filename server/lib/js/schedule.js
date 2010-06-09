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

        // The Calendar will be generated when the display list is loaded
	
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

/**
 * Generates the Calendar
 */
function CallGenerateCalendar() {
	// Pull the data out
	var url				= 'index.php?p=schedule&q=GenerateCalendar&ajax=true';
	var calendar 		= $('#Calendar');
	var view 			= calendar.data('view') || 'month';
	var date			= calendar.data('date') || {year:'', month:''};
	var displayGroups	= $('#DisplayList').serialize();
	
	var data 			= $.extend(date, {view: view});
	
	if (displayGroups != '') url += '&' + displayGroups;
	
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
			
			$('div.LongEvent').corner('5px');
            
            return false;
        }
    });
}

/**
 * Callback for the display list renderer
 */
function DisplayListRender() {
	// Bind a click event to all the display list checkboxes
	$('#DisplayList input[type=checkbox]').click(function(){
		CallGenerateCalendar();
	});

        CallGenerateCalendar();
}

/**
 * Callback for the schedule form
 */
function setupScheduleForm() {
    //set up any date fields we have with the date picker
    $('.date-pick').datepicker({
        dateFormat: "dd/mm/yy",
        beforeShow: customRange
    });

	bindRecurrenceCtl();
	
	$('#rec_type').change(function() {
		bindRecurrenceCtl();
	});
}

/**
 * Custom range function for date pickers
 * @param {Object} input
 */
function customRange(input) { 
    return {minDate: (input.id == "endtime" ? $("#starttime").datepicker("getDate") : null), 
        maxDate: (input.id == "starttime" ? $("#endtime").datepicker("getDate") : null)}; 
}

/**
 * Recurrance controls show / hide
 */
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