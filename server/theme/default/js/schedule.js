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
        
    $('input:checkbox[name=checkAll]', '#checkAllForDisplayList').click(function(){
        $("input:checkbox[name='DisplayGroupIDs[]']", "#DisplayList").attr("checked", this.checked);
        
        CallGenerateCalendar();
    });
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
    
    // We submit this form ourselves (outside framework)
    $('.XiboScheduleForm').validate({
        submitHandler: ScheduleFormSubmit
    });
    
    $(".time-pick", "#div_dialog").setMask({
        mask: "29:59",
        autoTab: false
    }).keypress(function() {
        var currentMask = $(this).data('mask').mask;
        var newMask = $(this).val().match(/^2.*/) ? "23:59" : "29:59";
        if (newMask != currentMask) {
            $(this).setMask({
                mask: newMask,
                autoTab: false
            });
        }
    }).click(function(){
        $(this)[0].select();
    });
}

function displayGridCallback() {

    $('input:checkbox[name=checkAll]', '#div_dialog').click(function(){
        $("input:checkbox[name='DisplayGroupIDs[]']", "#div_dialog").attr("checked", this.checked);
    });
}

function ScheduleFormSubmit(form) {
    // Get the URL from the action part of the form)
    var url = $(form).attr("action") + "&ajax=true";

    // Get additional fields from the form
    var layoutId = $('input:radio[name=CampaignID]:checked', '#div_dialog').val();
    if (layoutId == undefined)
        layoutId = 0;
    
    var displayGroupIds = $("input:checkbox[name='DisplayGroupIDs[]']:checked", "#div_dialog").serialize();
    if (displayGroupIds == undefined)
        displayGroupIds = 0;

    $.ajax({
        type:"post",
        url:url,
        cache:false,
        dataType:"json",
        data:$(form).serialize() + "&CampaignID=" + layoutId + "&" + displayGroupIds,
        success: XiboSubmitResponse
    });

    return;
}

/**
 * Custom range function for date pickers
 * @param {Object} input
 */
function customRange(input) { 
    return {minDate: (input.id == "endtime" ? $("#starttime").datepicker("getDate") : null), 
        maxDate: (input.id == "starttime" ? $("#endtime").datepicker("getDate") : null)}; 
}