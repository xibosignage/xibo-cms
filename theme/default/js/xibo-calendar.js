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
                    XiboFormRender($(this).attr('href'));
                    source.popover("hide");
                });
            });
        },
        language: calendarLanguage
    };

    // Calendar is initialised without any event_source (that is changed when the selector is used)
    if (($('#Calendar').length > 0)) {
        options.type = calendarType;
        calendar = $('#Calendar').calendar(options);

        // Set up our display selector control
        $('#DisplayList').on('change', function(){
            setTimeout(CallGenerateCalendar(), 1000);
        });

        // Make the select list nicer
        $('#DisplayList').selectpicker();

        // Generate the calendar now we have a list set up
        CallGenerateCalendar();
    }
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
    calendar.setOptions({events_source: url, time_start: '00:00', time_end: '00:00'});

    // Navigate
    calendar.view();
}

/**
 * Callback for the schedule form
 */
var setupScheduleForm = function(form) {

    // Select lists
    $('#CampaignID', form).selectpicker();
    $('select[name="DisplayGroupIDs[]"]', form).selectpicker();

    // Set up any date fields we have with the date picker
    $('#starttimeControl', form).datetimepicker({
        format: "dd MM yyyy - hh:ii",
        linkField: "starttime",
        linkFormat: "yyyy-mm-dd hh:ii",
        minuteStep: 5,
        autoClose: true,
        language: language,
        calendarType: calendarType
    });

    $('#endtimeControl', form).datetimepicker({
        format: "dd MM yyyy - hh:ii",
        linkField: "endtime",
        linkFormat: "yyyy-mm-dd hh:ii",
        minuteStep: 5,
        autoClose: true,
        language: language,
        calendarType: calendarType
    });

    $('#rec_rangeControl', form).datetimepicker({
        format: "dd MM yyyy - hh:ii",
        linkField: "rec_range",
        linkFormat: "yyyy-mm-dd hh:ii",
        minuteStep: 5,
        autoClose: true,
        language: language,
        calendarType: calendarType
    });
};

/**
 * Callback for the schedule form
 */
var setupScheduleNowForm = function(form) {
    
    // We submit this form ourselves (outside framework)
    $('#CampaignID', form).selectpicker();
    $('select[name="DisplayGroupIDs[]"]', form).selectpicker();
};
