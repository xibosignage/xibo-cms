/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
var timelineForm;
var lastForm;
var gridTimeouts = [];
var buttonsTemplate;

// Set up the light boxes
$(document).delegate('*[data-toggle="lightbox"]', 'click', function(event) {
    event.preventDefault();
    $(this).ekkoLightbox();
});

$(document).ready(function() {

    buttonsTemplate = null;

    // Code from: http://stackoverflow.com/questions/7585351/testing-for-console-log-statements-in-ie/7585409#7585409
    // Handles console.log calls when there is no console
    if ( ! window.console ) {

        (function() {
          var names = ["log", "debug", "info", "warn", "error",
              "assert", "dir", "dirxml", "group", "groupEnd", "time",
              "timeEnd", "count", "trace", "profile", "profileEnd"],
              i, l = names.length;

          window.console = {};

          for ( i = 0; i < l; i++ ) {
            window.console[ names[i] ] = function() {};
          }
        }());
    }
    
    setInterval("XiboPing('" + clockUrl + "')", 1000 * 60); // Every minute
    
    setInterval("XiboPing('" + pingUrl + "')", 1000 * 60 * 3); // Every 3 minutes

    XiboInitialise("");
});

/**
 * Initialises the page/form
 * @param {Object} scope (the form or page)
 */
function XiboInitialise(scope) {

    // If the scope isnt defined then assume the entire page
    if (scope == undefined || scope == "") {
        scope = " ";
    }

    // Search for any grids on the page and render them
    $(scope + " .XiboGrid").each(function() {

        var gridName = $(this).data().gridName;
        var form = $(this).find(".XiboFilter form");

        // Check to see if this grid is already in the local storage
        if (gridName != undefined) {
            // Populate the filter according to the values we already have.
            var formValues;
            try {
                formValues = JSON.parse(localStorage.getItem(gridName));

                if (formValues == null)
                    formValues = [];
            } catch (e) {
                formValues = [];
            }

            $.each(formValues, function(key, element) {
                // Does this field exist in the form
                var fieldName = element.name.replace(/\[\]/, '\\\\[\\\\]');
                try {
                    var field = form.find("input[name=" + fieldName + "], select[name=" + fieldName + "]");

                    if (field.length > 0) {
                        field.val(element.value);
                    }
                } catch (e) {
                    console.log("Error populating form saved value with selector input[name=" + element.name + "], select[name=" + element.name + "]");
                }
            });
        }

        var filterRefresh = $.debounce(500, function () {
            if (gridName != undefined)
                localStorage.setItem(gridName, JSON.stringify(form.serializeArray()));

            $(this).closest(".XiboGrid").find("table[role='grid']").DataTable().ajax.reload();
        });
        
        // Bind the filter form
        $(this).find(".XiboFilter form input").on("keyup",  filterRefresh);
        $(this).find(".XiboFilter form input, .XiboFilter form select").on("change", filterRefresh);
    });

    // Search for any Buttons / Links on the page that are used to load forms
    $(scope + " .XiboFormButton").click(function() {

        var formUrl = $(this).attr("href");

        XiboFormRender(formUrl);

        return false;
    });

    // Search for any Buttons that redirect to another page
    $(scope + " .XiboRedirectButton").click(function() {

        window.location = $(this).attr("href");

    });

    // Search for any Buttons / Linkson the page that are used to load hover tooltips
    $(scope + " .XiboHoverButton").hover(
        function(e){

            var formUrl = $(this).attr("href");

            XiboHoverRender(formUrl, e.pageX, e.pageY);

            return false;
        },
        function(){

            // Dont do anything on hover off - the hover on deals with
            // destroying itself.
            return false;
        }
    );

    // Search for any forms that will need submitting
    // NOTE: The validation plugin does not like binding to multiple forms at once.
    $(scope + ' .XiboForm').validate({
        submitHandler: XiboFormSubmit,
        errorElement: "span",
        highlight: function(element) {
            $(element).closest('.form-group').removeClass('has-success').addClass('has-error');
        },
        success: function(element) {
            $(element).closest('.form-group').removeClass('has-error').addClass('has-success');
        },
        invalidHandler: function() {
            // Remove the spinner
            $(this).closest(".modal-dialog").find(".saving").remove();
        }
    });

    // Links that just need to be submitted as forms
    $(scope + ' .XiboAjaxSubmit').click(function(){
        
        $.ajax({
            type: "post",
            url: $(this).attr("href"),
            cache:false,
            dataType:"json",
            success: XiboSubmitResponse
        });

        return false;
    });

    // Forms that we want to be submitted without validation.
    $(scope + ' .XiboAutoForm').submit( function() {
        XiboFormSubmit(this);

        return false;
    });

    // Search for any text forms that will need submitting
    $(scope + ' .XiboTextForm').validate({
        submitHandler: XiboFormSubmit,
        errorElement: "span",
        highlight: function(element) {
            $(element).closest('.form-group').removeClass('has-success').addClass('has-error');
        },
        success: function(element) {
            $(element).closest('.form-group').removeClass('has-error').addClass('has-success');
        }
    });

    // Search for any help enabled elements
    $(scope + " .XiboHelpButton").click(function(){

        var formUrl = $(this).attr("href");

        window.open(formUrl);

        return false;
    });

    // Search for any charts
    $(scope + " div.morrisChart").each(function() {

        // Look for a variable with the same ID as this element
        var data = eval($(this).attr("id"));

        if (data.type == "line")
            new Morris.Line(data.data);
        else if (data.type == "donut")
            new Morris.Donut(data.data);
        else if (data.type == "bar")
            new Morris.Bar(data.data);
    });

    // Special drop down forms (to act as a menu instead of a usual dropdown)
    $(scope + ' .dropdown-menu').on('click', function(e) {
        if($(this).hasClass('dropdown-menu-form')) {
            e.stopPropagation();
        }
    });

    // Date time controls
    $(scope + ' .dateTimePicker').each(function(){

        $(this).datetimepicker({
            format: bootstrapDateFormat,
            minuteStep: 5,
            autoClose: true,
            language: language,
            calendarType: calendarType
        });

        // Get the linked field and use it to set the time
        var preset = $(this).closest("form").find("#" + $(this).data().linkField).val();

        if (preset != undefined && preset != "")
            $(this).datetimepicker('update', preset);
    });

    $(scope + ' .datePicker').each(function() {

        $(this).datetimepicker({
            format: bootstrapDateFormat,
            autoClose: true,
            language: language,
            calendarType: calendarType,
            minView: 2,
            todayHighlight: true
        });

        // Get the linked field and use it to set the time
        var preset = $(this).closest("form").find("#" + $(this).data().linkField).val();

        if (preset != undefined && preset != "")
            $(this).datetimepicker('update', preset);
    });

    $(scope + ' .timePicker').each(function() {

        $(this).datetimepicker({
            format: "hh:ii",
            autoClose: true,
            language: language,
            calendarType: calendarType,
            maxView: 1,
            startView: 1,
            todayHighlight: true,
            minuteStep: 10
        });

        // Get the linked field and use it to set the time
        var preset = $(this).closest("form").find("#" + $(this).data().linkField).val();

        if (preset != undefined && preset != "")
            $(this).datetimepicker('update', preset);
    });

    $(scope + " .selectPicker select.form-control").selectpicker();

    // Notification dates
    $(scope + " span.notification-date").each(function() {
        $(this).html(moment($(this).html(), "X").fromNow());
    });
}

/**
 * DataTable processing event
 * @param e
 * @param settings
 * @param processing
 */
function dataTableProcessing(e, settings, processing) {
    if (processing)
        $(e.target).closest('.widget').children(".widget-title").append(' <span class="saving fa fa-cog fa-spin"></span>');
    else
        $(e.target).closest('.widget').closest(".widget").find(".saving").remove();
}

/**
 * DataTable Draw Event
 * @param e
 * @param settings
 */
function dataTableDraw(e, settings) {

    var target = $("#" + e.target.id);

    // Check to see if we have any buttons that are multi-select
    var enabledButtons = target.find("ul.dropdown-menu li[data-commit-url]");
    if (enabledButtons.length > 0) {
        var searchByKey = function(array, item, key) {
            // return Object from array where array[object].item matches key
            for (var i in array) {
                if (array[i][item] == key) {
                    return true;
                }
            }
            return false;
        };

        // Bind a click event to our table
        target.find("tbody").on("click", "tr", function() {
            $(this).toggleClass("selected");
        });

        // Add a button set to the table
        var template = Handlebars.compile($("#multiselect-button-template").html());
        var buttons = [];

        // Get every enabled button
        $(enabledButtons).each(function () {
            if (!searchByKey(buttons, "id", $(this).data("id")))
                buttons.push({id: $(this).data("id"), gridId: e.target.id, text: $(this).data("text")})
        });

        var output = template({withSelected: translations.withselected, buttons: buttons});
        target.closest(".dataTables_wrapper").find(".dataTables_info").prepend(output);

        // Bind to our output
        target.closest(".dataTables_wrapper").find(".dataTables_info li.XiboMultiSelectFormButton").click(function(){
            XiboMultiSelectFormRender(this);
        });
    }

    // Bind any buttons
    XiboInitialise("#" + e.target.id);
}

/**
 * DataTable Filter for Button Column
 * @param data
 * @param type
 * @param row
 * @param meta
 * @returns {*}
 */
function dataTableButtonsColumn(data, type, row, meta) {
    if (type != "display")
        return "";

    if (buttonsTemplate == null)
        buttonsTemplate = Handlebars.compile($("#buttons-template").html());

    return buttonsTemplate({buttons: data.buttons});
}

function dataTableTickCrossColumn(data, type, row) {
    if (type != "display")
        return data;

    var icon = "";
    if (data == 1)
        icon = "fa-check";
    else if (data == 0)
        icon = "fa-times";
    else
        icon = "fa-exclamation";

    return "<span class='fa " + icon + "'></span>";
}

function dataTableTickCrossInverseColumn(data, type, row) {
    if (type != "display")
        return data;

    var icon = "";
    if (data == 1)
        icon = "fa-times";
    else if (data == 0)
        icon = "fa-check";
    else
        icon = "fa-exclamation";

    return "<span class='fa " + icon + "'></span>";
}

function dataTableDateFromIso(data, type, row) {
    if (type != "display")
        return data;

    if (data == null)
        return "";

    return moment(data).format(jsDateFormat);
}

function dataTableDateFromUnix(data, type, row) {
    if (type != "display")
        return data;

    if (data == null)
        return "";

    return moment(data, "X").format(jsDateFormat);
}

/**
 * DataTable Refresher
 * @param gridId
 * @param table
 * @param refresh
 */
function dataTableConfigureRefresh(gridId, table, refresh) {
    var timeout = (refresh > 10) ? refresh : 10;

    // Cancel existing time outs
    for (var i = gridTimeouts.length - 1; i >= 0; i--) {
        if (gridTimeouts[i].label === gridId) {
            clearTimeout(gridTimeouts[i].timer);
            gridTimeouts.splice(i, 1);
        }
    }

    gridTimeouts.push({
        label: gridId,
        timer: setTimeout(function() {
            table.reload();
        }, (timeout * 1000))
    });
}

function dataTableAddButtons(table, filter) {
    var colVis = new $.fn.dataTable.Buttons(table, {
        buttons: [
            'colvis', 'print', 'csv'
        ]
    });

    table.buttons( 0, null ).container().prependTo(filter);
    $(".ColVis_MasterButton").addClass("btn");
}

/**
 * Renders the formid provided
 * @param {String} formUrl
 */
function XiboFormRender(formUrl, data) {

    // Currently only support one of these at once.
    bootbox.hideAll();

    // Store the last form?
    if (formUrl.indexOf("region/form/timeline") > -1) {
        timelineForm = {
            url: formUrl,
            data: data
        };
    }

    lastForm = formUrl;

    // Call with AJAX
    $.ajax({
        type: "get",
        url: formUrl,
        cache: false,
        dataType: "json",
        data: data,
        success: function(response) {

            // Was the Call successful
            if (response.success) {

                // Set the dialog HTML to be the response HTML
                var dialogTitle = "";

                // Is there a title for the dialog?
                if (response.dialogTitle != undefined && response.dialogTitle != "") {
                    // Set the dialog title
                    dialogTitle =  response.dialogTitle;
                }

                var id = new Date().getTime();

                // Create the dialog with our parameters
                var dialog = bootbox.dialog({
                        message: response.html,
                        title: dialogTitle,
                        animate: false
                    }).attr("id", id);

                // Store the extra
                dialog.data("extra", response.extra);

                // Buttons?
                if (response.buttons != '') {

                    // Append a footer to the dialog
                    var footer = $("<div>").addClass("modal-footer");
                    dialog.find(".modal-content").append(footer);

                    var i = 0;
                    var count = Object.keys(response.buttons).length;
                    $.each(
                        response.buttons,
                        function(index, value) {
                            i++;
                            var extrabutton = $('<button class="btn">').html(index);

                            if (i == count) {
                                extrabutton.addClass('btn-primary save-button');
                            }
                            else {
                                extrabutton.addClass('btn-default');
                            }

                            extrabutton.click(function(e) {
                                e.preventDefault();

                                if ($(this).hasClass("save-button"))
                                    $(this).append(' <span class="saving fa fa-cog fa-spin"></span>');

                                if (value.indexOf("DialogClose") > -1 && (lastForm.indexOf("playlist/widget/form") > -1 || lastForm.indexOf("playlist/form/library/assign") > -1) && timelineForm != null) {
                                    // Close button
                                    // We might want to go back to the prior form
                                    XiboFormRender(timelineForm.url, timelineForm.value);
                                }
                                else
                                    eval(value);

                                return false;
                            });

                            footer.append(extrabutton);
                        });
                }

                // Do we have to call any functions due to this success?
                if (response.callBack != "" && response.callBack != undefined) {
                    eval(response.callBack)(dialog);
                }

                $('input[type=text]', dialog).eq(0).focus();

                // Set up dependencies between controls
                if (response.fieldActions != '') {
                    $.each(response.fieldActions, function(index, fieldAction) {
                        
                        //console.log("Processing field action for " + fieldAction.field);

                        if (fieldAction.trigger == "init") {
                            // Process the actions straight away.
                            var fieldVal = $("#" + fieldAction.field).val();

                            //console.log("Init action with value " + fieldVal);
                            var valueMatch = false;
                            if (fieldAction.operation == "not") {
                                valueMatch = (fieldVal != fieldAction.value);
                            }
                            else if (fieldAction.operation == "is:checked") {
                                valueMatch = (fieldAction.value == $("#" + fieldAction.field).is(':checked'));
                            }
                            else {
                                valueMatch = (fieldVal == fieldAction.value);
                            }

                            if (valueMatch) {
                                //console.log("Value match");

                                $.each(fieldAction.actions, function(index, action) {
                                    //console.log("Setting child field on " + index + " to " + JSON.stringify(action));
                                    // Action the field
                                    var field = $(index);

                                    if (!field.data("initActioned"))
                                        field.css(action).data("initActioned", true);
                                });
                            }
                        }
                        else {
                            $("#" + fieldAction.field).on(fieldAction.trigger, function() {
                                // Process the actions straight away.
                                var fieldVal = $(this).val();

                                //console.log("Init action with value " + fieldVal);
                                var valueMatch = false;
                                if (fieldAction.operation == "not") {
                                    valueMatch = (fieldVal != fieldAction.value);
                                }
                                else if (fieldAction.operation == "is:checked") {
                                    valueMatch = (fieldAction.value == $("#" + fieldAction.field).is(':checked'));
                                }
                                else {
                                    valueMatch = (fieldVal == fieldAction.value);
                                }

                                if (valueMatch) {
                                    //console.log("Value match");

                                    $.each(fieldAction.actions, function(index, action) {
                                        //console.log("Setting child field on " + index + " to " + JSON.stringify(action));
                                        // Action the field
                                        $(index).css(action);
                                    });
                                }
                            });
                        }
                    });
                }

                // Check to see if there are any tab actions
                $('a[data-toggle="tab"]', dialog).on('shown.bs.tab', function (e) {
        
                    if ($(e.target).data().enlarge === 1) {
                        $(e.target).closest(".modal").addClass("modal-big");
                    }
                    else {
                        $(e.target).closest(".modal").removeClass("modal-big");
                    }
                });

                // Check to see if the current tab has the enlarge action
                $('a[data-toggle="tab"]', dialog).each(function() {
                    if ($(this).data().enlarge === 1 && $(this).closest("li").hasClass("active"))
                        $(this).closest(".modal").addClass("modal-big");
                });

                // Call Xibo Init for this form
                XiboInitialise("#"+dialog.attr("id"));
            }
            else {
                // Login Form needed?
                if (response.login) {
                    LoginBox(response.message);

                    return false;
                }
                else {
                    // Just an error we dont know about
                    if (response.message == undefined) {
                        SystemMessage(response);
                    }
                    else {
                        SystemMessage(response.message);
                    }
                }
            }

            return false;
        },
        error: function(response) {
            SystemMessage(response.responseText);
        }
    });

    // Dont then submit the link/button
    return false;
}

function XiboMultiSelectFormRender(button) {

    var buttonId = $(button).data().buttonId;
    var matches = [];

    $("." + buttonId).each(function() {
        if ($(this).closest('tr').hasClass('selected')) {
            // This particular button should be included.
            matches.push($(this));
        }
    });

    var message;

    if (matches.length > 0)
        message = translations.multiselectMessage.replace('%1', "" + matches.length).replace("%2", $(button).find("a").html());
    else
        message = translations.multiselectNoItemsMessage;

    // Open a Dialog containing all the items we have identified.
    var dialog = bootbox.dialog({
            message: message,
            title: translations.multiselect,
            animate: false
        });

    // Append a footer to the dialog
    var dialogContent = dialog.find(".modal-body");
    var footer = $("<div>").addClass("modal-footer");
    dialog.find(".modal-content").append(footer);

    // Add some buttons
    var extrabutton;

    if (matches.length > 0) {
        extrabutton = $('<button class="btn">').html(translations.save).addClass('btn-primary save-button');
        extrabutton.click(function() {

            $(this).append(' <span class="saving fa fa-cog fa-spin"></span>');

            // Create a new queue.
            window.queue = $.jqmq({

                // Next item will be processed only when queue.next() is called in callback.
                delay: -1,

                // Process queue items one-at-a-time.
                batch: 1,

                // For each queue item, execute this function, making an AJAX request. Only
                // continue processing the queue once the AJAX request's callback executes.
                callback: function( item ) {
                    var data = $(item).data();

                    // Make an AJAX call
                    $.ajax({
                        type: data.commitMethod,
                        url: data.commitUrl,
                        cache: false,
                        dataType: "json",
                        data: data,
                        success: function(response, textStatus, error) {

                            if (response.success) {

                                dialogContent.append($("<div>").html(data.rowtitle + ": " + translations.success));

                                // Process the next item
                                queue.next();
                            }
                            else {
                                // Why did we fail?
                                if (response.login) {
                                    // We were logged out
                                    LoginBox(response.message);
                                }
                                else {
                                    dialogContent.append($("<div>").html(data.rowtitle + ": " + translations.failure));

                                    // Likely just an error that we want to report on
                                    footer.find(".saving").remove();
                                    SystemMessageInline(response.message, footer.closest(".modal"));
                                }
                            }
                        },
                        error: function(responseText) {
                            SystemMessage(responseText, false);
                        }
                    });
                },
                // When the queue completes naturally, execute this function.
                complete: function() {
                    // Remove the save button
                    footer.find(".saving").parent().remove();

                    // Refresh the grids
                    // (this is a global refresh)
                    XiboRefreshAllGrids();
                }
            });

            // Add our selected items to the queue
            $(matches).each(function() {
                queue.add(this);
            });

            queue.start();

            // Keep the modal window open!
            return false;
        });

        footer.append(extrabutton);
    }

    // Close button
    extrabutton = $('<button class="btn">').html(translations.close).addClass('btn-default');
    extrabutton.click(function() {

        $(this).append(' <span class="saving fa fa-cog fa-spin"></span>');

        // Do our thing
        dialog.modal('hide');

        // Keep the modal window open!
        return false;
    });

    footer.append(extrabutton);

}

function XiboHelpRender(url) {
    window.open(url);
}

/**
 * Xibo Ping
 * @param {String} url
 * @param {String} updateDiv
 */
function XiboPing(url, updateDiv) {

    // Call with AJAX
    $.ajax({
        type: "get",
        url: url,
        cache: false,
        dataType: "json",
        success: function(response){

            // Was the Call successful
            if (response.success) {

                if (updateDiv != undefined) {
                    $(updateDiv).html(response.html);
                }

                if (response.clockUpdate) {
                    XiboClockUpdate(response.html);
                }
            }
            else {
                // Login Form needed?
                if (response.login) {
                    
                    LoginBox(response.message);
                    
                    return false;
                }
            }

            return false;
        }
    });
}

/**
 * Updates the Clock with the latest time
 * @param {Object} time
 */
function XiboClockUpdate(time)
{
    $('#XiboClock').html(time);

    return;
}

/**
 * Submits the Form
 * @param {Object} form
 * @param e
 * @param callBack
 */
function XiboFormSubmit(form, e, callBack) {

    // Get the URL from the action part of the form)
    var url = $(form).attr("action");

    // Pull any text editor instances we have
    for (var editor in CKEDITOR.instances) {

        //console.log("Name: " + editor);
        //console.log("Content: " + CKEDITOR.instances[editor].getData());

        // Parse the data for library preview references, and replace those with their original values
        // /\/library\/download\/(.[0-9]+)\?preview=1/;
        var regex = new RegExp(CKEDITOR_DEFAULT_CONFIG.imageDownloadUrl.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&").replace(":id", "([0-9]+)"), "g");

        var data = CKEDITOR.instances[editor].getData().replace(regex, function (match, group1) {
            var replacement = "[" + group1 + "]";
            //console.log("match = " + match + ". replacement = " + replacement);
            return replacement;
        });

        // Set the appropriate text editor field with this data.
        $("#" + editor).val(data);
    }

    $.ajax({
        type:$(form).attr("method"),
        url:url,
        cache:false,
        dataType:"json",
        data:$(form).serialize(),
        success: function(xhr, textStatus, error) {
            
            XiboSubmitResponse(xhr, form);

            if (callBack != null && callBack != undefined)
                callBack(xhr, form);
        },
        error: function(xhr, textStatus, errorThrown) {
            SystemMessage(xhr.responseText, false);
        }
    });

    return false;
}

/**
 * Handles the submit response from an AJAX call
 * @param {Object} response
 * @param
 */
function XiboSubmitResponse(response, form) {
    
    // Remove the spinner
    $(form).closest(".modal-dialog").find(".saving").remove();

    // Check the apply flag
    var apply = $(form).data("apply");

    // Remove the apply flag
    $(form).data("apply", false);

    // Did we actually succeed
    if (response.success) {
        // Success - what do we do now?
        if (response.message != '')
            SystemMessage(response.message, true);

        // We might need to keep the form open
        if (apply == undefined || !apply) {
            bootbox.hideAll();
        }
        else {
            // Focus in the first input
            $('input[type=text]', form).eq(0).focus();
        }

        // Should we refresh the window or refresh the Grids?
        XiboRefreshAllGrids();

        if (!apply) {
            // Next form URL is provided
            if ($(form).data("nextFormUrl") != undefined) {
                XiboFormRender($(form).data().nextFormUrl.replace(":id", response.id));
            }
            // Back to the timeline form
            else if ((lastForm != undefined && (lastForm.indexOf("playlist/widget/form") > -1 || lastForm.indexOf("playlist/form/library/assign") > -1)) && timelineForm != null) {
                // Close button
                // We might want to go back to the prior form
                XiboFormRender(timelineForm.url, timelineForm.value);
            }
        }
    }
    else {
        // Why did we fail?
        if (response.login) {
            // We were logged out
            LoginBox(response.message);
        }
        else {
            // Likely just an error that we want to report on
            SystemMessageInline(response.message, $(form).closest(".modal"));
        }
    }

    return false;
}

/**
 * Renders a Hover window and sets up events to destroy the window.
 */
function XiboHoverRender(url, x, y)
{
    // Call some AJAX
    // TODO: Change this to be hover code
    $.ajax({
        type: "get",
        url: url,
        cache: false,
        dataType: "json",
        success: function(response){

            // Was the Call successful
            if (response.success) {

                var dialogWidth = "500";
                var dialogHeight = "500";

                // Do we need to alter the dialog size?
                if (response.dialogSize) {
                    dialogWidth     = response.dialogWidth;
                    dialogHeight    = response.dialogHeight;
                }

                // Create the the popup bubble with our parameters
                $("body").append("<div class=\"XiboHover\"></div>");

                $(".XiboHover").css("position", "absolute").css(
                {
                    display: "none",
                    width:dialogWidth,
                    height:dialogHeight,
                    top: y,
                    left: x
                }
                ).fadeIn("slow").hover(
                    function(){
                        return false
                    },
                    function(){
                        $(".XiboHover").hide().remove();
                        return false;
                    }
                    );

                // Set the dialog HTML to be the response HTML
                $('.XiboHover').html(response.html);

                // Do we have to call any functions due to this success?
                if (response.callBack != "" && response.callBack != undefined) {
                    eval(response.callBack)(name);
                }

                // Call Xibo Init for this form
                XiboInitialise(".XiboHover");

            }
            else {
                // Login Form needed?
                if (response.login) {
                    LoginBox(response.message);
                    return false;
                }
                else {
                    // Just an error we dont know about
                    if (response.message == undefined) {
                        SystemMessage(response);
                    }
                    else {
                        SystemMessage(response.message);
                    }
                }
            }

            return false;
        }
    });

    // Dont then submit the link/button
    return false;
}

/**
 * Closes the dialog window
 */
function XiboDialogClose() {
    bootbox.hideAll();
}

/**
 * Apply a form instead of saving and closing
 * @constructor
 */
function XiboDialogApply(formId) {
    var form = $(formId);

    form.data("apply", true);

    form.submit();
}

function XiboSwapDialog(formUrl) {
    bootbox.hideAll();
    XiboFormRender(formUrl);
}

function XiboRefreshAllGrids() {
    // We should refresh the grids (this is a global refresh)
    $(" .XiboGrid table").each(function() {
        // Render
        $(this).DataTable().ajax.reload(null, false);
    });
}

function XiboRedirect(url) {
    window.location.href = url;
}

/**
 * Display a login box
 * @param {String} message
 */
function LoginBox(message) {

    // Reload the page (appending the message)
    window.location.href = window.location.href;
    location.reload(false);
}

function updateUserPref(prefs) {

    // Call with AJAX
    $.ajax({
        type: "post",
        url: userPreferencesUrl,
        cache: false,
        dataType: "json",
        data: {preference: prefs},
        success: function(response){

            // Was the Call successful
            if (response.success) {
                SystemMessage(response.message, true);
            }
            else {
                // Login Form needed?
                if (response.login) {

                    LoginBox(response.message);

                    return false;
                } else {
                    SystemMessage(response.message, response.success);
                }
            }

            return false;
        }
    });
}

/**
 * Displays the system message
 * @param {String} messageText
 * @param {boolean} success
 */
function SystemMessage(messageText, success) {

    if (messageText == '' || messageText == null) 
        return;

    if (success) {
        toastr.success(messageText);
    }
    else {
        var dialog = bootbox.dialog({
            message: messageText,
            title: "Application Message",
            buttons: [{
                label: 'Close',
                callback: function() {
                    if (lastForm != null && lastForm.indexOf("playlist/widget/form") > -1 && timelineForm != null) {
                        // Close button
                        // We might want to go back to the prior form
                        XiboFormRender(timelineForm.url, timelineForm.value);
                    }
                    else
                        dialog.modal('hide');
                }
            }],
            animate: false
        });
    }
}

/**
 * Displays the system message
 * @param {String} messageText
 * @param {Bool} success
 */
function SystemMessageInline(messageText, modal) {

    if (messageText == '' || messageText == null) 
        return;

    // TODO: if modal is null (or not a form), then pick the nearest .text error instead.
    if (modal == undefined || modal == null || modal.length == 0)
        modal = $(".modal");

    // Remove existing errors
    $(".form-error", modal).remove();

    $("<div/>", {
        class: "well text-danger text-center form-error",
        html: messageText
    }).appendTo(modal.find(".modal-footer"));
}

/**
 * Toggles the FilterForm view
 */
function ToggleFilterView(div) {
    if ($(div).css("display") == "none") {
        $(div).fadeIn("slow");
    }
    else {
        $(div).fadeOut("slow");
    }
}