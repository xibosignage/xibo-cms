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

// Global calendar object
var calendar;

$(document).ready(function() {
	
    // Set up the navigational controls
    $('.btn-group button[data-calendar-nav]').each(function() {
        var $this = $(this);
        $this.click(function() {
            calendar.navigate($this.data('calendar-nav'));
        });
    });

    $('.btn-group button[data-calendar-view]').each(function() {
        var $this = $(this);
        $this.click(function() {
            calendar.view($this.data('calendar-view'));
        });
    });

    var options = {
        events_source: function () { return []; },
        view: 'month',
        tmpl_path: "theme/default/libraries/calendar/tmpls/",
        tmpl_cache: true,
        onAfterEventsLoad: function(events) {
            if(!events) {
                return;
            }
        },
        onAfterViewLoad: function(view) {
            $('h1.page-header').text(this.getTitle());
            $('.btn-group button').removeClass('active');
            $('button[data-calendar-view="' + view + '"]').addClass('active');
        }
    };

    // Calendar is initialised without any event_source (that is changed when the selector is used)
    if (($('#Calendar').length > 0))
        calendar = $('#Calendar').calendar(options);

    // Set up our display selector control
    $('#DisplayList').on('change', function(){
        CallGenerateCalendar();
    });

    // Generate the calendar now we have a list set up
    CallGenerateCalendar();
});

/**
 * Generates the Calendar
 */
function CallGenerateCalendar() {
    
    var url = 'index.php?p=schedule&q=GenerateCalendar&ajax=true';

    // Append display groups
    var displayGroups = $('#DisplayList').serialize();
    if (displayGroups != '') 
        url += '&' + displayGroups;

    // Override the calendar URL
    calendar.setOptions({events_source: url});

    // Navigate
    calendar.view();
}

/**
 * Callback for the schedule form
 */
var setupScheduleForm = function() {
    //set up any date fields we have with the date picker
    $('.date-pick').datetimepicker({
            language: "en",
            pickSeconds: false
        });
    
    // We submit this form ourselves (outside framework)
    $('.XiboScheduleForm').validate({
        submitHandler: ScheduleFormSubmit,
        errorElement: "span",
        highlight: function(element) {
            $(element).closest('.form-group').removeClass('has-success').addClass('has-error');
        },
        success: function(element) {
            $(element).closest('.form-group').removeClass('has-error').addClass('has-success');
        }
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

/**
 * Callback for the schedule form
 */
var setupScheduleNowForm = function() {
    
    // We submit this form ourselves (outside framework)
    $('.XiboScheduleForm').validate({
        submitHandler: ScheduleNowFormSubmit,
        errorElement: "span",
        highlight: function(element) {
            $(element).closest('.form-group').removeClass('has-success').addClass('has-error');
        },
        success: function(element) {
            $(element).closest('.form-group').removeClass('has-error').addClass('has-success');
        }
    });    
}

function ScheduleNowFormSubmit(form) {
    // Get the URL from the action part of the form)
    var url = $(form).attr("action") + "&ajax=true";
    
    var displayGroupIds = $("input:checkbox[name='DisplayGroupIDs[]']:checked", $(form).closest(".modal")).serialize();
    if (displayGroupIds == undefined)
        displayGroupIds = 0;
    
    $.ajax({
        type:"post",
        url:url,
        cache:false,
        dataType:"json",
        data:$(form).serialize() + "&" + displayGroupIds,
        success: XiboSubmitResponse
    });

    return;
}
