/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2015 Daniel Garner
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
var events = [];

$(document).ready(function() {

    // Set a listener for popover clicks
    //  http://stackoverflow.com/questions/11703093/how-to-dismiss-a-twitter-bootstrap-popover-by-clicking-outside
    $('body').on('click', function (e) {
        $('[data-toggle="popover"]').each(function () {
            //the 'is' for buttons that trigger popups
            //the 'has' for icons within a button that triggers a popup
            if (!$(this).is(e.target) && $(this).has(e.target).length === 0 && $('.popover').has(e.target).length === 0) {
                $(this).popover('hide');
            }
        });
    });
	
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

    // Make the select list nicer
    $('#DisplayList').selectpicker();

    // Calendar is initialised without any event_source (that is changed when the selector is used)
    if (($('#Calendar').length > 0)) {
        // Get some options for the calendar
        var calendarOptions = $("#CalendarContainer").data();

        var options = {
            time_start: '00:00',
            time_end: '23:59',
            events_source: function () { return events; },
            view: 'month',
            tmpl_path: function (name) {
                return 'calendar-template-' + name;
            },
            tmpl_cache: true,
            onBeforeEventsLoad: function (done) {

                var calendarOptions = $("#CalendarContainer").data();
                var url = calendarOptions.eventSource;
                events = [];

                // Append display groups
                var displayGroups = $('#DisplayList').serialize();
                if (displayGroups != '')
                    url += '?' + displayGroups;

                // Populate the events array via AJAX
                var params = {
                    "from": this.options.position.start.getTime(),
                    "to": this.options.position.end.getTime()
                }

                $('#calendar-progress').addClass('fa fa-cog fa-spin');

                $.getJSON(url, params)
                    .done(function(data) {
                        events = data.result;

                        if (done != undefined)
                            done();

                        calendar._render();
                        
                        // Hook up any pop-overs (for small events)
                        $('[data-toggle="popover"]').popover({
                            trigger: "click",
                            html: true,
                            placement: "bottom",
                            content: function() {
                                return $(this).html();
                            }
                        })
                        .on('shown.bs.popover', function() {
                            var source = $(this);
                            var popover = source.attr("aria-describedby");

                            $("#" + popover + " a").click(function(e) {
                                e.preventDefault();
                                XiboFormRender($(this));
                                source.popover("hide");
                            });
                        });

                        $('#calendar-progress').removeClass('fa fa-cog fa-spin');
                    })
                    .fail(function() {
                        $('#calendar-progress').removeClass('fa fa-cog fa-spin');

                        if (done != undefined)
                            done();

                        calendar._render();

                        toastr.error(translate.error);
                    });
            },
            onAfterEventsLoad: function(events) {
                if(!events) {
                    return;
                }
            },
            onAfterViewLoad: function(view) {
                if (typeof this.getTitle === "function")
                    $('h1.page-header').text(this.getTitle());

                $('.btn-group button').removeClass('active');
                $('button[data-calendar-view="' + view + '"]').addClass('active');
            },
            language: calendarLanguage
        };

        options.type = calendarOptions.calendarType;
        calendar = $('#Calendar').calendar(options);

        // Set up our display selector control
        $('#DisplayList').on('change', function(){
            setTimeout(calendar.view(), 1000);
        });
    }
});

/**
 * Callback for the schedule form
 */
var setupScheduleForm = function(dialog) {

    // Select lists
    $('#campaignId', dialog).selectpicker();
    $('select[name="displayGroupIds[]"]', dialog).selectpicker();
    $('select[name="recurrenceRepeatsOn[]"]', dialog).selectpicker();
    
    // Hide/Show form elements according to the selected options
    // Initial state of the components
    processScheduleFormElements($("#recurrenceType"));
    processScheduleFormElements($("#eventTypeId"));

    // Events on change
    $("#recurrenceType, #eventTypeId, #dayPartId").on("change", function() { processScheduleFormElements($(this)) });

    // Bind to the dialog submit
    $("#scheduleAddForm, #scheduleEditForm, #scheduleDeleteForm").submit(function(e) {
        e.preventDefault();

        var form = $(this);

        $.ajax({
            type: $(this).attr("method"),
            url: $(this).attr("action"),
            data: $(this).serialize(),
            cache: false,
            dataType: "json",
            success: function(xhr, textStatus, error) {

                XiboSubmitResponse(xhr, form);

                if (xhr.success) {
                    // Reload the Calendar
                    calendar.view();
                }
            }
        });
    });
};

/**
 * Process schedule form elements for the purpose of showing/hiding them
 * @param el jQuery element
 */
var processScheduleFormElements = function(el) {
    
    var fieldVal = el.val();
    
    switch (el.attr('id')) {
        case 'recurrenceType':
            //console.log('Process: recurrenceType, val = ' + fieldVal);

            var repeatControlGroupDisplay = (fieldVal == "") ? "none" : "block";
            var repeatControlGroupWeekDisplay = (fieldVal != "Week") ? "none" : "block";

            $(".repeat-control-group").css('display', repeatControlGroupDisplay);
            $(".repeat-weekly-control-group").css('display', repeatControlGroupWeekDisplay);
            
            break;
        
        case 'eventTypeId':
            //console.log('Process: eventTypeId, val = ' + fieldVal);
            
            var layoutControlDisplay = (fieldVal == "2") ? "none" : "block";
            var endTimeControlDisplay = (fieldVal == "2") ? "none" : "block";
            var startTimeControlDisplay = (fieldVal == "2") ? "block" : "block";
            var dayPartControlDisplay = (fieldVal == "2") ? "none" : "block";
            var commandControlDisplay = (fieldVal == "2") ? "block" : "none";
            
            $(".layout-control").css('display', layoutControlDisplay);
            $(".endtime-control").css('display', endTimeControlDisplay);
            $(".starttime-control").css('display', startTimeControlDisplay);
            $(".day-part-control").css('display', dayPartControlDisplay);
            $(".command-control").css('display', commandControlDisplay);

            // Depending on the event type selected we either want to filter in or filter out the
            // campaigns.
            $('#campaignId').parent().find(".bootstrap-select li").each(function() {
                if (fieldVal == 1) {
                    // Normal layout event - everything is visible.
                    $(this).css("display", "block");
                } else if (fieldVal == 3) {
                    // Overlay layout, hide all campaigns
                    if ($(this).data("optgroup") == 1)
                        $(this).css("display", "none");
                }
            });
            
            // Call funtion for the daypart ID 
            processScheduleFormElements($('#dayPartId'));
            
            break;
        
        case 'dayPartId':
            //console.log('Process: dayPartId, val = ' + fieldVal);
            
            var endTimeControlDisplay = (fieldVal != 0) ? "none" : "block";
            var startTimeControlDisplay = (fieldVal == "1") ? "none" : "block";

            $(".endtime-control").css('display', endTimeControlDisplay);
            $(".starttime-control").css('display', startTimeControlDisplay);

            // Dayparts only show the start control
            var $startTime = $("input[name=fromDt_Link2]");

            // Should we show the time element
            if (fieldVal != 0 && fieldVal != 1) {
                // We need to update the date/time controls to only accept the date element
                $startTime.hide();
            } else {
                $startTime.show();
            }
                        
            break;
    }
}

/**
 * Callback for the schedule form
 */
var setupScheduleNowForm = function(form) {
    
    // We submit this form ourselves (outside framework)
    $('#campaignId', form).selectpicker();
    $('select[name="displayGroupIds[]"]', form).selectpicker();

    // Hide the seconds input option unless seconds are enabled in the date format
    if (dateFormat.indexOf("s") <= -1) {
        $(form).find(".schedule-now-seconds-field").hide();
    }

    var evaluateDates = $.debounce(500, function() {
        var hours = $(form).find("#hours").val();
        var minutes = $(form).find("#minutes").val();
        var seconds = $(form).find("#seconds").val();

        //var fromDt = moment().add(-24, "hours");
        var fromDt = moment();
        var toDt = moment();

        // Use Hours, Minutes and Seconds to generate a from date
        var $messageDiv = $('.scheduleNowMessage');

        if (hours != "")
            toDt.add(hours, "hours");

        if (minutes != "")
            toDt.add(minutes, "minutes");

        if (seconds != "")
            toDt.add(seconds, "seconds");

        // Update the message div
        $messageDiv.html($messageDiv.data().template.replace("[fromDt]", fromDt.format(jsDateFormat)).replace("[toDt]", toDt.format(jsDateFormat))).removeClass("hidden");

        // Update the final submit fields
        $("#fromDt").val(fromDt.format(systemDateFormat));
        $("#toDt").val(toDt.format(systemDateFormat));
    });

    // Bind to the H:i:s fields
    $(form).find("#hours").on("keyup", evaluateDates);
    $(form).find("#minutes").on("keyup", evaluateDates);
    $(form).find("#seconds").on("keyup", evaluateDates);
};
