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

    // Bind to the event type dropdown
    $("select#eventTypeId").on("change", function() {
        postProcessLayoutList($(this).val());
    });
    postProcessLayoutList($("select#eventTypeId").val());

    // Bind to the dayParting dropdown
    $("select#dayPartId").on("change", function() {
        postProcessDaypartList($(this).val());
    });
    postProcessDaypartList($("select#dayPartId").val());

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
 * Depending on the event type selected we either want to filter in or filter out the
 * campaigns.
 * @param eventTypeId
 */
function postProcessLayoutList(eventTypeId) {

    $('#campaignId').parent().find(".bootstrap-select li").each(function() {

        if (eventTypeId == 1) {
            // Normal layout event - everything is visible.
            $(this).css("display", "block");
        } else if (eventTypeId == 3) {
            // Overlay layout, hide all campaigns
            if ($(this).data("optgroup") == 1)
                $(this).css("display", "none");
        }
    });
}

function postProcessDaypartList(dayPartId) {

    // The time controls
    var $start = $("input[name=fromDtLink]");
    var $end = $("input[name=toDtLink]");

    // Is this a full control?
    var fullStart = $start.hasClass("dateTimePicker");

    if (dayPartId != 0)
        $end.closest(".form-group").hide();
    else
        $end.closest(".form-group").show();

    if (dayPartId != 0 && dayPartId != 1) {
        // We need to update the date/time controls to only accept the date element
        if (fullStart) {
            // we are not currently a date only control
            $start.removeClass("dateTimePicker").addClass("datePicker").datetimepicker("remove");

            XiboInitialise("#" + $start.closest("form").prop("id"));
        }
    } else {
        // Datetime controls should be full date/time
        if (!fullStart) {
            // we are not currently a full date control
            $start.removeClass("datePicker").addClass("dateTimePicker").datetimepicker("remove");

            XiboInitialise("#" + $start.closest("form").prop("id"));
        }
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

    // Bind to the form submit
    $("#scheduleNowForm").submit(function(e) {
        e.preventDefault();

        var hours = $(form).find("#hours").val();
        var minutes = $(form).find("#minutes").val();
        var seconds = $(form).find("#seconds").val();

        var now = moment();

        // Use Hours, Minutes and Seconds to generate a from date to send to the API
        $(this).append("<input type=\"hidden\" name=\"fromDt\" value=\"" + now.format("YYYY-MM-DD HH:mm:ss") + "\" />");

        if (hours != "")
            now.add(hours, "hours");

        if (minutes != "")
            now.add(minutes, "minutes");

        if (seconds != "")
            now.add(seconds, "seconds");

        $(this).append("<input type=\"hidden\" name=\"toDt\" value=\"" + now.format("YYYY-MM-DD HH:mm:ss") + "\" />");

        $.ajax({
            type: $(this).attr("method"),
            url: $(this).attr("action"),
            data: $(this).serialize(),
            cache: false,
            dataType: "json",
            success: XiboSubmitResponse
        });
    });
};
