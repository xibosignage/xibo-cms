/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2013 Daniel Garner
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

    $('#Calendar').each(function (){


        var picker = $('#main-calendar-picker').datetimepicker({
                language: "en",
                pickTime: false
            });
            
        picker.on('changeDate', function(e) {
                
                // Store the date on our hidden form (or in the data object)
                $('#Calendar').data({
                    view: 'month',
                    date: e.date,
                    localDate: e.localDate
                });
                
                // Call the AJAX to refresh
                CallGenerateCalendar();
            });

        picker.data('datetimepicker').setDate(new Date());
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
	var date			= calendar.data('date') || new Date();
	var displayGroups	= $('#DisplayList').serialize();
	
	var data 			= $.extend({date: date.toISOString()}, {view: view});
	
	if (displayGroups != '') 
        url += '&' + displayGroups;
	
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

            // Make sure the calendar will fit in the window
            var height = $("#Calendar").parent().height();
            $("#Calendar").height($(window).height() * 0.8);
            $("#display-list-well").css("max-height", (($(window).height() * 0.8) - 110) + "px");
            
            return false;
        }
    });
}

/**
 * Callback fired when the Display Select List on the left hand pane is rendered
 */
function DisplayListRender() {
    // Bind a click event to all the display list checkboxes
    $('.DisplayListForm input[type=checkbox]').click(function(){
            CallGenerateCalendar();
    });

    CallGenerateCalendar();
        
    $('input:checkbox[name=checkAll]', '#checkAllForDisplayList').click(function(){
        $("input:checkbox[name='DisplayGroupIDs[]']", ".DisplayListForm").prop("checked", this.checked);
        
        CallGenerateCalendar();
    });
}

/**
 * Callback for the schedule form
 */
function setupScheduleForm() {
    //set up any date fields we have with the date picker
    $('.date-pick').datetimepicker({
            language: "en",
            pickSeconds: false
        });
    
    // We submit this form ourselves (outside framework)
    $('.XiboScheduleForm').validate({
        submitHandler: ScheduleFormSubmit
    });    
}

/**
 * Call back fired by the Display Grid on the Add/Edit Event Form.
 * @return {[type]} [description]
 */
function displayGridCallback(gridId) {

    var modal = $('#' + gridId).closest(".modal");

    $('input:checkbox[name=checkAll]', modal).click(function(){
        $("input:checkbox[name='DisplayGroupIDs[]']", modal).prop("checked", this.checked);
    });
}

function ScheduleFormSubmit(form) {
    // Get the URL from the action part of the form)
    var url = $(form).attr("action") + "&ajax=true";

    // Get additional fields from the form
    var layoutId = $('input:radio[name=CampaignID]:checked').val();
    if (layoutId == undefined)
        layoutId = 0;
    
    var displayGroupIds = $("input:checkbox[name='DisplayGroupIDs[]']:checked", $(form).closest(".modal")).serialize();
    if (displayGroupIds == undefined)
        displayGroupIds = 0;

    // Get the values from our datepickers
    var startDate = dateFormat($("#starttime").closest(".date-pick").data('datetimepicker').getLocalDate(), "isoDateTime");
    var endDate = dateFormat($("#endtime").closest(".date-pick").data('datetimepicker').getLocalDate(), "isoDateTime");
    var recurUntil = dateFormat($("#rec_range").closest(".date-pick").data('datetimepicker').getLocalDate(), "isoDateTime");

    $.ajax({
        type:"post",
        url:url,
        cache:false,
        dataType:"json",
        data:$(form).serialize() + "&CampaignID=" + layoutId + "&" + displayGroupIds + "&iso_starttime=" + startDate + "&iso_endtime=" + endDate + "&iso_rec_range=" + recurUntil,
        success: XiboSubmitResponse
    });

    return;
}
