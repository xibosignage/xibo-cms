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

                if (this.options.view != 'agenda') {

                    // Append display groups
                    var displayGroups = $('#DisplayList').serialize();
                    
                    var url = calendarOptions.eventSource;
                    
                    if (displayGroups != '')
                        url += '?' + displayGroups;
                        
                    events = [];
                    
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
                } else {
                    
                    // Get selected display groups
                    var selectedDisplayGroup = $('.cal-context').data().selectedTab;
                    var displayGroupsList = {};
                    var chooseAllDisplays = false;
                    
                    // Find selected display group and create a display group list used to create tabs
                    $('#DisplayList option').each( function(){
                            var $self = $(this);
                            
                            // If the all option is selected 
                            if ($self.val() == -1 && $self.is(':selected')){
                                chooseAllDisplays = true;
                                return true;
                            }
                                
                            if ($self.is(':selected') || chooseAllDisplays){
                                displayGroupsList[$self.val()] = $self.html();
                                
                                if (typeof selectedDisplayGroup == 'undefined')
                                    selectedDisplayGroup = $self.val(); 
                            }
                    });
                        
                    var url = calendarOptions.agendaLink.replace(":id", selectedDisplayGroup);
                                    
                    var dateMoment = moment(this.options.position.start.getTime());
                    var timeFromSlider = ( $('#timePickerSlider').length ) ? $('#timePicker').slider('getValue') : 0
                    var timeMoment = moment(timeFromSlider*60*1000);
                    
                    // Add hour to date to get the selected date
                    var dateSelected = moment(dateMoment + timeMoment);

                    // Populate the events array via AJAX
                    var params = {
                        "date": dateSelected.format(systemDateFormat)
                    }
                    
                    $('#calendar-progress').addClass('fa fa-cog fa-spin');
                    
                    // if the result are empty create a empty object and reset the results
                    if(jQuery.isEmptyObject(events['results'])){
                        
                        // events var must be an array for compatibility with the previous implementation
                        events = [];
                        events['results'] = {}; 
                    }
                    
                    // Save displaygroup list and the selected display
                    events['displayGroupList'] = displayGroupsList;
                    events['selectedDisplayGroup'] = selectedDisplayGroup; 
                    
                    // Clean error message
                    events['errorMessage'] = '';
                                
                        
                    // 1 - if there are no displaygroups selected
                    if ($('#DisplayList').val() == null) {
                        
                        events['errorMessage'] = 'display_not_selected';
                        calendar._render();
                        
                        $('#calendar-progress').removeClass('fa fa-cog fa-spin');
                        
                    } else if(!jQuery.isEmptyObject(events['results'][selectedDisplayGroup]) && events['results'][selectedDisplayGroup]['request_date'] == params.date) {
                        // 2 - Use cache if the element was already saved for the requested date
                        console.log('Use cache for ' + selectedDisplayGroup + ' on ' + events['results'][selectedDisplayGroup]['request_date']);   
                        if (done != undefined)
                            done();
                            
                        calendar._render();

                        $('#calendar-progress').removeClass('fa fa-cog fa-spin');
                    } else {
                        // 3 - make request to get the data for the events
                        
                        console.log('Make request for ' + selectedDisplayGroup + ' on ' + params.date);
                        
                        $.getJSON(url, params)
                            .done(function(data) {
                                
                                if(!jQuery.isEmptyObject(data.data) && data.data.events.length > 0){
                                    events['results'][String(selectedDisplayGroup)] = data.data;
                                    events['results'][String(selectedDisplayGroup)]['request_date'] = params.date;
                                } else {
                                    events['errorMessage'] = 'no_events';
                                }
                                
                                if (done != undefined)
                                    done();
                                    
                                console.log("Result:");
                                console.log(events);
                                    
                                calendar._render();

                                $('#calendar-progress').removeClass('fa fa-cog fa-spin');
                            })
                            .fail(function(data) {
                                // Deal with the failed request

                                if (done != undefined)
                                    done();
                                
                                events['errorMessage'] = 'request_failed';
                                
                                calendar._render();
                                
                                $('#calendar-progress').removeClass('fa fa-cog fa-spin');
                            });
                    }
                        
                }
                
            },
            onAfterEventsLoad: function(events) {
                if(!events) {
                    return;
                }
            },
            onAfterViewLoad: function(view) {
                
                // Show time slider on agenda view and call the calendar view on slide stop event
                if (this.options.view == 'agenda') {
                    $('.cal-event-time-bar').show();
                    
                    $('#timePicker').slider({
                        tooltip: 'always',
                        step: 5,
                        formatter: function(value) {
                            return moment(value*60*1000).format(jsTimeFormat);
                        }
                    }).off('slideStop').on('slideStop', function(ev) {
                        calendar.view();
                    });
                } else {
                    $('.cal-event-time-bar').hide();
                }
                
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
        
        // Set event when clicking on a tab, to refresh the view
        $('.cal-context').on('click', 'a[data-toggle="tab"]', function (e) {
            $('.cal-context').data().selectedTab = $(this).data("id");
            calendar.view();
        });
        
        // When selecting a layout row, create a Breadcrumb Trail and select the correspondent Display Group(s) and the Campaign(s)
        $('.cal-context').on('click', 'tbody tr', function (e) {
            var $self = $(this);
            
            $('.cal-event-breadcrumb-trail').hide();
            $('.cal-context tbody tr').removeClass('selected');
            $('.cal-context tbody tr').removeClass('selected-linked');
            
            // If the click was in a layout table row create the breadcrumb trail
            if ($self.closest('table').prop('id') == 'layouts'){
                $('.cal-event-breadcrumb-trail').show();
                agendaCreateBreadcrumbTrail($self.data("id"), events);
            }
            
            if (!$self.hasClass('selected')){
                agendaSelectLinkedElements($self.closest('table').prop('id'), $self.data("id"), events);
            }
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

/**
 * Select the elements linked to the clicked element
 */
var agendaSelectLinkedElements = function(elemType, elemID, data) {
    
    var targetEvents = [];
    var selectClass = {
            'layouts': 'selected-linked',
            'displaygroups': 'selected-linked',
            'campaigns': 'selected-linked',
    };
    
    results = data.results[data.selectedDisplayGroup];
    
    var allEvents = results.events;
    
    // Get the correspondent events
    for (var i = 0; i < allEvents.length; i++) {
        if (elemType == 'layouts' && allEvents[i].layoutId == elemID) {
            targetEvents.push(allEvents[i]);
            selectClass['layouts'] = 'selected';
        } else if (elemType == 'displaygroups' && allEvents[i].displayGroupId == elemID) {
            targetEvents.push(allEvents[i]);
            selectClass['displaygroups'] = 'selected';
        } else if (elemType == 'campaigns' && allEvents[i].campaignId == elemID) {
            targetEvents.push(allEvents[i]);
            selectClass['campaigns'] = 'selected';
        }
    }
    
    // Use the target events to select the corresponding objects
    for (var i = 0; i < targetEvents.length; i++) {
        // Select the corresponding layout
        $('table#layouts tr[data-id~="' + targetEvents[i].layoutId + '"]').addClass(selectClass['layouts']);
        
        // Select the corresponding display group
        $('table#displaygroups tr[data-id~="' + targetEvents[i].displayGroupId + '"]').addClass(selectClass['displaygroups']);
        
        // Select the corresponding campaigns
        $('table#campaigns tr[data-id~="' + targetEvents[i].campaignId + '"]').addClass(selectClass['campaigns']);
        
    }
    
};

/**
 * Create a breadcrumb trail that shows the origin of a layout
 */
var agendaCreateBreadcrumbTrail = function(layoutId, data) {
    
    var targetEvent = {};
    
    results = data.results[data.selectedDisplayGroup];
    
    var allEvents = results.events;
    
    // Get the correspondent event
    for (var i = 0; i < allEvents.length; i++) {
        if (allEvents[i].layoutId == layoutId) {
            targetEvent = allEvents[i];
        }
    }
    
    $('.cal-event-breadcrumb-trail #content').html('');
    
    var htmlStructure = '';
    
    // Create breadcrumb structure
    // Add layout
    var layoutData = results.layouts[layoutId];
    var arrowElement = '<span>&nbsp;<i class="fa fa-arrow-right" aria-hidden="true"></i>&nbsp;</span>';
    htmlStructure += '<span><a href="' + layoutData.link + '">' + layoutData.layout + '</a></span>'

    // Add campaign
    if (typeof results.campaigns[targetEvent.campaignId] != 'undefined'){
        htmlStructure += arrowElement + '<span><a href="' + results.campaigns[targetEvent.campaignId].link + '">' + results.campaigns[targetEvent.campaignId].campaign + '</a></span>';
    }
    
    // Add schedule
    htmlStructure += arrowElement + '<span><a href="">Schedule</a></span>'
    
    
    // Add intermediate display groups
    for (var i = 0; i < targetEvent.intermediateDisplayGroupIds.length; i++) {
        var displayGroupId = targetEvent.intermediateDisplayGroupIds[i];
        if (typeof results.displayGroups[displayGroupId] != 'undefined'){
            htmlStructure += arrowElement + '<span><a href="' + results.displayGroups[displayGroupId].link + '">' + results.displayGroups[displayGroupId].displayGroup + '</a></span>'
        }
    }
    
    // Add final display group
    var displayGroupId = targetEvent.displayGroupId;
    if (typeof results.displayGroups[displayGroupId] != 'undefined'){
        htmlStructure += arrowElement + '<span><a href="' + results.displayGroups[displayGroupId].link + '">' + results.displayGroups[displayGroupId].displayGroup + '</a></span>'
    }
    
    $('.cal-event-breadcrumb-trail #content').append(htmlStructure);
    $('.cal-event-breadcrumb-trail').show();
};
