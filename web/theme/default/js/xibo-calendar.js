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
    $('#DisplayList').select2();

    // Calendar is initialised without any event_source (that is changed when the selector is used)
    if (($('#Calendar').length > 0)) {
        // Get some options for the calendar
        var calendarOptions = $("#CalendarContainer").data();

        var options = {
            time_start: '00:00',
            time_end: '00:00',
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
                    var displayGroupsList = [];
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
                                
                                displayGroupsList.push({id: $self.val(), name: $self.html(), isDisplaySpecific: $self.attr('type')});
                                
                                if (typeof selectedDisplayGroup == 'undefined')
                                    selectedDisplayGroup = $self.val(); 
                            }
                    });
                    
                    // Sort display group list by name
                    displayGroupsList.sort(function(a, b){
                        var nameA = a.name.toLowerCase(), nameB=b.name.toLowerCase()
                        if (nameA < nameB) //sort string ascending
                            return -1;
                        if (nameA > nameB)
                            return 1;
                            
                        return 0; //default return value (no sorting)
                    })
                        
                    var url = calendarOptions.agendaLink.replace(":id", selectedDisplayGroup);
                                    
                    var dateMoment = moment(this.options.position.start.getTime() / 1000, "X");
                    var timeFromSlider = ( $('#timePickerSlider').length ) ? $('#timePicker').slider('getValue') : 0
                    var timeMoment = moment(timeFromSlider*60, "X");
                    
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
                    
                    // Clean cache/results if its requested by the options
                    if (calendar.options['clearCache'] == true) {
                        events['results'] = {}; 
                    }        
                        
                    // 1 - if there are no displaygroups selected
                    if ($('#DisplayList').val() == null) {
                        
                        events['errorMessage'] = 'display_not_selected';
                        
                        if (done != undefined)
                            done();
                            
                        calendar._render();
                        
                        $('#calendar-progress').removeClass('fa fa-cog fa-spin');
                        
                    } else if(!jQuery.isEmptyObject(events['results'][selectedDisplayGroup]) && events['results'][selectedDisplayGroup]['request_date'] == params.date) {
                        // 2 - Use cache if the element was already saved for the requested date
                        if (done != undefined)
                            done();
                            
                        calendar._render();

                        $('#calendar-progress').removeClass('fa fa-cog fa-spin');
                    } else {
                        // 3 - make request to get the data for the events
                        $.getJSON(url, params)
                            .done(function(data) {
                                
                                if(!jQuery.isEmptyObject(data.data) && data.data.events.length > 0){
                                    events['results'][String(selectedDisplayGroup)] = data.data;
                                    events['results'][String(selectedDisplayGroup)]['request_date'] = params.date;
                                } else {
                                    events['results'][String(selectedDisplayGroup)] = {};
                                    events['errorMessage'] = 'no_events';
                                }
                                
                                if (done != undefined)
                                    done();
                                    
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
                            return moment().startOf("day").minute(value).format(jsTimeFormat);
                        }
                    }).off('slideStop').on('slideStop', function(ev) {
                        calendar.view();
                    });
                } else {
                    $('.cal-event-time-bar').hide();
                }
                
                // Sync the date of the date picker to the current calendar date
                if (this.options.position.start != undefined && this.options.position.start != ""){
                    $("#dateInput .form-control").datetimepicker('update', moment(this.options.position.start.getTime() / 1000, "X").format(systemDateFormat));
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
            var alreadySelected = $self.hasClass('selected');
            
            // Clean all selected elements
            $('.cal-event-breadcrumb-trail').hide();
            $('.cal-context tbody tr').removeClass('selected');
            $('.cal-context tbody tr').removeClass('selected-linked');
            
            // If the element was already selected return so that it can deselect everything 
            if (alreadySelected)
                return;
            
            // If the click was in a layout table row create the breadcrumb trail
            if ($self.closest('table').prop('id') == 'layouts' || $self.closest('table').prop('id') == 'overlays'){
                $('.cal-event-breadcrumb-trail').show();
                //agendaCreateBreadcrumbTrail($self.data("id"), events);
                
                // Clean div content
                $('.cal-event-breadcrumb-trail #content').html('');
                
                // Get the template and render it on the div
                $('.cal-event-breadcrumb-trail #content').append(calendar._breadcrumbTrail($self.data("elemId"), events, $self.data("eventId")));
                
                XiboInitialise("");
            }
            
            // Select the clicked element and the linked elements
            agendaSelectLinkedElements($self.closest('table').prop('id'), $self.data("elemId"), events, $self.data("eventId"));
            
        });
        
        // Create the date input shortcut
        $('#dateInput').datetimepicker({
            format: bootstrapDateFormatDateOnly,
            autoclose: true,
            language: language,
            calendarType: calendarType,
            minView: 2,
            todayHighlight: true
        }).change(function() {
            calendar.navigate("date", moment($("#dateInput .form-control").val(), jsDateFormat));
        }).datetimepicker('update', moment(calendar.options.position.start.getTime() / 1000, "X").format(systemDateFormat));
    }
});

/**
 * Callback for the schedule form
 */
var setupScheduleForm = function(dialog) {

    // Select lists
    var $campaignSelect = $('#campaignId', dialog);
    $campaignSelect.select2({
        dropdownParent: $(dialog),
        ajax: {
            url: $campaignSelect.data("searchUrl"),
            dataType: "json",
            data: function(params) {
                var query = {
                    isLayoutSpecific: -1,
                    retired: 0,
                    totalDuration: 0,
                    name: params.term,
                    start: 0,
                    length: 10,
                    columns: [
                        {
                            "data": "isLayoutSpecific"
                        },
                        {
                            "data": "campaign"
                        }
                    ],
                    order: [
                        {
                            "column": 0,
                            "dir": "asc"
                        },
                        {
                            "column": 1,
                            "dir": "asc"
                        }
                    ]
                };

                // Set the start parameter based on the page number
                if (params.page != null) {
                    query.start = (params.page - 1) * 10;
                }

                return query;
            },
            processResults: function(data, params) {
                var results = [];
                var campaigns = [];
                var layouts = [];

                $.each(data.data, function(index, element) {
                    if (element.isLayoutSpecific === 1) {
                        layouts.push({
                            "id": element.campaignId,
                            "text": element.campaign
                        });
                    } else {
                        campaigns.push({
                            "id": element.campaignId,
                            "text": element.campaign
                        });
                    }
                });

                if (campaigns.length > 0) {
                    results.push({
                        "text": $campaignSelect.data('transCampaigns'),
                        "children": campaigns
                    })
                }

                if (layouts.length > 0) {
                    results.push({
                        "text": $campaignSelect.data('transLayouts'),
                        "children": layouts
                    })
                }

                console.log(results);

                var page = params.page || 1;
                page = (page > 1) ? page - 1 : page;

                return {
                    results: results,
                    pagination: {
                        more: (page * 10 < data.recordsTotal)
                    }
                }
            }
        }
    });

    var $displaySelect = $('select[name="displayGroupIds[]"]', dialog);
    $displaySelect.select2({
        dropdownParent: $(dialog),
        ajax: {
            url: $displaySelect.data("searchUrl"),
            dataType: "json",
            data: function(params) {
                var query = {
                    isDisplaySpecific: -1,
                    forSchedule: 1,
                    displayGroup: params.term,
                    start: 0,
                    length: 10,
                    columns: [
                        {
                            "data": "isDisplaySpecific"
                        },
                        {
                            "data": "displayGroup"
                        }
                    ],
                    order: [
                        {
                            "column": 0,
                            "dir": "asc"
                        },
                        {
                            "column": 1,
                            "dir": "asc"
                        }
                    ]
                };

                // Set the start parameter based on the page number
                if (params.page != null) {
                    query.start = (params.page - 1) * 10;
                }

                return query;
            },
            processResults: function(data, params) {
                var groups = [];
                var displays = [];

                $.each(data.data, function(index, element) {
                    if (element.isDisplaySpecific === 1) {
                        displays.push({
                            "id": element.displayGroupId,
                            "text": element.displayGroup
                        });
                    } else {
                        groups.push({
                            "id": element.displayGroupId,
                            "text": element.displayGroup
                        });
                    }
                });

                var page = params.page || 1;
                page = (page > 1) ? page - 1 : page;

                return {
                    results: [
                        {
                            "text": $displaySelect.data('transGroups'),
                            "children": groups
                        },{
                            "text": $displaySelect.data('transDisplay'),
                            "children": displays
                        }
                    ],
                    pagination: {
                        more: (page * 10 < data.recordsTotal)
                    }
                }
            }
        }
    });

    $('select[name="recurrenceRepeatsOn[]"]', dialog).select2({
        width: "100%"
    });
    
    // Hide/Show form elements according to the selected options
    // Initial state of the components
    processScheduleFormElements($("#recurrenceType"));
    processScheduleFormElements($("#eventTypeId"));
    processScheduleFormElements($("#campaignId"));

    // Events on change
    $("#recurrenceType, #eventTypeId, #dayPartId, #campaignId").on("change", function() { processScheduleFormElements($(this)) });

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
                    calendar.options['clearCache'] = true;
                    calendar.view();
                }
            }
        });
    });

    // Add a button for duplicating this event
    if ($(dialog).find("#scheduleEditForm").length > 0) {
        $button = $("<button>").addClass("btn btn-info").attr("id", "scheduleDuplateButton").html(translations.duplicate).on("click", function() {
            duplicateScheduledEvent()
        });

        $(dialog).find('.modal-footer').prepend($button);
    }
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
            console.log('Process: eventTypeId, val = ' + fieldVal);
            
            var layoutControlDisplay = (fieldVal == 2) ? "none" : "block";
            var endTimeControlDisplay = (fieldVal == 2) ? "none" : "block";
            var startTimeControlDisplay = (fieldVal == 2) ? "block" : "block";
            var dayPartControlDisplay = (fieldVal == 2) ? "none" : "block";
            var commandControlDisplay = (fieldVal == 2) ? "block" : "none";
            var previewControlDisplay = (fieldVal == 2) ? "none" : "block";

            $(".layout-control").css('display', layoutControlDisplay);
            $(".endtime-control").css('display', endTimeControlDisplay);
            $(".starttime-control").css('display', startTimeControlDisplay);
            $(".day-part-control").css('display', dayPartControlDisplay);
            $(".command-control").css('display', commandControlDisplay);
            $(".preview-button-container").css('display', previewControlDisplay);

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

            // If the fieldVal is 2 (command), then we should set the dayPartId to be 0 (custom)
            if (fieldVal == 2) {
                console.log('Setting dayPartId to custom');
                $("#dayPartId").val(0);

                var $startTime = $(".starttime-control");
                $startTime.find("input[name=fromDt_Link2]").show();
                $startTime.find(".help-block").html($startTime.closest("form").data().daypartMessage);
            }
            
            // Call funtion for the daypart ID 
            processScheduleFormElements($('#dayPartId'));
            
            break;
        
        case 'dayPartId':
            console.log('Process: dayPartId, val = ' + fieldVal + ', visibility = ' + el.is(":visible"));

            if (!el.is(":visible"))
                return;

            var meta = el.find('option[value=' + fieldVal + ']').data();

            var endTimeControlDisplay = (meta.isCustom === 0) ? "none" : "block";
            var startTimeControlDisplay = (meta.isAlways === 1) ? "none" : "block";
            var repeatsControlDisplay = (meta.isAlways === 1) ? "none" : "block";
            
            var $startTime = $(".starttime-control");
            var $endTime = $(".endtime-control");
            var $repeats = $("li.repeats");

            // Set control visibility
            $startTime.css('display', startTimeControlDisplay);
            $endTime.css('display', endTimeControlDisplay);
            $repeats.css('display', repeatsControlDisplay);

            // Dayparts only show the start control
            if (meta.isAlways === 0 && meta.isCustom === 0) {
                // We need to update the date/time controls to only accept the date element
                $startTime.find("input[name=fromDt_Link2]").hide();
                $startTime.find(".help-block").html($startTime.closest("form").data().notDaypartMessage);
            } else {
                $startTime.find("input[name=fromDt_Link2]").show();
                $startTime.find(".help-block").html($startTime.closest("form").data().daypartMessage);
            }
                        
            break;

        case 'campaignId':
            // Update the preview button URL
            var $previewButton = $("#previewButton");

            if (fieldVal === null || fieldVal === '' || fieldVal === 0) {
                $previewButton.closest('.preview-button-container').hide();
            } else {
                $previewButton.closest('.preview-button-container').show();
                $previewButton.attr("href", $previewButton.data().url.replace(":id", fieldVal));
            }

            break;
    }
}

var duplicateScheduledEvent = function() {
    // Set the edit form URL to that of the add form
    var $scheduleForm = $("#scheduleEditForm");
    $scheduleForm.attr("action", $scheduleForm.data().addUrl).attr("method", "post");

    // Remove the duplicate button
    $("#scheduleDuplateButton").remove();

    toastr.info($scheduleForm.data().duplicatedMessage);
}

/**
 * Callback for the schedule form
 */
var setupScheduleNowForm = function(form) {
    
    // We submit this form ourselves (outside framework)
    $('#campaignId', form).select2();
    $('select[name="displayGroupIds[]"]', form).select2();

    // Hide the seconds input option unless seconds are enabled in the date format
    if (dateFormat.indexOf("s") <= -1) {
        $(form).find(".schedule-now-seconds-field").hide();
    }

    $(form).find("#always").on("change", function() {
        var always = $(form).find("#always").is(':checked');
        var dayPartId = (always) ? $(form).find("#alwaysDayPartId").val() : $(form).find("#customDayPartId").val();

        $(form).find("#dayPartId").val(dayPartId);

        $(form).find(".duration-part").toggle();
        if (dateFormat.indexOf("s") <= -1) {
            $(form).find(".schedule-now-seconds-field").hide();
        }
    });

    var evaluateDates = $.debounce(500, function() {
      scheduleNowFormEvaluateDates(form);
    });
    
    // Bind to the H:i:s fields
    $(form).find("#hours").on("keyup", evaluateDates);
    $(form).find("#minutes").on("keyup", evaluateDates);
    $(form).find("#seconds").on("keyup", evaluateDates);
};

/**
 * Evaluate dates on schedule form and fill the date input fields
 */
var scheduleNowFormEvaluateDates = function(form) {

    var always = $(form).find("#always").is(':checked');

    if (!always) {
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
    }
};

/**
 * Call evaluate values and then submit schedule now form
 */

var scheduleNowFormSubmit = function(form) {
  
  // Evaluate dates 
  scheduleNowFormEvaluateDates(form);
  
  // Submit the form
  form.submit();
};
  
/**
 * Select the elements linked to the clicked element
 */
var agendaSelectLinkedElements = function(elemType, elemID, data, eventId) {
    
    var targetEvents = [];
    var selectClass = {
            'layouts': 'selected-linked',
            'overlays': 'selected-linked',
            'displaygroups': 'selected-linked',
            'campaigns': 'selected-linked',
    };
    
    results = data.results[data.selectedDisplayGroup];
    
    var allEvents = results.events;
    
    // Get the correspondent events
    for (var i = 0; i < allEvents.length; i++) {
        if ( (elemType == 'layouts' || elemType == 'overlays') && allEvents[i].layoutId == elemID && allEvents[i].eventId == eventId ) {
            targetEvents.push(allEvents[i]);
            selectClass[elemType] = 'selected';
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
        $('table#layouts tr[data-elem-id~="' + targetEvents[i].layoutId + '"][data-event-id~="' + targetEvents[i].eventId + '"]').addClass(selectClass['layouts']);
        
        // Select the corresponding layout
        $('table#overlays tr[data-elem-id~="' + targetEvents[i].layoutId + '"][data-event-id~="' + targetEvents[i].eventId + '"]').addClass(selectClass['overlays']);
        
        // Select the corresponding display group
        $('table#displaygroups tr[data-elem-id~="' + targetEvents[i].displayGroupId + '"]').addClass(selectClass['displaygroups']);
        
        // Select the corresponding campaigns
        $('table#campaigns tr[data-elem-id~="' + targetEvents[i].campaignId + '"]').addClass(selectClass['campaigns']);
        
    }
    
};