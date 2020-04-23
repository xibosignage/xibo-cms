/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2018 Spring Signage Ltd
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

// Fix startsWith string prototype for IE
if (!String.prototype.startsWith) {
    String.prototype.startsWith = function(searchString, position) {
        position = position || 0;
        return this.indexOf(searchString, position) === position;
    };
}

// Fix endsWith string prototype for IE
if (!String.prototype.endsWith) {
    String.prototype.endsWith = function(suffix) {
        return this.indexOf(suffix, this.length - suffix.length) !== -1;
    };
}

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

        var filterRefresh = _.debounce(function () {
            if (gridName != undefined)
                localStorage.setItem(gridName, JSON.stringify(form.serializeArray()));

            $(this).closest(".XiboGrid").find("table[role='grid']").DataTable().ajax.reload();
        }, 500);
        
        // Prevent enter key to submit form
        $(this).find(".XiboFilter form").on('keydown', function(event) {
            if(event.keyCode == 13) {
                event.preventDefault();
                return false;
            }
        });
        
        // Bind the filter form
        $(this).find(".XiboFilter form input").on("keyup",  filterRefresh);
        $(this).find(".XiboFilter form input, .XiboFilter form select").on("change", filterRefresh);
    });

    // Search for any Buttons / Links on the page that are used to load forms
    $(scope + " .XiboFormButton").click(function() {

        var eventStart = $(this).data("eventStart");
        var eventEnd = $(this).data("eventEnd");
        if (eventStart !== undefined && eventEnd !== undefined ) {
            var data = {
                eventStart: eventStart,
                eventEnd: eventEnd,
            };
            XiboFormRender($(this), data);

        } else {
            XiboFormRender($(this));
        }

        return false;
    });

    // Search for any Buttons / Links on the page that are used to load custom forms
    $(scope + " .XiboCustomFormButton").click(function() {

        XiboCustomFormRender($(this));

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
        invalidHandler: function(event, validator) {
            // Remove the spinner
            $(this).closest(".modal-dialog").find(".saving").remove();
            // https://github.com/xibosignage/xibo/issues/1589
            $(this).closest(".modal-dialog").find(".save-button").removeClass("disabled");
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
    // TODO: remove in 1.9
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
    $(scope + ' .datePicker').each(function() {
        if(calendarType == 'Jalali') {
            const linkedFormat = $(this).data().linkFormat;
            initDatePicker($(this), dateOnlyFormat, 
                {
                    altFieldFormatter: function(unixTime) {
                        let newDate = moment.unix(unixTime / 1000);
                        newDate.set('hour', 0);
                        newDate.set('minute', 0);
                        newDate.set('second', 0);

                        return newDate.format(linkedFormat);
                    }
                }
            );
        } else {
            initDatePicker($(this), dateOnlyFormat);
        }
    });

    $(scope + ' .dateTimePicker').each(function() {
        if(calendarType == 'Jalali') {
            initDatePicker($(this), dateFormat, {
                timePicker: {
                    enabled: true
                }
            });
        } else {
            initDatePicker($(this), dateFormat, {
                enableTime: true
            });
        }
    });

    $(scope + ' .dateMonthPicker').each(function() {
        if(calendarType == 'Jalali') {
            const linkedFormat = $(this).data().linkFormat;
            initDatePicker($(this), dateOnlyFormat, {
                format: "YYYY MMMM",
                viewMode: 'month',
                dayPicker: {
                    enabled: false
                },
                altFieldFormatter: function(unixTime) {
                    let newDate = moment.unix(unixTime / 1000);
                    newDate.set('date', 1);
                    newDate.set('hour', 0);
                    newDate.set('minute', 0);
                    newDate.set('second', 0);

                    return newDate.format(linkedFormat);
                }
            });
        } else {
            initDatePicker($(this), dateOnlyFormat, {
                altFormat: "F, Y",
                plugins: [new flatpickrMonthSelectPlugin({shorthand: false})]
            });
        }
    });

    $(scope + ' .timePicker').each(function() {
        if(calendarType == 'Jalali') {
            initDatePicker($(this), timeFormat, {
                onlyTimePicker: true,
                format: jsTimeFormat,
                timePicker: {
                    second: {
                        enabled: false
                    }
                }
            });
        } else {
            initDatePicker($(this), timeFormat, 
                {
                    enableTime: true,
                    noCalendar: true,
                    time_24hr: true
                },
                {
                    timeOnly: true
                }
            );
        }
    });

    $(scope + " .selectPicker select.form-control").select2({
        dropdownParent: ($(scope).hasClass("modal") ? $(scope) : $("body")),
        templateResult: function(state) {

            if (!state.id) {
                return state.text;
            }

            var $el = $(state.element);

            if ($el.data().content !== undefined) {
                return $($el.data().content);
            }

            return state.text;
        }
    });

    // make a vanilla layout, display and media selector for reuse
    $(scope + " .pagedSelect select.form-control").each(function() {
        let $this = $(this);
        let anchor = $this.data("anchorElement");
        if (anchor !== undefined && anchor !== "") {
            makePagedSelect($(this), $(anchor));
        } else {
            makePagedSelect($(this), $("body"));
        }
    });

    // make a local select that search for text or tags
    $(scope + " .localSelect select.form-control").each(function() {
        makeLocalSelect($(this), ($(scope).hasClass("modal") ? $(scope) : $("body")));
    });

    // Notification dates
    $(scope + " span.notification-date").each(function() {
        $(this).html(moment($(this).html(), "X").fromNow());
    });

    // Switch form elements
    $(scope + " input.bootstrap-switch-target").each(function() {
        $(this).bootstrapSwitch();
    });
    
    // Initialize tags input form
    $(scope + " input[data-role=tagsInputInline], " + scope + " input[data-role=tagsInputForm], " + scope + " select[multiple][data-role=tagsInputForm]").tagsinput();

    // Initialize tag with values function from xibo-forms.js
    $(scope + " .tags-with-value").each(function() {
        tagsWithValues($(this).closest("form").attr('id'));
    });

    // If it's a modal, clear some created field on close
    if($(scope).hasClass('modal')) {
        $(scope).on("hide.bs.modal", function(e) {
            if(e.namespace === 'bs.modal') {
                // Remove datetimepickers
                destroyDatePickers($(scope).find('.dateTimePicker, .datePicker, .dateMonthPicker, .timePicker'));
            }
        });
    }
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
        if (target.data().initialised == undefined) {
            target.find("tbody").on("click", "tr", function () {
                $(this).toggleClass("selected");
                target.data().initialised = true;
            });
        }

        // Add a button set to the table
        var template = Handlebars.compile($("#multiselect-button-template").html());
        var buttons = [];

        // Get every enabled button
        $(enabledButtons).each(function () {
            if (!searchByKey(buttons, "id", $(this).data("id")))
                buttons.push({id: $(this).data("id"), gridId: e.target.id, text: $(this).data("text")})
        });

        var output = template({selectAll: translations.selectAll, withSelected: translations.withselected, buttons: buttons});
        target.closest(".dataTables_wrapper").find(".dataTables_info").prepend(output);

        // Bind to our output
        target.closest(".dataTables_wrapper").find(".dataTables_info li.XiboMultiSelectFormButton").click(function(){
            XiboMultiSelectFormRender(this);
        });
        
        // Bind click to select all button
        target.closest(".dataTables_wrapper").find(".dataTables_info button.select-all").click(function(){
            var allRows = target.find("tbody tr");
            var numberSelectedRows = target.find("tbody tr.selected").length;
            
            // If there are more rows selected than unselected, unselect all, otherwise, selected them all
            if (numberSelectedRows > allRows.length/2){
              allRows.removeClass('selected');
            } else {
              allRows.addClass('selected');
            }
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
    if (type !== "display" && type !== "export")
        return data;

    if (data == null)
        return "";

    return moment(data, systemDateFormat).format(jsDateFormat);
}

function dataTableDateFromUnix(data, type, row) {
    if (type !== "display" && type !== "export")
        return data;

    if (data == null || data == 0)
        return "";

    return moment(data, "X").tz ? moment(data, "X").tz(timezone).format(jsDateFormat) : moment(data, "X").format(jsDateFormat);
}

function dataTableSpacingPreformatted(data, type, row) {
    if (type !== "display")
        return data;

    if (data === null || data === "")
        return "";

    return "<span class=\"spacing-whitespace-pre\">" + data + "</span>";
}

/**
 * DataTable Create tags
 * @param data
 * @returns {*}
 */
function dataTableCreateTags(data, type) {

    if (type !== "display")
        return data.tags;

    var returnData = '';

    if(typeof data.tags != undefined && data.tags != null ) {
        let arrayOfValues = [];
        var arrayOfTags = data.tags.split(',');

        if(typeof data.tagValues != undefined && data.tagValues != null) {
            arrayOfValues = data.tagValues.split(',');
        }

        returnData += '<div id="tagDiv">';

        for (let i = 0; i < arrayOfTags.length; i++) {
            if(arrayOfTags[i] != '' && (arrayOfValues[i] == undefined || arrayOfValues[i] === 'NULL')) {
                returnData += '<li class="btn btn-sm btn-default btn-tag">' + arrayOfTags[i] + '</span></li>'
            } else if (arrayOfTags[i] != '' && (arrayOfValues[i] != '' || arrayOfValues[i] !== 'NULL')) {
                returnData += '<li class="btn btn-sm btn-default btn-tag">' + arrayOfTags[i] + '|' + arrayOfValues[i] + '</span></li>'
            }
        }

        returnData += '</div>';
    }

    return returnData;
}

/**
 * DataTable Create permissions
 * @param data
 * @returns {*}
 */
function dataTableCreatePermissions(data, type) {

    if (type !== "display")
        return data;

    var returnData = '';

    if(typeof data != undefined && data != null ) {
        var arrayOfTags = data.split(',');

        returnData += '<div class="permissionsDiv">';

        for (var i = 0; i < arrayOfTags.length; i++) {
            if(arrayOfTags[i] != '')
                returnData += '<li class="badge">' + arrayOfTags[i] + '</span></li>'
        }

        returnData += '</div>';
    }

    return returnData;
}

/**
 * DataTable Create tags
 * @param e
 * @param settings
 */
function dataTableCreateTagEvents(e, settings) {
    
    var table = $("#" + e.target.id);
    var tableId = e.target.id;
    var form = e.data.form;
    // Unbind all 
    table.off('click');
    
    table.on("click", ".btn-tag", function(e) {
        // See if its the first element, if not add comma
        var tagText = $(this).text();

        // Get the form tag input text field
        var inputText = form.find("#tags").val();

        if (tableId == 'playlistLibraryMedia') {
            inputText = form.find("#filterMediaTag").val();
            form.find("#filterMediaTag").tagsinput('add', tagText, { allowDuplicates: false });
        } else if (tableId == 'displayGroupDisplays') {
            inputText = form.find("#dynamicCriteriaTags").val();
            form.find("#dynamicCriteriaTags").tagsinput('add', tagText, { allowDuplicates: false });
        } else {
            // Add text to form
            form.find("#tags").tagsinput('add', tagText, {allowDuplicates: false});
        }
        // Refresh table to apply the new tag search
        table.DataTable().ajax.reload();
    });
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

function dataTableAddButtons(table, filter, allButtons) {

    allButtons = (allButtons === undefined) ? true : allButtons;

    if (allButtons) {
        var colVis = new $.fn.dataTable.Buttons(table, {
            buttons: [
                {
                    extend: 'colvis',
                    text: function (dt, button, config) {
                        return dt.i18n('buttons.colvis');
                    }
                },
                {
                    extend: 'print',
                    text: function (dt, button, config) {
                        return dt.i18n('buttons.print');
                    },
                    exportOptions: {
                        orthogonal: 'export',
                        format: {
                            body: function (data, row, column, node) {
                                if (data === null || data === "" || data === "null")
                                    return "";
                                else
                                    return data;
                            }
                        }
                    }
                },
                {
                    extend: 'csv',
                    exportOptions: {
                        orthogonal: 'export',
                        format: {
                            body: function (data, row, column, node) {
                                if (data === null || data === "")
                                    return "";
                                else
                                    return data;
                            }
                        }
                    }
                }
            ]
        });
    } else {
        var colVis = new $.fn.dataTable.Buttons(table, {
            buttons: [
                {
                    extend: 'colvis',
                    text: function (dt, button, config) {
                        return dt.i18n('buttons.colvis');
                    }
                }
            ]
        });
    }

    table.buttons( 0, null ).container().prependTo(filter);
    $(".ColVis_MasterButton").addClass("btn");
}

/**
 * Renders the formid provided
 * @param {Object} sourceObj
 * @param {Object} data
 */
function XiboFormRender(sourceObj, data) {
    
    var formUrl = "";
    if (typeof sourceObj === "string" || sourceObj instanceof String) {
        formUrl = sourceObj;
    } else {
        formUrl = sourceObj.attr("href");
        // Remove the link from the source object if exists 
        sourceObj.removeAttr('href');
    }  
    
    // To fix the error generated by the double click on button
    if( formUrl == undefined ){
        return false;
    }

    // Currently only support one of these at once.
    bootbox.hideAll();

    // Store the last form?
    if (formUrl.indexOf("region/form/timeline") > -1 || formUrl.indexOf("playlist/form/timeline") > -1) {
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

            // Restore the link to the source object if exists
            if (typeof sourceObj === "object" || sourceObj instanceof Object)
                sourceObj.attr("href", lastForm);
                
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
                if (response.buttons !== '') {

                    // Append a footer to the dialog
                    var footer = $("<div>").addClass("modal-footer");
                    dialog.find(".modal-content").append(footer);

                    var i = 0;
                    var count = Object.keys(response.buttons).length;
                    $.each(
                        response.buttons,
                        function(index, value) {
                            i++;
                            var extrabutton = $('<button id="dialog_btn_' + i + '" class="btn">').html(index);

                            if (i === count) {
                                extrabutton.addClass('btn-primary save-button');
                            }
                            else {
                                extrabutton.addClass('btn-default');
                            }

                            extrabutton.click(function(e) {
                                e.preventDefault();

                                let $button = $(this);

                                if ($button.hasClass("save-button")) {
                                    if ($button.hasClass("disabled")) {
                                        return false;
                                    } else {
                                        $button.append(' <span class="saving fa fa-cog fa-spin"></span>');

                                        // Disable the button
                                        // https://github.com/xibosignage/xibo/issues/1467
                                        $button.addClass("disabled");
                                    }
                                }

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

                // Focus in the first input
                $('input[type=text]', dialog).not(".dateControl").eq(0).focus();

                $('input[type=text]', dialog).each(function(index, el) {
                    formRenderDetectSpacingIssues(el);

                    $(el).on("keyup", _.debounce(function() {
                        formRenderDetectSpacingIssues(el);
                    }, 500));
                });

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
                
                // Do we have to call any functions due to this success?
                if (response.callBack !== "" && response.callBack !== undefined) {
                    eval(response.callBack)(dialog);
                }
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

/**
 * Renders the form provided using the form own javascript
 * @param {Object} sourceObj
 */
function XiboCustomFormRender(sourceObj) {
    
    var formUrl = "";
    
    formUrl = sourceObj.attr("href");

    // Remove the link from the source object if exists 
    sourceObj.removeAttr('href');
   
    // To fix the error generated by the double click on button
    if(formUrl == undefined) {
        return false;
    }

    lastForm = formUrl;

    // Call with AJAX
    $.ajax({
        type: "get",
        url: formUrl,
        cache: false,
        dataType: "json",
        success: function(response) {

            // Restore the link to the source object if exists
            if(typeof sourceObj === "object" || sourceObj instanceof Object)
                sourceObj.attr("href", lastForm);

            // Was the Call successful
            if(response.success) {

                // Create new id using the current time
                var id = new Date().getTime();

                var formToRender = {
                    id: id,
                    buttons: response.buttons,
                    data: response.data,
                    title: response.dialogTitle,
                    message: response.html,
                    extra: response.extra
                };

                // Do we have to call any functions due to this success?
                if(response.callBack !== "" && response.callBack !== undefined) {
                    window[response.callBack](formToRender);
                }
            }
            else {
                // Login Form needed?
                if(response.login) {
                    LoginBox(response.message);

                    return false;
                }
                else {
                    // Just an error we dont know about
                    if(response.message == undefined) {
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

/**
 * Makes a remote call to XIBO and passes the result in the given onSuccess method
 * In case of an Error it shows an ErrorMessageBox
 * @param {String} fromUrl
 * @param {Object} data
 * @param {Function} onSuccess
 */
function XiboRemoteRequest(formUrl, data, onSuccess) {
    $.ajax({
        type: "post",
        url: formUrl,
        cache: false,
        dataType: "json",
        data: data,
        success: onSuccess,
        error: function(response) {
            SystemMessage(response.responseText);
        }
    });
}

function formRenderDetectSpacingIssues(element) {
    var $el = $(element);
    var value = $el.val();

    if (value !== '' && (value.startsWith(" ") || value.endsWith(" ") || value.indexOf("  ") > -1)) {
        // Add a little icon to the fields parent to inform of this issue
        console.log("Field with strange spacing: " + $el.attr("name"));

        var warning = $("<span></span>").addClass("fa fa-exclamation-circle spacing-warning-icon").attr("title", translations.spacesWarning);

        $el.parent().append(warning);
    } else {
        $el.parent().find('.spacing-warning-icon').remove();
    }
}

function XiboMultiSelectFormRender(button) {
    // The button ID
    var buttonId = $(button).data().buttonId;

    // Get a list of buttons that match the ID
    var matches = [];
    var formOpenCallback = null;

    $("." + buttonId).each(function() {
        if ($(this).closest('tr').hasClass('selected')) {
            // This particular button should be included.
            matches.push($(this));

            if (matches.length === 1) {
                // this is the first button which matches, so use the form open hook if one has been provided.
                formOpenCallback = $(this).data().formCallback;

                // If form needs confirmation
                formConfirm = $(this).data().formConfirm;
            }
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

    // Call our open function if we have one
    if (formOpenCallback !== undefined && formOpenCallback !== null) {
        eval(formOpenCallback)(dialog);
    }

    // Add some buttons
    var extrabutton;

    if (matches.length > 0) {
        extrabutton = $('<button class="btn">').html(translations.save).addClass('btn-primary save-button');

        // If form needs confirmation, disable save button
        if(formConfirm) {
            extrabutton.prop('disabled', true);
        }

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

                    if (dialog.data().commitData !== undefined)
                        data = $.extend({}, data, dialog.data().commitData);

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

        // Bring other modals back to focus
        if ($('.modal').hasClass('in')) {
            $('body').addClass('modal-open');
        }

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
            // If we have reset on apply
            if($(form).data("applyCallback")) {
                eval($(form).data("applyCallback"))(form);
            }

            // Remove form errors
            $(form).closest(".modal-dialog").find(".form-error").remove();

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

function XiboSwapDialog(formUrl, data) {
    bootbox.hideAll();
    XiboFormRender(formUrl, data);
}

function XiboRefreshAllGrids() {
    // We should refresh the grids (this is a global refresh)
    $(" .XiboGrid table.dataTable").each(function() {
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

    // if modal is null (or not a form), then pick the nearest .text error instead.
    if (modal == undefined || modal == null || modal.length == 0)
        modal = $(".modal");

    // popup if no form
    if (modal.length <= 0) {
        toastr.error(messageText);
        return;
    }

    // Remove existing errors
    $(".form-error", modal).remove();

    // Re-enabled any disabled buttons
    $(modal).find(".btn").removeClass("disabled");

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

/**
 * Make a Paged Layout Selector from a Select Element and its parent (which can be null)
 * @param element
 * @param parent
 */
function makePagedSelect(element, parent) {
    element.select2({
        dropdownParent: ((parent == null) ? $("body") : $(parent)),
        ajax: {
            url: element.data("searchUrl"),
            dataType: "json",
            data: function(params) {
                var query = {
                    start: 0,
                    length: 10
                };

                // Term to use for search
                var searchTerm = params.term;

                // If we search by tags
                if(searchTerm != undefined && element.data("searchTermTags") != undefined) {
                    // Get string 
                    var tags = searchTerm.match(/\[([^}]+)\]/);
                    var searchTags = '';

                    // If we have match for tag search
                    if(tags != null) {
                        // Add tags to search
                        searchTags = tags[1];

                        // Remove tags in the query text
                        searchTerm = searchTerm.replace(tags[0], '');

                        // Add search by tags to the query
                        query[element.data("searchTermTags")] = searchTags;
                    }
                }

                // Search by searchTerm
                query[element.data("searchTerm")] = searchTerm;

                // Check to see if we've been given additional filter options
                if (element.data("filterOptions") !== undefined) {
                    query = $.extend({}, query, element.data("filterOptions"));
                }

                // Set the start parameter based on the page number
                if (params.page != null) {
                    query.start = (params.page - 1) * 10;
                }

                return query;
            },
            processResults: function(data, params) {
                var results = [];
                var $element = element;

                $.each(data.data, function(index, el) {
                    results.push({
                        "id": el[$element.data("idProperty")],
                        "text": el[$element.data("textProperty")]
                    });
                });

                var page = params.page || 1;
                page = (page > 1) ? page - 1 : page;

                return {
                    results: results,
                    pagination: {
                        more: (page * 10 < data.recordsTotal)
                    }
                };
            }
        }
    });

    // Set initial value if exists
    if(element.data("initialValue") != undefined) {
        $.ajax({
            url: element.data("searchUrl"),
            type: 'GET',
            data: {
                mediaId: element.data("initialValue")
            }
        }).then(function(data) {
            // create the option and append to Select2
            var option = new Option(data.data[0][element.data("textProperty")], data.data[0][element.data("idProperty")], true, true);
            element.append(option).trigger('change');

            // manually trigger the `select2:select` event
            element.trigger({
                type: 'select2:select',
                params: {
                    data: data
                }
            });
        });
    }
}

/**
 * Make a dropwdown with a search field for option's text and tag datafield (data-tags)
 * @param element
 * @param parent
 */
function makeLocalSelect(element, parent) {
    element.select2({
        dropdownParent: ((parent == null) ? $("body") : $(parent)),
        matcher: function(params, data) {
            // If filterClass is defined, try to filter the elements by it
            const mainFilterClass = $(data.element.parentElement).data().filterClass;

            // Get element class array ( one or more elements split by comma)
            const elementClassArray = ($(data.element).data().filterClass != undefined ) ? $(data.element).data().filterClass.replace(' ', '').split(',') : [];

            // If filter exists and it's not in one of the element filters, return empty data
            if(mainFilterClass != undefined && mainFilterClass != '' && !elementClassArray.includes(mainFilterClass)) {
                return null;
            }

            // If there are no search terms, return all of the data
            if($.trim(params.term) === '') {
                return data;
            }

            // Tags
            var tags = params.term.match(/\[([^}]+)\]/);
            var queryText = params.term;
            var queryTags = '';

            if(tags != null) {
                // Add tags to search
                queryTags = tags[1];

                // Replace tags in the query text
                queryText = params.term.replace(tags[0], '');
            }

            // Remove whitespaces and split by comma
            queryText = queryText.replace(' ', '').split(',');
            queryTags = queryTags.replace(' ', '').split(',');

            // Find by text
            for(let index = 0;index < queryText.length; index++) {
                const text = queryText[index];
                if(text != '' && data.text.indexOf(text) > -1) {
                    return data;
                }
            }
            
            // Find by tag ( data-tag )
            for(let index = 0;index < queryTags.length;index++) {
                const tag = queryTags[index];
                if(tag != '' && $(data.element).data('tags') != undefined && $(data.element).data('tags').indexOf(tag) > -1) {
                    return data;
                }
            }

            // Return `null` if the term should not be displayed
            return null;
        },
        templateResult: function(state) {
            if(!state.id) {
                return state.text;
            }

            var $el = $(state.element);

            if($el.data().content !== undefined) {
                return $($el.data().content);
            }

            return state.text;
        }
    });
}

// Custom submit for user preferences
function userPreferencesFormSubmit() {
    let $form = $("#userPreferences");
    // Replace all checkboxes with hidden input fields
    $form.find('input[type="checkbox"]').each(function () {
        // Get checkbox values
        let value = $(this).is(':checked') ? 'on' : 'off';
        let id = $(this).attr('id');

        // Create hidden input
        $('<input type="hidden">')
            .attr('id', id)
            .attr('name', id)
            .val(value)
            .appendTo($(this).parent());

        // Disable checkbox so it won't be submitted
        $(this).attr('disabled', true);
    });
    $form.submit();
}

// Initialise date time picker
function initDatePicker($element, dateTimeFormat, options = {}, onChangeCallback = null, clearButtonActive = true, onClearCallback = null) {
    // Check for date format
    if(dateTimeFormat == undefined) {
        console.error('dateTimeFormat needs to be defined!');
        return false;
    }
    
    // Get linked field
    const $linkedField = $element.closest('form').find('#' + $element.data().linkField);
    const linkedFormat = $element.data().linkFormat;

    // Link field initial value
    const linkedFieldVal = $linkedField.val();

    if(calendarType == 'Jalali') {
        $element.persianDatepicker(Object.assign({
            initialValue: ((linkedFieldVal != undefined) ? linkedFieldVal : false),
            altField: '#' + $element.data().linkField,
            altFieldFormatter: function(unixTime) {
                return (moment.unix(unixTime / 1000).format(linkedFormat));
            },
            onSelect: function() {
                // Trigger change after close
                $element.trigger('change');
                $linkedField.trigger('change');

                // Callback if exists
                if(onChangeCallback != null && typeof onChangeCallback == 'function') {
                    onChangeCallback();
                }
            }
        }, options));

        // Add the readonly property
        $element.attr('readonly', 'readonly');
    } else if(calendarType == 'Gregorian') {
        // Remove tabindex from modal to fix flatpickr bug
        $element.parents('.bootbox.modal').removeAttr('tabindex');

        // Create flatpickr
        flatpickr($element, Object.assign({
            allowInput: false,
            dateFormat: dateTimeFormat,
            locale: language,
            onChange: function(selectedDates, dateStr, instance) {
                let newDate = (dateStr != '') ? moment(instance.formatDate(selectedDates[0], dateFormat)).format(linkedFormat) : '';
                
                // Update linked field
                if(newDate == '') {
                    $linkedField.val('');
                } else {
                    $linkedField.val(newDate);
                }

                if(onChangeCallback != null && typeof onChangeCallback == 'function') {
                    onChangeCallback();
                }
            }
        }, options));

        // Update date picker if linked field has a default value
        if(linkedFieldVal != undefined && linkedFieldVal != "") {
            updateDatePicker($element, linkedFieldVal, dateTimeFormat);
        }
    }

    // Clear button
    if(clearButtonActive) {
        $element.parent().find('.date-clear-button').removeClass('hidden').click(function() {
            updateDatePicker($element, '');

            // Clear callback if defined
            if(onClearCallback != null && typeof onClearCallback == 'function') {
                onClearCallback();
            }
        });
    }
}

// Update date picker/pickers
function updateDatePicker($element, date, format, triggerChange = false) {
    if(calendarType == 'Gregorian') {
        // Update gregorian calendar
        if($element[0]._flatpickr != undefined) {
            if(date == '') {
                $element.val('').trigger('change');
                $('#' + $element.data().linkField).val('').trigger('change');
                $element[0]._flatpickr.setDate('');
            } else if(format != undefined) {
                $element[0]._flatpickr.setDate(date, triggerChange, format);
            } else {
                $element[0]._flatpickr.setDate(date);
            }
        }
    } else if(calendarType == 'Jalali'){
        if(date == '') {
            $element.val('').trigger('change');
            $('#' + $element.data().linkField).val('').trigger('change');
        } else {
            // Update jalali calendar
            $element.data().datepicker.setDate(moment(date, format).unix() * 1000);
        }
    }
}

// Destroy date picker/pickers
function destroyDatePickers($elements) {
    $elements.each(function() {
        if(calendarType == 'Gregorian') {
            // Destroy gregorian pickers
            if(this._flatpickr != undefined) {
                this._flatpickr.destroy();
            }
        }
    });
}