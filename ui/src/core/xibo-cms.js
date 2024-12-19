/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
var lastForm;
var gridTimeouts = [];
var buttonsTemplate;
var autoSubmitTemplate = null;

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

// Configure a global error handler for data tables
$.fn.dataTable.ext.errMode = function (settings, helpPage, message) {
    console.error(message);
};

// Set up the light boxes
$(document).delegate('*[data-toggle="lightbox"]', 'click', function(event) {
    event.preventDefault();
    $(this).ekkoLightbox({
        onContentLoaded: function() {
            var $container = $('.ekko-lightbox-container');
            $container.css({'max-height': $container.height(), "height": "", 'max-width': $container.width()});
            $container.parents('.modal-content').css({'width' : 'fit-content'});
        }
    });
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
 * @param {Object} [options] (options for the form)
 */
function XiboInitialise(scope, options) {

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

                if (formValues == null) {
                    localStorage.setItem(gridName, JSON.stringify(form.serializeArray()));
                    formValues = JSON.parse(localStorage.getItem(gridName));
                }
            } catch (e) {
                formValues = [];
            }

            // flatten the array
            // if we have multiple items with the same name.
            let formValuesUpdated = [];
            formValues.forEach(element => {
                if (element.name in formValuesUpdated) {
                    formValuesUpdated[element.name].value = [element.value, formValuesUpdated[element.name].value]
                } else {
                    formValuesUpdated[element.name] = element
                }
            })

            const url = new URL(window.location.href);
            var params = new URLSearchParams(url.search.slice(1));

            $.each(Object.values(formValuesUpdated), function(key, element) {
                // Does this field exist in the form
                var fieldName = element.name.replace(/\[\]/, '\\\\[\\\\]');
                try {
                    var field = form.find('input[name="' + fieldName + '"], select[name="' + fieldName + '"], select[name="' + element.name + '"]');

                    if (params.get(fieldName) !== null) {
                        field.val(params.get(fieldName))
                    } else if (field.length > 0) {
                        field.val(element.value);
                    }

                    // if we have pagedSelect as inline filter for the grid
                    // set the initial value here, to ensure the correct option gets selected.
                    if (field.parent().hasClass('pagedSelect')) {
                        field.data('initial-value', element.value)
                    }
                } catch (e) {
                    console.error("Error populating form saved value with selector input[name=" + element.name + "], select[name=" + element.name + "]");
                }
            });
        }

        var filterRefresh = _.debounce(function () {
            if (gridName != undefined) {
                localStorage.setItem(gridName, JSON.stringify(form.serializeArray()));
            }

            $(this).closest(".XiboGrid").find("table.dataTable").first().DataTable().ajax.reload();
        }, 500);

        // Add clear filter button and handle behaviour
        // Create template for the inputs
        var buttonTemplate = Handlebars.compile(
            $('#xibo-filter-clear-button').html()
        );

        // Append button to tabs or container (if we don't have tabs)
        if ($(this).find(".XiboFilter .nav-tabs").length > 0) {
            if ($(this).find(".XiboFilter .nav-tabs .clear-filter-btn-container").length === 0 && form.length > 0) {
                $(this).find(".XiboFilter .nav-tabs").append(buttonTemplate);
            }
        } else {
            if ($(this).find(".XiboFilter .clear-filter-btn-container").length === 0 && form.length > 0) {
                $(this).find(".XiboFilter").prepend(buttonTemplate);
                $(this).find(".XiboFilter .FilterDiv").addClass("pt-0");
            }
        }

        // Prevent enter key to submit form
        $(this).find(".XiboFilter .clear-filter-btn").off().on('click', function(event) {
            // Reset fields
            form[0].reset();

            // Trigger change on select2
            form.find('.select2-hidden-accessible').val('').trigger('change');

            // Clear tags input
            form.find('.bootstrap-tagsinput').tagsinput('clear');

            // Refresh filter
            filterRefresh.call(this);
        });

        // Prevent enter key to submit form
        $(this).find(".XiboFilter form").on('keydown', function(event) {
            if(event.keyCode == 13) {
                event.preventDefault();
                return false;
            }
        });
        // Bind the filter form
        $(this).find('.XiboFilter form input').on('keyup', filterRefresh);
        $(this).find('.XiboFilter form input[type="number"]').on('change', filterRefresh);
        $(this).find('.XiboFilter form input[type="checkbox"]').on('change', filterRefresh);
        $(this).find('.XiboFilter form select').on('change', filterRefresh);
        $(this).find('.XiboFilter form input.dateControl').on('change', filterRefresh);

        // Folder navigation relies on triggering the change event on this hidden field.
        $(this).find('.XiboFilter form #folderId').on('change', filterRefresh);

        // Tags need on change trigger.
        $(this).find('.XiboFilter form input[data-role="tagsInputInline"]').on('change', filterRefresh);

        // check to see if we need to share folder tree state globally or per page
        var gridFolderState = rememberFolderTreeStateGlobally ? 'grid-folder-tree-state' : 'grid_'+gridName ;
        // init the jsTree
        initJsTreeAjax($(this).find('#container-folder-tree'), gridFolderState, false)
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
        // Ignore the date picker helpers
        // and input groups that are hidden
        ignore: '.datePickerHelper, :hidden>*:not(.flatpickr-input)',
        errorElement: 'span',
        errorPlacement: function(error, element) {
            if($(element).hasClass('dateControl')) {
                // Places the error label date controller
                error.insertAfter(element.parent());
            } else {
                // Places the error label after the invalid element
                error.insertAfter(element);
            }
        },
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

    // Prevent manual numbers input outside of min/max
    $(
        scope + ' input[type="number"][min], ' +
        scope + ' input[type="number"][max]',
    ).each((_idx, input) => {
        const $input = $(input);
        const max = $input.attr('max');
        const min = $input.attr('min');

        $input.on('blur', () => {
        (max && $input.val() > max) &&
            ($input.val(max).trigger('change'));
        (min && $input.val() < min) &&
            ($input.val(min).trigger('change'));
        });
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

    // Special drop down forms (to act as a menu instead of a usual dropdown)
    $(scope + ' .dropdown-menu').on('click', function(e) {
        if($(this).hasClass('dropdown-menu-form')) {
            e.stopPropagation();
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
        var $this = $(this);
        var anchor = $this.data("anchorElement");
        var inModal = $(scope).hasClass("modal");
        if (anchor !== undefined && anchor !== "") {
            makePagedSelect($(this), $(anchor));
        } else if (inModal) {
            makePagedSelect($(this), $(scope));
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

    // Colour picker
    $(scope + " .colorpicker-input:not(.colorpicker-element)").each(function() {
        $(this).colorpicker({
            container: $(this).parent(),
        });
    });

    // Initialize tags input form
    $(scope + " input[data-role=tagsInputInline], " + scope + " input[data-role=tagsInputForm], " + scope + " select[multiple][data-role=tagsInputForm]").each(function() {
        var self = this;
        var autoCompleteUrl = $(self).data('autoCompleteUrl');

        if(autoCompleteUrl != undefined && autoCompleteUrl != '') {
            // Tags input with autocomplete
            var tags = new Bloodhound({
                datumTokenizer: Bloodhound.tokenizers.whitespace,
                queryTokenizer: Bloodhound.tokenizers.whitespace,
                initialize: false,
                remote: {
                    url: autoCompleteUrl,
                    prepare: function(query, settings) {
                        settings.data = { tag: query };
                        return settings;
                    },
                    filter: function(list) {
                        return $.map(list.data, function(tagObj) {
                            return {
                                tag: tagObj.tag
                            };
                        });
                    }
                },
                sorter: function(a, b) {
                    var nameA = a.tag.toUpperCase();
                    var nameB = b.tag.toUpperCase();
                    if (nameA < nameB) {
                        return -1;
                    }
                    if (nameA > nameB) {
                        return 1;
                    }

                    // Names must be the same
                    return 0;
                }
            });

            var promise = tags.initialize();

            promise
            .done(function() {
                // Initialise tagsinput with autocomplete
                $(self).tagsinput({
                    typeaheadjs: {
                        name: 'tags',
                        displayKey: 'tag',
                        valueKey: 'tag',
                        source: tags.ttAdapter()
                    }
                });
            })
            .fail(function() {
                console.info('Auto-complete for tag failed! Using default...');
                $(self).tagsinput();
            });
        } else {
            // Normal tags input
            $(self).tagsinput();
        }

        // When tagsinput loses focus, add the tag,
        // do not rely solely on comma or selection from suggestions
        $('.bootstrap-tagsinput input').blur(function() {
            if ($(this).val() !== '') {
                $(self).tagsinput('add', $(this).val());
                $(this).val('');
            }
        });
    });

    // Initialize tag with values function from xibo-forms.js
    // this needs to be initialised only once, otherwise some functions in it will be executed multiple times.
    if ($(scope + " .tags-with-value").length > 0) {
        tagsWithValues($(scope).find("form").attr('id'));
    }

    $(scope + ' .XiboCommand').each(function () {
        // TODO: Move to forms.js eventually
        // Get main container
        var $mainContainer = $(this);

        // Get input and its value
        var $input = $mainContainer.find('input');

        // Hide main input
        $input.hide();

        var commandTypes = {
            freetext: translations.freeTextCommand,
            tpv_led: 'Philips Android',
            rs232: 'RS232',
            intent: 'Android Intent',
            http: 'HTTP',
        };

        // Load templates
        var loadTemplates = function (type) {
            var initVal = $input.val();
            var parsedVal = parseCommandValue($input.val());
            var $targetContainer = $mainContainer.find('.command-inputs');

            // Create template for the inputs
            var inputTemplate = Handlebars.compile(
                $('#command-input-' + type + '-template').html()
            );
            $targetContainer.html(
                inputTemplate({
                    value: parsedVal.value,
                    initVal: initVal,
                    unique: new Date().valueOf(),
                })
            );

            // Extra templates for Android intent
            if (type == 'intent') {
                var inputExtraTemplate = Handlebars.compile(
                    $('#command-input-intent-extra-template').html()
                );
                if (parsedVal.value.extras != undefined) {
                    parsedVal.value.extras.forEach(function (el) {
                        $targetContainer
                            .find('.intent-extra-container')
                            .append(inputExtraTemplate(el));
                    });
                }

                // Add extra element
                $targetContainer.find('.intent-add-extra').on('click', function () {
                    $targetContainer.find('.intent-extra-container').append(inputExtraTemplate({}));
                    updateValue(type);
                });

                // Remove extra element
                $targetContainer
                    .off('click', '.intent-remove-extra')
                    .on('click', '.intent-remove-extra', function () {
                        $(this).parents('.intent-extra-element').remove();
                        updateValue(type);
                    });
            }

            // Header and body templates for HTTP intent
            if (type == 'http') {
                var inputKeyValueElementTemplate = Handlebars.compile(
                    $('#command-input-http-key-value-template').html()
                );
                var sectionClasses = [
                    '.query-builder-container',
                    '.http-headers-container',
                    '.http-data-container',
                ];
                var sectionValues = [
                    parsedVal.value != undefined && parsedVal.value.query != undefined
                        ? parsedVal.value.query
                        : null,
                    parsedVal.value != undefined &&
                    parsedVal.value.requestOptions != undefined &&
                    parsedVal.value.requestOptions.headers != undefined
                        ? parsedVal.value.requestOptions.headers
                        : null,
                    parsedVal.value != undefined &&
                    parsedVal.value.requestOptions != undefined &&
                    parsedVal.value.requestOptions.body != undefined
                        ? parsedVal.value.requestOptions.body
                        : null,
                ];

                // Generate key value pairs in a container
                var populateKeyValues = function ($container, values) {
                    // Empty container
                    $container.find('.http-key-value-container').empty();

                    // Populate with the new values
                    for (let i = 0; i < Object.keys(values).length; i++) {
                        $container.find('.http-key-value-container').append(
                            inputKeyValueElementTemplate({
                                key: Object.keys(values)[i],
                                value: Object.values(values)[i],
                            })
                        );
                    }
                };

                // Update all the key-value/raw fields
                var updateKeyValueRawFields = function (forceUpdateTextArea) {
                    // Update text area even if the checkbox for raw is off
                    forceUpdateTextArea =
                        forceUpdateTextArea != undefined ? forceUpdateTextArea : false;

                    var $parentContainer = $(this).parents('.request-section');

                    // Get value from JSON string
                    var parseJsonFromString = function (valueToParse) {
                        var parsedValue;

                        try {
                            parsedValue = JSON.parse(valueToParse);
                        } catch (error) {
                            console.warn('Value not a JSON!');
                        }

                        return parsedValue;
                    };

                    // Create a JSON string from a key-value pair
                    var createJSONStringFromKeyValue = function ($container) {
                        var elementsObject = {};

                        $container
                            .find('.http-key-value-container .http-key-value-element')
                            .each(function () {
                                var $el = $(this);
                                var elKey = $el.find('.http-key').val();
                                var elValue = $el.find('.http-value').val();

                                // Add to final command if all fields are correct
                                if (![elKey, elValue].includes('')) {
                                    elementsObject[elKey] = elValue;
                                    $el.removeClass('invalid');
                                } else {
                                    $el.addClass('invalid');
                                }
                            });

                        return JSON.stringify(elementsObject);
                    };

                    //
                    var decodeQueryString = function (valueToParse) {
                        var parsedValue;

                        try {
                            parsedValue =
                                '{"' +
                                decodeURI(valueToParse.replace(/&/g, '","').replace(/=/g, '":"')) +
                                '"}';
                        } catch (error) {
                            console.warn('Decode URI failed!');
                        }

                        return parsedValue;
                    };

                    // Create query string from a set of key values
                    var createQueryStringFromKeyValues = function ($container) {
                        var elementsObject = {};
                        $container
                            .find('.http-key-value-container .http-key-value-element')
                            .each(function () {
                                var $el = $(this);
                                var elKey = $el.find('.http-key').val();
                                var elValue = $el.find('.http-value').val();

                                // Add to final command if all fields are correct
                                if (![elKey, elValue].includes('')) {
                                    elementsObject[elKey] = elValue;
                                }
                            });

                        // Build body param string
                        var paramsString = Object.keys(elementsObject)
                            .map((key) => key + '=' + elementsObject[key])
                            .join('&');

                        return paramsString;
                    };

                    // Get current content type
                    var contentType = $targetContainer.find('.http-contenttype').val();

                    if ($(this).is(':checked') || forceUpdateTextArea) {
                        // Create string from key value elements
                        if (
                            !$parentContainer.find('textarea').hasClass('http-data') ||
                            contentType == 'application/json'
                        ) {
                            $parentContainer
                                .find('textarea')
                                .val(createJSONStringFromKeyValue($parentContainer));
                        } else if (contentType == 'application/x-www-form-urlencoded') {
                            $parentContainer
                                .find('textarea')
                                .val(createQueryStringFromKeyValues($parentContainer));
                        }
                    } else {
                        // Update key value elements based on the textarea value
                        var builtValue;

                        if (
                            !$parentContainer.find('textarea').hasClass('http-data') ||
                            contentType == 'application/json'
                        ) {
                            // Parse JSON from textarea
                            builtValue = parseJsonFromString(
                                $parentContainer.find('textarea').val()
                            );
                        } else if (contentType == 'application/x-www-form-urlencoded') {
                            builtValue = parseJsonFromString(
                                decodeQueryString($parentContainer.find('textarea').val())
                            );
                        }

                        // if value exists, populate key-value elements
                        if (builtValue) {
                            populateKeyValues($parentContainer, builtValue);
                        }
                    }
                };

                // Create key pair sections
                for (let i = 0; i < sectionValues.length; i++) {
                    var sectionValue = sectionValues[i];
                    var $sectionContainer = $targetContainer.find(sectionClasses[i]);

                    if (sectionValue != null) {
                        populateKeyValues($sectionContainer, sectionValue);
                    }

                    // Handle Add extra element
                    $sectionContainer.find('.http-key-value-add').on('click', function (el) {
                        $(this)
                            .parent()
                            .find('.http-key-value-container')
                            .append(inputKeyValueElementTemplate({}));
                        updateValue(type);
                    });

                    // Handle Remove extra element
                    $sectionContainer
                        .off('click', '.http-key-value-remove')
                        .on('click', '.http-key-value-remove', function (el) {
                            $(this).parents('.http-key-value-element').remove();
                            updateValue(type);
                        });

                    // Handle Raw checkbox input
                    $sectionContainer
                        .parent()
                        .find('.form-check input[type="checkbox"]')
                        .off('change')
                        .on('change', function () {
                            if (
                                $(this).hasClass('show-raw-headers') ||
                                $(this).hasClass('show-raw-data')
                            ) {
                                updateKeyValueRawFields.bind(this)();
                            } else {
                                updateValue(type);
                            }

                            // Toggle fields visibility
                            var $parentContainer = $(this).parents('.request-section');
                            $parentContainer
                                .find($(this).data('toggleElement'))
                                .toggleClass($(this).data('toggleClass'), $(this).is(':checked'));
                            $parentContainer
                                .find($(this).data('toggleElementReverse'))
                                .toggleClass($(this).data('toggleClass'), !$(this).is(':checked'));
                        });

                    // Value change makes fields to be updated
                    $sectionContainer
                        .parent()
                        .off('change', '.http-key-value-container .http-key-value-element input')
                        .on(
                            'change',
                            '.http-key-value-container .http-key-value-element input',
                            function () {
                                updateKeyValueRawFields.bind(this)(true);
                            }
                        );

                    // Call update when loading each section
                    updateKeyValueRawFields.bind($sectionContainer)(true);
                }

                // Handle content type behaviour
                $targetContainer
                    .find('.http-contenttype')
                    .off('change')
                    .on('change', function () {
                        var isPlainText = $(this).val() == 'text/plain';

                        $targetContainer
                            .find('.show-raw-data')
                            .parent()
                            .toggleClass('d-none', isPlainText);
                        $targetContainer
                            .find('.show-raw-data')
                            .prop('checked', isPlainText)
                            .trigger('change');

                        // Update data raw field based on the contenttype
                        if (!isPlainText) {
                            updateKeyValueRawFields.bind($('.http-data-container'))(true);
                        }
                    });
            }

            // Bind input changes to the old input field
            $targetContainer
                .off('change', 'input:not(.ignore-change), select, textarea')
                .on('change', 'input:not(.ignore-change), select, textarea', function () {
                    updateValue(type);
                });

            updateValue(type);
        };

        // Parse command value and return object
        var parseCommandValue = function (value) {
            var valueObj = {};

            if (value == '' || value == undefined) {
                valueObj.type = 'freetext';
                valueObj.value = '';
            } else {
                var splitValue = value.split('|');

                if (splitValue.length == 1) {
                    // free text
                    valueObj.type = 'freetext';
                    valueObj.value = value;
                } else {
                    valueObj.type = splitValue[0];

                    switch (valueObj.type) {
                        case 'intent':
                            // intent|<type|activity,service,broadcast>|<activity>|[<extras>]
                            valueObj.value = {
                                // <type|activity,service,broadcast>
                                type: splitValue[1],
                                name: splitValue[2],
                                // [<extras>]
                                //{
                                //  "name": "<extra name>",
                                //  "type": "<type|string,int,bool,intArray>",
                                //  "value": <the value of the above type>
                                //}
                                extras: splitValue.length > 3 ? JSON.parse(splitValue[3]) : [],
                            };
                            break;
                        case 'rs232':
                            // rs232|<connection string>|<command>
                            var connectionStringRaw = splitValue[1].split(',');
                            var connectionString = {
                                deviceName: connectionStringRaw[0],
                                baudRate: connectionStringRaw[1],
                                dataBits: connectionStringRaw[2],
                                parity: connectionStringRaw[3],
                                stopBits: connectionStringRaw[4],
                                handshake: connectionStringRaw[5],
                                hexSupport: connectionStringRaw[6],
                            };

                            valueObj.value = {
                                // <COM#>,<Baud Rate>,<Data Bits>,<Parity|None,Odd,Even,Mark,Space>,<StopBits|None,One,Two,OnePointFive>,<Handshake|None,XOnXOff,RequestToSend,RequestToSendXOnXOff>,<HexSupport|0,1,default 0>
                                // <DeviceName>,<Baud Rate>,<Data Bits>,<Parity>,<StopBits>,<FlowControl>
                                cs: connectionString,
                                command: splitValue[2],
                            };
                            break;
                        case 'tpv_led':
                            valueObj.type = 'tpv_led';
                            valueObj.value = splitValue[1];

                            break;
                        case 'http':
                            var requestOptions = {};
                            var contentType = splitValue[2];

                            // try to parse JSON
                            if (splitValue[3] != undefined) {
                                try {
                                    requestOptions = JSON.parse(splitValue[3]);
                                } catch (error) {
                                    console.warn('Skip JSON parse!');
                                }
                            }

                            // parse headers
                            if(requestOptions.headers != undefined) {
                                try {
                                    requestOptions.headers = JSON.parse(requestOptions.headers);
                                } catch (error) {
                                    console.warn('Skip headers JSON parse!');
                                }
                            }

                            // parse body
                            if (requestOptions.body != undefined) {
                                try {
                                    if (contentType) {
                                        if (contentType == 'application/json') {
                                            requestOptions.body = JSON.parse(requestOptions.body);
                                        } else if (
                                            contentType == 'application/x-www-form-urlencoded'
                                        ) {
                                            var bodyElements = decodeURI(requestOptions.body).split('&');
                                            var newParsedElements = {}

                                            bodyElements.forEach(element => {
                                                var elementSplit = element.split('=');
                                                if(elementSplit.length = 2) {
                                                    newParsedElements[elementSplit[0]] = elementSplit[1];
                                                }
                                            });

                                            requestOptions.body = newParsedElements;
                                        }
                                    }

                                } catch (error) {
                                    console.warn('Skip body parse!');
                                }
                            }

                            // http|url|<requestOptions>
                            valueObj.type = 'http';
                            valueObj.value = {
                                // <url>
                                url: splitValue[1],
                                // <contentType|application/x-www-form-urlencoded|application/json|text/plain>
                                contenttype: splitValue[2],
                                // <requestOptions|{<method|GET,HEAD,POST,PUT,PATCH,DELETE,OPTIONS,TRACE,CONNECT>,<headers|{[key:value]}>,<body|based on content type>}>
                                requestOptions: requestOptions,
                            };
                            break;
                        default:
                            valueObj.type = 'freetext';
                            valueObj.value = value;
                            break;
                    }
                }
            }

            return valueObj;
        };

        // Build command value
        var updateValue = function (type) {
            var builtString = '';
            var invalidValue = false;
            var $container = $mainContainer.find('.command-inputs');

            switch (type) {
                case 'tpv_led':
                    builtString = 'tpv_led|' + $container.find('.tpv-led-command').val();
                    break;
                case 'http':
                    // URL
                    var url = $container.find('.http-url').val();
                    var paramsObj = {};

                    // Validate URL
                    if (url == '') {
                        invalidValue = true;
                        $container.find('.http-url').addClass('invalid');
                    } else {
                        $container.find('.http-url').removeClass('invalid');
                    }

                    // Query builder
                    if ($container.find('.show-query-builder').is(':checked')) {
                        // Check if url has params
                        if (url.split('?').length == 2) {
                            var urlParams = url.split('?')[1];
                            var params = [];

                            try {
                                params = decodeURI(urlParams).split('&');
                            } catch (e) {
                                console.warn('malformed URI:' + e);
                            }

                            // Update URL
                            url = url.split('?')[0];

                            // Add params to query builder
                            for (let i = 0; i < params.length; i++) {
                                var param = params[i].split('=');
                                if (param.length != 2) {
                                    continue;
                                }

                                paramsObj[param[0]] = param[1];
                            }
                        }

                        // Grab all the key-value pairs
                        $container
                            .find(
                                '.query-builder-container .http-key-value-container .http-key-value-element'
                            )
                            .each(function () {
                                var $el = $(this);
                                $el.removeClass('invalid');
                                var paramName = $el.find('.http-key').val();
                                var paramValue = $el.find('.http-value').val();

                                // encode uri
                                try {
                                    paramName = encodeURI(paramName);
                                    paramValue = encodeURI(paramValue);
                                } catch (error) {
                                    console.warn('malformed URI:' + e);
                                    paramName = '';
                                    paramValue = '';
                                }

                                // Add to final command if all fields are correct
                                if (![paramName, paramValue].includes('')) {
                                    paramsObj[paramName] = paramValue;
                                    $el.removeClass('invalid');
                                } else {
                                    invalidValue = true;
                                    $el.addClass('invalid');
                                }
                            });

                        // Build param string
                        var paramsString = Object.keys(paramsObj)
                            .map((key) => key + '=' + paramsObj[key])
                            .join('&');

                        // Append to url
                        url += paramsString != '' ? '?' + encodeURI(paramsString) : '';
                    }

                    // Build request options
                    var requestOptions = {};

                    // Method
                    requestOptions.method = $container.find('.http-method').val();

                    // contenttype
                    var contentType = $container.find('.http-contenttype').val();

                    // Custom Headers
                    var headers = $container.find('.http-headers').val();

                    // validate headers
                    $container.find('.http-headers').parent().removeClass('invalid');
                    try {
                        JSON.parse(headers);
                        requestOptions.headers = headers;
                    } catch (e) {
                        console.warn('Invalid headers: ' + e);
                        invalidValue = true;
                        $container.find('.http-headers').parent().addClass('invalid');
                    }

                    // body data
                    var bodyData = $container.find('.http-data').val();

                    // validate body data
                    $container.find('.http-data').parent().removeClass('invalid');
                    try {
                        if (contentType == 'application/json') {
                            JSON.parse(bodyData);
                        } else if (contentType == 'application/x-www-form-urlencoded') {
                            decodeURI(bodyData);
                        }

                        requestOptions.body = bodyData;
                    } catch (e) {
                        console.warn('Invalid body: ' + e);
                        invalidValue = true;
                        $container.find('.http-data').parent().addClass('invalid');
                    }

                    // Create final JSON string
                    if (typeof requestOptions == 'object') {
                        requestOptions = JSON.stringify(requestOptions);
                    }

                    // Build final string
                    builtString = 'http|';
                    builtString += url + '|';
                    builtString += contentType + '|';
                    builtString += requestOptions;

                    break;
                case 'rs232':
                    // Get values
                    var deviceNameVal = $container.find('.rs232-device-name').val();
                    var baudRateVal = $container.find('.rs232-baud-rate').val();
                    var dataBitsVal = $container.find('.rs232-data-bits').val();
                    var parityVal = $container.find('.rs232-parity').val();
                    var stopBitsVal = $container.find('.rs232-stop-bits').val();
                    var handshakeVal = $container.find('.rs232-handshake').val();
                    var hexSupportVal = $container.find('.rs232-hex-support').val();
                    var commandVal = $container.find('.rs232-command').val();

                    $container
                        .find('.rs232-device-name')
                        .toggleClass('invalid', deviceNameVal == '');
                    $container.find('.rs232-baud-rate').toggleClass('invalid', baudRateVal == '');
                    $container.find('.rs232-data-bits').toggleClass('invalid', dataBitsVal == '');

                    if ([deviceNameVal, baudRateVal, dataBitsVal].includes('')) {
                        invalidValue = true;
                    }

                    builtString = 'rs232|';
                    builtString += deviceNameVal != '' ? deviceNameVal + ',' : '';
                    builtString += baudRateVal != '' ? baudRateVal + ',' : '';
                    builtString += dataBitsVal != '' ? dataBitsVal + ',' : '';
                    builtString += parityVal != '' ? parityVal + ',' : '';
                    builtString += stopBitsVal != '' ? stopBitsVal + ',' : '';
                    builtString += handshakeVal != '' ? handshakeVal + ',' : '';
                    builtString += hexSupportVal;
                    builtString += '|' + commandVal;
                    break;
                case 'intent':
                    builtString =
                        'intent|' +
                        $container.find('.intent-type').val() +
                        '|' +
                        $container.find('.intent-name').val();

                    var nameVal = $container.find('.intent-name').val();

                    if (nameVal == '') {
                        $container.find('.intent-name').addClass('invalid');
                        invalidValue = true;
                    } else {
                        $container.find('.intent-name').removeClass('invalid');
                    }

                    // Extra values array
                    var extraValues = [];

                    // Get values from input fields
                    $container.find('.intent-extra-element').each(function () {
                        var $el = $(this);
                        $el.removeClass('invalid');
                        var extraName = $el.find('.extra-name').val();
                        var extraType = $el.find('.extra-type').val();
                        var extraValue = $el.find('.extra-value').val();

                        // Validate values
                        if (extraType == 'intArray') {
                            // Transform the value into an array
                            extraValue = extraValue
                                .replace(' ', '')
                                .split(',')
                                .map(function (x) {
                                    return x != '' ? Number(x) : '';
                                });

                            // Check if all the array elements are numbers ( and non empty )
                            for (var index = 0; index < extraValue.length; index++) {
                                var element = extraValue[index];

                                if (isNaN(element) || element == '') {
                                    extraValue = '';
                                    break;
                                }
                            }
                        } else if (extraType == 'int' && extraValue != '') {
                            extraValue = isNaN(Number(extraValue)) ? '' : Number(extraValue);
                        } else if (extraType == 'bool' && extraValue != '') {
                            extraValue = extraValue == 'true';
                        }

                        // Add to final command if all fields are correct
                        if (![extraName, extraType, extraValue].includes('')) {
                            extraValues.push({
                                name: extraName,
                                type: extraType,
                                value: extraValue,
                            });
                        } else {
                            invalidValue = true;
                            $el.addClass('invalid');
                        }
                    });

                    // Append extra values array in JSON format
                    if (extraValues.length > 0) {
                        builtString += '|' + JSON.stringify(extraValues);
                    }

                    break;
                default:
                    builtString = $container.find('.free-text').val();
                    break;
            }

            // Update command preview
            if (invalidValue) {
                $input.val('');
                $mainContainer
                    .find('.command-preview code')
                    .text($mainContainer.find('.command-preview').data('invalidMessage'));
                $mainContainer.find('.command-preview').addClass('invalid');
            } else {
                $input.val(builtString);
                $mainContainer.find('.command-preview code').text(builtString);
                $mainContainer.find('.command-preview').removeClass('invalid');
            }
        };

        // Get init command type
        var initType = parseCommandValue($input.val()).type;

        // Create basic type element
        var optionsTemplate = Handlebars.compile($('#command-input-main-template').html());
        $input.before(
            optionsTemplate({
                types: commandTypes,
                type: initType,
                unique: new Date().valueOf(),
            })
        );

        // Set template on first run
        loadTemplates(initType);

        // Set template on command type change
        $(this)
            .find('.command-type')
            .change(function () {
                loadTemplates($(this).val());
            });

        // Link checkbox to input preview
        $(this)
            .find('.show-command-preview')
            .change(function () {
                $mainContainer.find('.command-preview').toggle($(this).is(':checked'));
            });

        // Disable main input
        $input.attr('readonly', 'readonly');
    });

    // Initialize color picker
    $(scope + " .XiboColorPicker").each(function() {
        // Create color picker
        createColorPicker(this);
    });

    // Handle bootstrap error when a dropdown content renders offscreen
    $(scope + ' .XiboData').on('shown.bs.dropdown', '.dropdown-menu-container', function(e) {
        var $dropdownMenuShown = $(this).find('.dropdown-menu.show');
        setTimeout(function() {
            if ($dropdownMenuShown.offset().top < 0) {
                $dropdownMenuShown.offset({
                    top: 0,
                    left: $dropdownMenuShown.offset().left
                });
            }
        }, 200);
    });

    $(scope + ' #fullScreenCampaignId').each(function() {
        let $form = $(this).closest('form');
        let eventTypeId = parseInt($form.find('#eventTypeId').val());
        let mediaId = $form.find('#fullScreen-mediaId').val();
        let playlistId = $form.find('#fullScreen-playlistId').val();
        let dataObj = {};
        let url;

        if (mediaId !== null && mediaId !== undefined && mediaId !== '') {
            dataObj = {
                mediaId: mediaId,
            }
            url = $form.data().libraryGetUrl;
        } else if (playlistId != null && playlistId != undefined && playlistId !== '') {
            dataObj = {
                playlistId: playlistId,
            }
            url = $form.data().playlistGetUrl;
        }

        // get selected media or playlist details
        if (url != null && [7, 8].includes(eventTypeId)) {
            $.ajax({
                type: 'GET',
                url: url,
                cache: false,
                dataType: 'json',
                data: dataObj,
            })
              .then(
                (response) => {
                    if (!response.success) {
                        SystemMessageInline(
                          (response.message === '') ? translations.failure : response.message,
                          $form.closest('.modal'),
                        );
                    }

                    // at the moment we only add media or playlist name to readonly input
                    // this might be extended in the future.
                    if (eventTypeId == 7) {
                        $form.find('#fullScreen-media').val(response.data[0].name)
                    } else if (eventTypeId == 8) {
                        $form.find('#fullScreen-playlist').val(response.data[0].name)
                    }

                    if (response.data[0]?.fullScreenCampaignId) {
                        $form.find('#fullScreenCampaignId')
                          .val(response.data[0].fullScreenCampaignId)
                          .trigger('change')
                    }

                    if (!response.data[0]?.hasFullScreenLayout) {
                        if (eventTypeId == 7) {
                            $form.find('#fullScreenControl_media').trigger('autoOpen');
                        } else if (eventTypeId == 8) {
                            $form.find('#fullScreenControl_playlist').trigger('autoOpen');
                        }
                    }
                }, (xhr) => {
                    SystemMessage(xhr.responseText, false);
                })
        }
    })

    $(scope + ' .full-screen-layout-form').on('click autoOpen', function(ev) {
        if ($('#full-screen-schedule-modal').length != 0) {
            $('#full-screen-schedule-modal').remove();
        }

        let $target = $(ev.currentTarget);
        let $mainModal = $target.parents(scope);
        let eventTypeId = $target.closest('form').find('#eventTypeId').val()
        let mediaId = $target.closest('form').find('#fullScreen-mediaId').val();
        let playlistId = $target.closest('form').find('#fullScreen-playlistId').val();
        let readOnlySelect = $target.data('readonly');

        if ($('#full-screen-schedule-modal').length === 0) {
            // compile full screen schedule modal template
            const fullScreenSchedule = Handlebars.compile($('#full-screen-schedule-template').html());
            const config = {
                type: eventTypeId == 7 ? 'Media' : 'Playlist',
                eventTypeId : eventTypeId,
                readonlySelect: readOnlySelect
            }

            $('body').append(fullScreenSchedule(config));
            const $modal = $('#full-screen-schedule-modal');

            // If form was opened automatically
            // close background modal if we close this one
            if(ev.type === 'autoOpen') {
                $modal.find('button.close').on('click', function() {
                    $mainModal.modal('hide');
                });
            }

            $modal
              .on('show.bs.modal', function() {
                  $('.no-full-screen-layout').addClass('d-none')
              })
              .on('shown.bs.modal', function() {
                  const $form = $modal.find('form')
                  // set initial values if we have any
                  $form.find('.pagedSelect select.form-control').each(function() {
                      if ($(this).attr('id') == 'mediaId' && mediaId != null) {
                          $(this).data('initialValue', mediaId)
                      } else if ($(this).attr('id') == 'playlistId' && playlistId != null) {
                          $(this).data('initialValue', playlistId)
                      }

                      // init select2
                      makePagedSelect($(this), '#' + $form.attr('id'));
                  });

                  // init color picker
                  $form.find('.colorpicker-input:not(.colorpicker-element)').each(function() {
                      $(this).colorpicker({
                        container: $(this).parent(),
                      });
                  });

                  // change input visibility depending on what we selected for media/playlist
                  $('#mediaId, #playlistId', $form).on('select2:select', function(event) {
                      let hasFullScreenLayout = false;
                      if (event.params.data.data !== undefined) {
                          hasFullScreenLayout = event.params.data.data[0].hasFullScreenLayout;
                      } else if (event.params.data.hasFullScreenLayout !== undefined) {
                          hasFullScreenLayout = event.params.data.hasFullScreenLayout;
                      }

                      if (hasFullScreenLayout) {
                          $('.no-full-screen-layout').addClass('d-none');
                      } else {
                          if ($(this).attr('id') === 'mediaId') {
                              $('.no-full-screen-layout').removeClass('d-none');
                          } else {
                              $('.no-full-screen-layout.media-playlist-control').removeClass('d-none')
                          }
                      }
                  })

                  if (readOnlySelect) {
                      $form.find('#mediaId, #playlistId').prop('disabled', true);
                  }

                  // resolution helpText changes depending
                  // if we are on playlist or media event
                  const $resolutionControl = $('.resolution-control');
                  const $resolutionSelect = $form.find('#resolutionId');
                  if (eventTypeId == 7) {
                      $resolutionControl
                        .children('div')
                        .children('small.form-text.text-muted')
                        .text($resolutionSelect.data('transMediaHelpText'))
                  } else if (eventTypeId == 8) {
                      $resolutionControl
                        .children('div')
                        .children('small.form-text.text-muted')
                        .text($resolutionSelect.data('transPlaylistHelpText'))
                  }

                  // confirmation button was pressed
                  // create or fetch the Layout for selected media or playllist
                  // this will populate fullScreenCampaignId hidden input
                  // and close this modal once everything is done
                  $('#btnFullScreenLayoutConfirm').on('click', function(e) {
                      e.preventDefault();
                      fullscreenBeforeSubmit($form)
                  })
              })
              .on('hidden.bs.modal', function() {
                  // Fix for 2nd/overlay modal
                  $('.modal:visible').length && $(document.body).addClass('modal-open');

                  $(this).data('bs.modal', null);
              });

            // Open modal programmatically
            $modal.modal({
                backdrop: 'static',
                keyboard: false,
                show: true,
            });
        }
    })

    // Initalise remaining form fields
    if (forms && typeof forms.initFields === 'function') {
        // Initialise fields, with scope of body if we don't have a specific scope
        forms.initFields(
            (scope === " ") ? "body" : scope,
            null,
            (options && options.targetId) ? options.targetId : null,
            (options && options.readOnlyMode) ? options.readOnlyMode : false,
        );
    }
}

/**
 * DataTable processing event
 * @param e
 * @param settings
 * @param processing
 */
function dataTableProcessing(e, settings, processing) {
    if (processing) {
        if ($(e.target).closest('.widget').closest(".widget").find(".saving").length === 0) {
            $(e.target).closest('.widget').children(".widget-title").append('<span class="saving fa fa-cog fa-spin p-1"></span>');
        }
    } else {
        $(e.target).closest('.widget').closest(".widget").find(".saving").remove();
    }
}

/**
 * DataTable Draw Event
 * @param e
 * @param settings
 * @param callBack
 */
function dataTableDraw(e, settings, callBack) {

    var target = $("#" + e.target.id);

    // Check to see if we have any buttons that are multi-select
    var enabledButtons = target.find("div.dropdown-menu a.multi-select-button");

    // Check to see if we have tag filter for the current table
    var $tagsElement = target.closest(".XiboGrid").find('.FilterDiv #tags');

    // Check to see if we have a folder system for this table
    var $folderController = target.closest(".XiboGrid").find('.folder-controller');



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
        target.find("tbody").off("click", "tr").on("click", "tr", function () {
            $(this).toggleClass("selected");
            target.data().initialised = true;
        });

        // Add a button set to the table
        var template = Handlebars.compile($("#multiselect-button-template").html());
        var buttons = [];

        // Get every enabled button
        $(enabledButtons).each(function () {
          if (!searchByKey(buttons, 'id', $(this).data('id'))) {
            buttons.push({
              id: $(this).data('id'),
              gridId: e.target.id,
              text: $(this).data('text'),
              customHandler: $(this).data('customHandler'),
              customHandlerUrl: $(this).data('customHandlerUrl'),
              contentIdName: $(this).data('contentIdName'),
              sortGroup: ($(this).data('sortGroup') != undefined) ? $(this).data('sortGroup') : 0
            })
          }
        });

        // Add tag button if exist in the filter ( and user has permissions)
        if($tagsElement.length > 0 && userRoutePermissions.tags == 1) {
          buttons.push({
            id: $tagsElement.attr('id'),
            gridId: e.target.id,
            text: translations.editTags,
            contentType: target.data('contentType'),
            contentIdName: target.data('contentIdName'),
            customHandler: 'XiboMultiSelectTagFormRender',
            sortGroup: 0
          });
        }

        // Sort buttons by groups/importance
        buttons = buttons.sort(function(a, b) {
            return ((a.sortGroup > b.sortGroup) ? 1 : -1);
        });

        // Add separators
        var groupAux = 0;
        if(buttons.length > 1) {
            for (var index = 0; index < buttons.length; index++) {
                var btn = buttons[index];

                // If there's a new group ( and it's not the first element on the list)
                if(btn.sortGroup > groupAux && index > 0) {
                    buttons.splice(index, 0, {divider: true});
                    groupAux = btn.sortGroup;
                }
            }
        }

        var output = template({selectAll: translations.selectAll, withSelected: translations.withselected, buttons: buttons});
        target.closest(".dataTables_wrapper").find(".dataTables_info").prepend(output);

        // Bind to our output
        target.closest(".dataTables_wrapper").find(".dataTables_info a.XiboMultiSelectFormButton").click(function(){
            if($(this).data('customHandler') != undefined && typeof window[$(this).data('customHandler')] == 'function') {
                window[$(this).data('customHandler')](this);
            } else {
                XiboMultiSelectFormRender(this);
            }
        });

        target.closest(".dataTables_wrapper").find(".dataTables_info a.XiboMultiSelectFormCustomButton").click(function(){
            window[$(this).data('customHandler')](this);
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

    // Move and show folder controller if it's not inside of the table container
    if ($folderController.length > 0 && target.closest(".dataTables_wrapper").find('.dataTables_folder .folder-controller').length == 0) {
        $folderController.appendTo('.dataTables_folder');
        $folderController.removeClass('d-none').addClass('d-inline-flex');
    }

    (typeof callBack === 'function') && callBack();

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
    if (type != "display") {
        return "";
    }

    if (data.buttons.length <= 0) {
        return "";
    }

    if (buttonsTemplate == null) {
        buttonsTemplate = Handlebars.compile($("#buttons-template").html());
    }

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

function dataTableRoundDecimal(data, type, row) {
    if (type !== "display" && type !== "export")
        return data;

    if (data == null)
        return "";

    return parseFloat(data).toFixed(2);
}

function dataTableDateFromUnix(data, type, row) {
    if (type !== "display" && type !== "export")
        return data;

    if (data == null || data == 0)
        return "";

    return moment(data, "X").tz ? moment(data, "X").tz(timezone).format(jsDateFormat) : moment(data, "X").format(jsDateFormat);
}

function dataTableTimeFromSeconds(data, type, row) {
    if(type !== "display" && type !== "export")
        return data;

    if(data == null || data == 0)
        return "";

    // Get duration
    var duration = moment.duration(data * 1000);

    // Get the number of hours
    var hours = Math.floor(duration.asHours());

    // Format string with leading zero
    var hoursString = (hours < 10) ? '0' + hours : hours;

    return hoursString + moment.utc(duration.asMilliseconds()).format(":mm:ss");
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
    if (type !== "display") {
        return data.tags;
    }

    var returnData = '';

    if (typeof data.tags !== undefined && data.tags != null ) {
        returnData += '<div id="tagDiv">';
        data.tags.forEach((element) => returnData += '<li class="btn btn-sm btn-white btn-tag">' + element.tag + ((element.value) ? '|' + element.value : '') + '</li>')
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

function dataTableAddButtons(table, filter, allButtons, resetSort) {
    allButtons = (allButtons === undefined) ? true : allButtons;
    resetSort = (resetSort === undefined) ? false : resetSort;

    let buttons = [
        {
            extend: 'colvis',
            columns: ':not(.rowMenu)',
            text: function (dt, button, config) {
                return dt.i18n('buttons.colvis');
            }
        },
    ];

    if (resetSort) {
        buttons.push(
          {
              text: translations.defaultSorting,
              action: function ( e, dt, node, config ) {
                  table.order([]).draw();
              }
          }
        )
    }

    if (allButtons) {
        buttons.push(
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
              },
              customize: function (win) {
                  let table = $(win.document.body).find('table');
                  table.removeClass('nowrap responsive dataTable no-footer dtr-inline');
                  if (table.find('th').length > 16) {
                      table.addClass('table-sm');
                      table.css('font-size', '6px');
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
          },
        )
    }

    new $.fn.dataTable.Buttons(table, {buttons: buttons});

    table.buttons( 0, null ).container().prependTo(filter);
    $(filter).addClass('text-right');
    $(".ColVis_MasterButton").addClass("btn");
    $(filter).find('.dt-buttons button.btn-secondary').addClass('btn-outline-primary').removeClass('btn-secondary');
}

/**
 * State Load Callback
 * @param settings
 * @param callback
 * @return {{}}
 */
function dataTableStateLoadCallback(settings, callback) {
    var statePreferenceName = $("#"+settings.sTableId).data().statePreferenceName;
    var option = (statePreferenceName !== undefined) ? statePreferenceName : settings.sTableId + "Grid";
    var data = {};
    $.ajax({
        type: "GET",
        async: false,
        url: userPreferencesUrl + "?preference=" + option,
        dataType: "json",
        success: function (json) {
            try {
                if (json.success) {
                    data = JSON.parse(json.data.value);
                }
            } catch (e) {
                // Do nothing
            }
        }
    });
    return data;
}

/**
 * Save State Callback
 * @param settings
 * @param data
 */
function dataTableStateSaveCallback(settings, data) {
    var statePreferenceName = $("#"+settings.sTableId).data().statePreferenceName;
    var option = (statePreferenceName !== undefined) ? statePreferenceName : settings.sTableId + "Grid";
    updateUserPref([{
        option: option,
        value: JSON.stringify(data)
    }], function() {
        // ignore
    });
}

/**
 * Renders the formid provided
 * @param {Object} sourceObj
 * @param {Object} data
 */
function XiboFormRender(sourceObj, data = null) {

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
                if(!(typeof sourceObj === "string" || sourceObj instanceof String)) {
                    var commitUrl = sourceObj.data().commitUrl;

                    // Handle auto-submit
                    if (response.autoSubmit && commitUrl !== undefined) {
                        // grab the auto submit URL and submit it immediately
                        $.ajax({
                            type: sourceObj.data().commitMethod || "POST",
                            url: commitUrl,
                            cache: false,
                            dataType: "json",
                            success: function(autoSubmitResponse) {
                                if (autoSubmitResponse.success) {
                                    // Success - what do we do now?
                                    if (autoSubmitResponse.message !== '') {
                                        SystemMessage(autoSubmitResponse.message, true);
                                    }
                                    XiboRefreshAllGrids();
                                } else if (autoSubmitResponse.login) {
                                    // We were logged out
                                    LoginBox(autoSubmitResponse.message);
                                } else {
                                    SystemMessageInline(autoSubmitResponse.message);
                                }
                            },
                            error: function(xhr) {
                                SystemMessageInline(xhr.responseText);
                            }
                        });
                        return false;
                    }
                }

                // Set the dialog HTML to be the response HTML
                var dialogTitle = "";

                // Is there a title for the dialog?
                if (response.dialogTitle != undefined && response.dialogTitle != "") {
                    // Set the dialog title
                    dialogTitle =  response.dialogTitle;
                }

                var id = new Date().getTime();

                // Create the dialog with our parameters
                var size = 'large';
                if (sourceObj && typeof sourceObj === 'object') {
                  size = sourceObj.data().modalSize || 'large';
                }

                // Currently only support one of these at once.
                // We have to move this here before calling bootbox.dialog
                // to avoid multiple modal being opened
                bootbox.hideAll();

                var dialog = bootbox.dialog({
                        message: response.html,
                        title: dialogTitle,
                        animate: false,
                        size: size
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
                                extrabutton.addClass('btn-white');
                            }

                            extrabutton.click(function(e) {
                                e.preventDefault();

                                var $button = $(this);

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

                                eval(value);

                                return false;
                            });

                            footer.append(extrabutton);
                        });

                    // Check to see if we ought to render out a checkbox for autosubmit
                    if(!(typeof sourceObj === "string" || sourceObj instanceof String)) {
                        if (sourceObj.data().autoSubmit) {
                            if (autoSubmitTemplate === null) {
                                autoSubmitTemplate = Handlebars.compile($('#auto-submit-field-template').html());
                            }

                            footer.prepend(autoSubmitTemplate());
                        }
                    }
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

                // make bootstrap happy.
                if ($('#folder-tree-form-modal').length != 0) {
                    $('#folder-tree-form-modal').remove();
                }

                // Call Xibo Init for this form
                XiboInitialise("#"+dialog.attr("id"));

                if (dialog.find('.XiboForm').attr('id') != undefined) {
                    // if this is add form and we have some folderId selected in grid view, put that as the working folder id for this form
                    // edit forms will get the current folderId assigned to the edited object.
                    if ($('#container-folder-tree').jstree("get_selected", true)[0] !== undefined && $('#' + dialog.find('.XiboForm').attr('id') + ' #folderId').val() == '') {
                        $('#' + dialog.find('.XiboForm').attr('id') + ' #folderId').val($('#container-folder-tree').jstree("get_selected", true)[0].id);
                    }

                    initJsTreeAjax('#container-folder-form-tree', dialog.find('.XiboForm').attr('id'), true, 600);
                }

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
        console.debug("Field with strange spacing: " + $el.attr("name"));

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
        message = translations.multiselectMessage.replace('%1', "" + matches.length).replace("%2", $(button).html());
    else
        message = translations.multiselectNoItemsMessage;

    // Open a Dialog containing all the items we have identified.
    var dialog = bootbox.dialog({
            message: message,
            title: translations.multiselect,
            animate: false,
            size: 'large'
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
    extrabutton = $('<button class="btn">').html(translations.close).addClass('btn-white');
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

function XiboMultiSelectPermissionsFormOpen(button) {
    var $targetTable = $(button).parents('.XiboGrid').find('.dataTable');
    var $matches = $targetTable.find('tr.selected')
    var targetDataTable = $targetTable.DataTable();
    var requestUrl = $(button).data('customHandlerUrl');
    var elementIdName = $(button).data('contentIdName');
    var matchIds = [];

    // Get matches from the selected elements
    $matches.each(function(index, row){
        // Get data
        var rowData = targetDataTable.row(row).data();

        // Add match id to the array
        matchIds.push(rowData[elementIdName]);
    });

    if($matches.length == 0) {
        // If there are no matches, show dialog with no element selected message
        bootbox.dialog({
            message: translations.multiselectNoItemsMessage,
            title: translations.multiselect,
            animate: false,
            size: 'large',
            buttons: {
                cancel: {
                    label: translations.close,
                    className: 'btn-white btn-bb-cancel'
                }
            }
        });
    } else {
        // Render multi edit permissions form
        XiboFormRender(requestUrl, {ids: matchIds.toString()});
    }
}

function XiboMultiSelectTagFormRender(button) {
    var elementType = $(button).data('contentType');
    var elementIdName = $(button).data('contentIdName');
    var matches = [];
    var $targetTable = $(button).parents('.XiboGrid').find('.dataTable');
    var targetDataTable = $targetTable.DataTable();
    var dialogContent = '';
    var dialogId = "multiselectTagEditForm";
    var matchIds = [];
    var existingTags = [];

    // Get matches from the selected elements
    $targetTable.find('tr.selected').each(function(){
        matches.push($(this));
    });

    // If there are no matches, show form with no element selected message
    if(matches.length == 0) {
        dialogContent = translations.multiselectNoItemsMessage;
    } else {
        // Create the data for the request
        matches.forEach(function(row) {
            // Get data
            var rowData = targetDataTable.row(row).data();

            // Add match id to the array
            matchIds.push(rowData[elementIdName]);

            // Add existing tags to the array
            if(['', null].indexOf(rowData.tags) === -1) {
                rowData.tags.forEach(function(tag) {
                    if (existingTags.indexOf(tag) === -1) {
                        existingTags.push(tag.tag + ((tag.value) ? '|' + tag.value : ''));
                    }
                });
            }
        });

        dialogContent = Handlebars.compile($('#multiselect-tag-edit-form-template').html());
    }

    // Create dialog
    var dialog = bootbox.dialog({
        message: dialogContent,
        title: translations.multiselect,
        size: 'large',
        animate: false
    });

    // Append a footer to the dialog
    var dialogBody = dialog.find(".modal-body");
    var footer = $("<div>").addClass("modal-footer");
    dialog.find(".modal-content").append(footer);
    dialog.attr("id", dialogId);

    // Add some buttons
    var extrabutton;

    if (matches.length > 0) {
        // Save button
        extrabutton = $('<button class="btn">').html(translations.save).addClass('btn-primary save-button');

        extrabutton.click(function() {
            var newTagsToRemove = dialogBody.find('#tagsToRemove').val().split(',');
            var requestURL = dialogBody.find('#requestURL').val();

            var tagsToBeRemoved = function() {
                var tags = [];
                existingTags.forEach(function(oldTag) {
                    if(newTagsToRemove.indexOf(oldTag) == -1) {
                        tags.push(oldTag);
                    }
                });

                return tags;
            };

            var requestData = {
                targetIds: matchIds.toString(),
                targetType: elementType,
                addTags: dialogBody.find('#tagsToAdd').val(),
                removeTags: tagsToBeRemoved().toString()
            };

            // Add loading icon to the button
            $(this).append('<span class="saving fa fa-cog fa-spin"></span>');

            // Make an AJAX call
            $.ajax({
                type: 'PUT',
                url: requestURL,
                cache: false,
                dataType: "json",
                data: requestData,
                success: function(response, textStatus, error) {

                    if (response.success) {
                        toastr.success(response.message);

                        // Hide modal
                        dialog.modal('hide');
                        targetDataTable.ajax.reload(null, false);
                    }
                    else {
                        // Why did we fail?
                        if (response.login) {
                            // We were logged out
                            LoginBox(response.message);
                        }
                        else {
                            // Likely just an error that we want to report on
                            footer.find(".saving").remove();
                            SystemMessageInline(response.message, footer.closest(".modal"));
                        }


                        // Remove loading icon
                        $(this).find('.saving').remove();
                    }
                },
                error: function(responseText) {
                    SystemMessage(responseText, false);

                    // Remove loading icon
                    $(this).find('.saving').remove();
                }
            });

            // Keep the modal open
            return false;
        });

        footer.append(extrabutton);

        // Initialise existing tags ( and save a backup )
        if(existingTags.length > 0) {
            var tagsString = existingTags.toString();
            dialogBody.find('#tagsToRemove').val(tagsString);
        } else {
            dialogBody.find('#tagsToRemoveContainer').hide();
        }

        // Add element type to the request hidden input
        dialogBody.find('#requestURL').val(dialogBody.find('#requestURL').val().replace('[type]', elementType));

        // Prevent tag add
        dialogBody.find('#tagsToRemove').on('beforeItemAdd', function(event) {
            // Cancel event if the tag doesn't belong in the starting tags
            event.cancel = (existingTags.indexOf(event.item) == -1);
        });
    }

    // Close button
    extrabutton = $('<button class="btn">').html(translations.close).addClass('btn-white');
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

    // Append button
    footer.prepend(extrabutton);

    // Initialise controls
    XiboInitialise('#' + dialogId);
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
    var $form = $(form);
    var url = $form.attr("action");

    // Update any text editor instances we have
    formHelpers.updateCKEditor();

    $.ajax({
        type:$form.attr("method"),
        url:url,
        cache:false,
        dataType:"json",
        data:$form.serialize(),
        success: function(xhr, textStatus, error) {

            XiboSubmitResponse(xhr, form);

            if (callBack != null && callBack != undefined) {
                callBack(xhr, form);
            } else {
                var callBackFromForm = $form.data('submitCallBack');
                if (callBackFromForm && typeof window[callBackFromForm] === 'function') {
                    window[callBackFromForm](xhr, form);
                }
            }
        },
        error: function(xhr, textStatus, errorThrown) {
            SystemMessage(xhr.responseText, false);
        }
    });

    // Check to see if we need to call any auto-submit preferences
    // get the formid
    if ($form.closest('.modal-dialog').find('input[name=autoSubmit]').is(':checked')) {
        updateUserPref([{
            option: "autoSubmit." + $form.attr("id"),
            value: true
        }]);
    }

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
            if ($(form).data("nextFormUrl") !== undefined) {
                var responseId = ($(form).data("nextFormIdProperty") === undefined)
                    ? response.id
                    : response.data[$(form).data("nextFormIdProperty")];
                XiboFormRender($(form).data().nextFormUrl.replace(":id", responseId));
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
function XiboDialogClose(refreshTable) {
    refreshTable = refreshTable !== undefined;

    bootbox.hideAll();

    if (refreshTable) {
        XiboRefreshAllGrids();
    }
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
        const refresh = $(this).closest('.XiboGrid').data('refreshOnFormSubmit');
        if (refresh === undefined || refresh === null || refresh) {
            const table = $(this).DataTable();
            const tableOptions = table.init();

            // Only refresh if we have ajax enabled
            if(tableOptions.serverSide) {
                // Reload
                table.ajax.reload(null, false);
            }
        }
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
    window.location.reload();
}

/**
 * Update User preferences
 * @param prefs
 * @param success
 */
function updateUserPref(prefs, success) {
    // If we do not have a success function provided, then set one.
    if (success === undefined || success === null) {
        success = function(response) {
            if (response.success) {
                SystemMessage(response.message, true);
            } else if (response.login) {
                LoginBox(response.message);
            } else {
                SystemMessage(response.message, response.success);
            }
            return false;
        }
    }

    $.ajax({
        type: "post",
        url: userPreferencesUrl,
        cache: false,
        dataType: "json",
        data: {
            preference: prefs
        },
        success: success
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
            size: 'large',
            buttons: [{
                label: 'Close',
                className: 'btn-bb-close',
                callback: function() {
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
        class: "card bg-light p-3 text-danger col-sm-12 text-center form-error",
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
 * @param dataFormatter
 * @param addRandomId
 */
function makePagedSelect(element, parent, dataFormatter, addRandomId = false) {
    // If we need to append random id
    if (addRandomId === true) {
        const randomNum = Math.floor(1000000000 + Math.random() * 9000000000);
        const previousId = $(element).attr('id');
        const newId = previousId ? previousId + '_' + randomNum : randomNum;
        $(element).attr('data-select2-id', newId);
    }

    element.select2({
        dropdownParent: ((parent == null) ? $("body") : $(parent)),
        minimumResultsForSearch: (element.data('hideSearch')) ? Infinity : 1,
        ajax: {
            url: element.data("searchUrl"),
            dataType: "json",
            delay: 250,
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

                // If we have a custom data formatter
                if (
                    dataFormatter &&
                    typeof dataFormatter === 'function'
                ) {
                    data = dataFormatter(data);
                }

                // Check if we have a display all option
                var displayAll = $element.data('displayAll') ?? false;

                $.each(data.data, function(index, el) {
                    var result = {
                        "id": el[$element.data("idProperty")],
                        "text": el[$element.data("textProperty")]
                    };

                    if ($element.data("thumbnail") !== undefined) {
                        result.thumbnail = el[$element.data("thumbnail")];
                    }

                    if ($element.data('additionalProperty') !== undefined) {
                        const additionalProperties = $element.data('additionalProperty').split(',');
                        $.each(additionalProperties, function(index, property) {
                            result[property] = el[property];
                        })
                    }

                    results.push(result);
                });

                var page = params.page || 1;
                page = (page > 1) ? page - 1 : page;

                return {
                    results: results,
                    pagination: {
                        more: !displayAll && (page * 10 < data.recordsTotal)
                    }
                };
            }
        },
        templateResult: function(state) {
            var stateText = '';

            // Add thumbnail if available
            if (state.thumbnail) {
                stateText += "<span class='option-thumbnail mr-3'><img style='width: 100px; height: 60px; object-fit: cover;' src='" + state.thumbnail + "' /></span>";
            }

            // Add option text
            stateText += "<span class='option-text'>" + state.text + "</span></span>";
            return $(stateText);
        }
    });

    element.on('select2:open', function(event) {
        setTimeout(function() {
            $(event.target).data('select2').dropdown?.$search?.get(0).focus();
        }, 10);
    });

    // Set initial value if exists
    if(
        [undefined, ''].indexOf(element.data("initialValue")) == -1 &&
        [undefined, ''].indexOf(element.data("initialKey")) == -1
    ) {
        var initialValue = element.data("initialValue");
        var initialKey = element.data("initialKey");
        var textProperty = element.data("textProperty");
        var idProperty = element.data("idProperty");
        var dataObj = {};
        dataObj[initialKey] = initialValue;

        // if we have any filter options, add them here as well
        // for example isDisplaySpecific filter is important for displayGroup.search
        if (element.data("filterOptions") !== undefined) {
            dataObj = $.extend({}, dataObj, element.data("filterOptions"));
        }

        $.ajax({
            url: element.data("searchUrl"),
            type: 'GET',
            data: dataObj
        }).then(function(data) {
            // Do we need to check if it's selected
            var checkSelected = false;

            // If we have a custom data formatter
            if (
                dataFormatter &&
                typeof dataFormatter === 'function'
            ) {
                data = dataFormatter(data);
                checkSelected = true;
            }

            // create the option and append to Select2
            data.data.forEach(object => {
                var isSelected = true;

                // Check if it's selected if needed
                if(checkSelected) {
                    isSelected = (initialValue == object[idProperty]);
                }

                // Only had if the option is selected
                if (isSelected) {
                    var option = new Option(
                        object[textProperty],
                        object[idProperty],
                        isSelected,
                        isSelected
                    );
                    element.append(option)
                }
            });

            // Trigger change but skip auto save
            element.trigger(
                'change',
                [{
                    skipSave: true,
                }]
            );

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
 * @param addRandomId
 */
function makeLocalSelect(element, parent, addRandomId = false) {
    // If we need to append random id
    if (addRandomId === true) {
        const randomNum = Math.floor(1000000000 + Math.random() * 9000000000);
        const previousId = $(element).attr('id');
        const newId = previousId ? previousId + '_' + randomNum : randomNum;
        $(element).attr('data-select2-id', newId);
    }

    element.select2({
        dropdownParent: ((parent == null) ? $("body") : $(parent)),
        matcher: function(params, data) {

            var testElementFilter = function (filter, elementFilterClassName) {
                var elementFilterClass = $(data.element).data()[elementFilterClassName];

                // Get element class array ( one or more elements split by comma)
                var elementClassArray = (elementFilterClass != undefined ) ? elementFilterClass.replace(' ', '').split(',') : [];

                // If filter exists and it's not in one of the element filters, return empty data
                return (filter != undefined && filter != '' && !elementClassArray.includes(filter));
            };

            // If filterClass is defined, try to filter the elements by it
            var mainFilterClass = $(data.element.parentElement).data().filterClass;

            if(Array.isArray(mainFilterClass)) {
                for(var index = 0;index < mainFilterClass.length; index++) {
                    if (testElementFilter(mainFilterClass[index], 'filter' + index + 'Class')) {
                        return null
                    }
                }
            } else {
                if (testElementFilter(mainFilterClass, 'filterClass')) {
                    return null
                }
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
            for(var index = 0;index < queryText.length; index++) {
                var text = queryText[index];
                if(text != '' && data.text.toUpperCase().indexOf(text.toUpperCase()) > -1) {
                    return data;
                }
            }

            // Find by tag ( data-tag )
            for(var index = 0;index < queryTags.length;index++) {
                var tag = queryTags[index];
                if(tag != '' && $(data.element).data('tags') != undefined && $(data.element).data('tags').toUpperCase().indexOf(tag.toUpperCase()) > -1) {
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

    element.on('select2:open', function(event) {
        setTimeout(function() {
            $(event.target).data('select2').dropdown?.$search?.get(0).focus();
        }, 10);
    });
}

// Custom submit for user preferences
function userPreferencesFormSubmit() {
    var $form = $("#userPreferences");
    // Replace all checkboxes with hidden input fields
    $form.find('input[type="checkbox"]').each(function () {
        // Get checkbox values
        var value = $(this).is(':checked') ? 'on' : 'off';
        var id = $(this).attr('id');

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
function initDatePicker($element, baseFormat, displayFormat, options, onChangeCallback, clearButtonActive, onClearCallback) {
    // Default values
    options = (typeof options == 'undefined') ? {} : options;
    onChangeCallback = (typeof onChangeCallback == 'undefined') ? null : onChangeCallback;
    clearButtonActive = (typeof clearButtonActive == 'undefined') ? true : clearButtonActive;
    onClearCallback = (typeof onClearCallback == 'undefined') ? null : onClearCallback;

    // Check for date format
    if(baseFormat == undefined || displayFormat == undefined) {
        console.error('baseFormat and displayFormat needs to be defined!');
        return false;
    }

    if ($element.data('customFormat')) {
        displayFormat = $element.data('customFormat');
    }

    var $inputElement = $element;
    var initialValue = $element.val();

    if(calendarType == 'Jalali') {

        if(options.altField != undefined) {
            $inputElement = $(options.altField);
        } else {
            $inputElement = $('<input type="text" class="form-control" id="' + $element.attr('id') + 'Link">');
            $element.after($inputElement).hide();
        }

        $inputElement.persianDatepicker(Object.assign({
            initialValue: ((initialValue != undefined) ? initialValue : false),
            altField: '#' + $element.attr('id'),
            altFieldFormatter: function(unixTime) {
                return (moment.unix(unixTime / 1000).format(baseFormat));
            },
            onSelect: function() {
                // Trigger change after close
                $element.trigger('change');
                $inputElement.trigger('change');
            }
        }, options));

        // Add the readonly property
        $inputElement.attr('readonly', 'readonly');
    } else if(calendarType == 'Gregorian') {
        // Remove tabindex from modal to fix flatpickr bug
        $element.parents('.bootbox.modal').removeAttr('tabindex');

        flatpickr.l10ns.default.firstDayOfWeek = parseInt(moment().startOf('week').format('d'));

        // Create flatpickr
        flatpickr($element, Object.assign({
            altInput: true,
            allowInput: false,
            defaultDate: ((initialValue != undefined) ? initialValue : null),
            altInputClass: 'datePickerHelper ' + $element.attr('class'),
            disableMobile: true,
            altFormat: displayFormat,
            dateFormat: baseFormat,
            locale: (language != 'en-GB') ? language : 'default',
            defaultHour: '00',
            getWeek: function(dateObj) {
                return moment(dateObj).week();
            },
            parseDate: function(datestr, format) {
                return moment(datestr, format, true).toDate();
            },
            formatDate: function(date, format, locale) {
                return moment(date).format(format);
            }
        }, options));
    }

    // Callback for on change event
    $inputElement.change(function() {
        // Callback if exists
        if(onChangeCallback != null && typeof onChangeCallback == 'function') {
            onChangeCallback();
        }
    });

    // Clear button
    if(clearButtonActive) {
        $inputElement.parent().find('.date-clear-button').removeClass('d-none').click(function() {
            updateDatePicker($inputElement, '');

            // Clear callback if defined
            if(onClearCallback != null && typeof onClearCallback == 'function') {
                onClearCallback();
            }
        });
    }

    // Toggle button
    $inputElement.parent().find('.date-open-button').click(function() {
        if(calendarType == 'Gregorian') {
            if($inputElement[0]._flatpickr != undefined) {
                $inputElement[0]._flatpickr.open();
            }
        } else if(calendarType == 'Jalali') {
            $inputElement.data().datepicker.show();
        }
    });
}

// Update date picker/pickers
function updateDatePicker($element, date, format, triggerChange) {
    // Default values
    triggerChange = (typeof triggerChange == 'undefined') ? false : triggerChange;

    if(calendarType == 'Gregorian') {
        // Update gregorian calendar
        if($element[0]._flatpickr != undefined) {
            if(date == '') {
                $element.val('').trigger('change');
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
            $('#' + $element.attr('id') + 'Link').val('').trigger('change');
        } else {
            // Update jalali calendar
            $('#' + $element.attr('id') + 'Link').data().datepicker.setDate(moment(date, format).unix() * 1000);
        }
    }
}

// Destroy date picker
function destroyDatePicker($element) {
    if(calendarType == 'Gregorian') {
        // Destroy gregorian calendar
        if($element[0]._flatpickr != undefined) {
            $element[0]._flatpickr.destroy();
        }

        // Set value to text field if exists
        if($element.attr('value') != undefined) {
            $element.val($element.attr('value'));
        }
    } else if(calendarType == 'Jalali') {
        // Destroy jalali calendar
        $('#' + $element.attr('id') + 'Link').data().datepicker.destroy();
    }

    // Unbind toggle button click
    $element.parent().find('.date-open-button').off('click');
}

function updateRangeFilter($element, $from, $to, callBack) {
    let value = $element.val();
    let from;
    let to;
    let isCustom = value === 'custom';
    let isPast = value.includes('last');

    if (value === 'agenda') {
        value = 'day';
    }

    if (isCustom) {
        $('.custom-date-range').removeClass('d-none');
    } else {
        $('.custom-date-range').addClass('d-none');

        if (!isPast) {
            from = moment().startOf(value).format(jsDateFormat)
            to = moment().endOf(value).format(jsDateFormat)
        } else {
            let pastValue = value.replace('last', '');
            from = moment().startOf(pastValue).subtract(1, pastValue +'s').format(jsDateFormat)
            to = moment().endOf(pastValue).subtract(1, pastValue +'s').format(jsDateFormat)
        }

        updateDatePicker($from, from, jsDateFormat, true);
        updateDatePicker($to, to, jsDateFormat, true);
    }

    (typeof callBack === 'function') && callBack();
}

function initJsTreeAjax(container, id, isForm, ttl, onReady = null, onSelected = null, onBuildContextMenu = null, plugins = [], homeFolderId = null)
{
    // Default values
    isForm = (typeof isForm == 'undefined') ? false : isForm;
    ttl = (typeof ttl == 'undefined') ? false : ttl;
    var homeNodeId;


    // if there is no modal appended to body and we are on a form that needs this modal, then append it
    if ($('#folder-tree-form-modal').length === 0 && $('#' + id + ' #folderId').length && $('#select-folder-button').length) {
        // compile tree folder modal and append it to Form
        var folderTreeModal = Handlebars.compile($('#folder-tree-template').html());
        var treeConfig = {"container": "container-folder-form-tree", "modal": "folder-tree-form-modal"};

        // append to body, instead of the form as it was before to make it more bootstrap friendly
        $('body').append(folderTreeModal(treeConfig));

        $("#folder-tree-form-modal").on('hidden.bs.modal', function () {
            // Fix for 2nd/overlay modal
            $('.modal:visible').length && $(document.body).addClass('modal-open');
            $(this).data('bs.modal', null);
        });
    }

    var state = {};
    if ($(container).length) {

        // difference here is, that for grid trees we don't set ttl at all
        // add/edit forms have short ttl, multi select will be cached for couple of minutes
        if (isForm) {
            state = {"key" : id + "_folder_tree", "ttl": ttl};
        } else {
            state = {"key" : id + "_folder_tree"}
        }

        $(container).jstree({
            "state" : state,
            "plugins" : ["contextmenu", "state", "unique", "sort", "types", 'search'].concat(plugins),
            "contextmenu":{
                "items": function($node, checkContextMenuPermissions) {
                    // items in context menu need to check user permissions before we render them
                    // as such each click on the node will execute the below ajax to check what permissions user has
                    // permission may be different per node, therefore we cannot look this up just once for whole tree.
                    var items = {};
                    var tree = $(container).jstree(true);
                    var buttonPermissions = null;

                    $.ajax({
                        url: foldersUrl + "/contextButtons/"+$node.id,
                        method: "GET",
                        dataType: "json",
                        success: function (data) {
                            buttonPermissions = data;

                            if (buttonPermissions.create) {
                                items['Create'] = {
                                    "separator_before": false,
                                    "separator_after": false,
                                    "label": translations.folderTreeCreate,
                                    "action": function (obj) {
                                        $node = tree.create_node($node);
                                        tree.edit($node);
                                    }
                                }
                            }

                            if (buttonPermissions.modify) {
                                items['Rename'] = {
                                    "separator_before": false,
                                    "separator_after": false,
                                    "label": translations.folderTreeEdit,
                                    "action": function (obj) {
                                        tree.edit($node);
                                    }
                                };
                            }

                            if (buttonPermissions.delete) {
                                items['Remove'] = {
                                    "separator_before": true,
                                    "separator_after": false,
                                    "label": translations.folderTreeDelete,
                                    "action": function (obj) {
                                        tree.delete_node($node);
                                    }
                                }
                            }

                            if (isForm === false && buttonPermissions.share) {
                                items['Share'] = {
                                    "separator_before": true,
                                    "separator_after": false,
                                    "label": translations.folderTreeShare,
                                    "_class": "XiboFormRender",
                                    "action": function (obj) {
                                        XiboFormRender(permissionsUrl.replace(":entity", "form/Folder/") + $node.id);
                                    }
                                }
                            }

                            if (isForm === false && buttonPermissions.move) {
                                items['Move'] = {
                                    "separator_before": true,
                                    "separator_after": false,
                                    "label": translations.folderTreeMove,
                                    "_class": "XiboFormRender",
                                    "action": function (obj) {
                                        XiboFormRender(foldersUrl + '/form/' + $node.id + '/move');
                                    }
                                }
                            }

                            if (onBuildContextMenu !== null && onBuildContextMenu instanceof Function) {
                                items = onBuildContextMenu($node, items);
                            }
                        },
                        complete: function (data) {
                            checkContextMenuPermissions(items);
                        }
                    });
                }},
            "types" : {
                "root" : {
                    "icon" : "fa fa-file text-xibo-primary"
                },
                "home" : {
                    "icon" : "fa fa-home text-xibo-primary"
                },
                "default" : {
                    "icon" : "fa fa-folder text-xibo-primary"
                },
                "open" : {
                    "icon" : "fa fa-folder-open text-xibo-primary"
                }
            },
            'search' : {
                'show_only_matches' : true
            },
            'core' : {
                "check_callback" : function (operation, node, parent, position, more) {
                    // prevent edit/delete of the root node.
                    if(operation === "delete_node" || operation === "rename_node") {
                        if(node.id === "#" || node.id === "1") {
                            toastr.error(translations.folderTreeError);
                            return false;
                        }
                    }
                    return true;
                },
                'data' : {
                    "url": foldersUrl + (homeFolderId ? '?homeFolderId='+homeFolderId : '')
                },
                "themes" : {
                    'responsive' : true,
                    'dots' : false,
                },
            }
        }).bind('ready.jstree', function(e, data) {
            // depending on the state of folder tree, hide/show as needed when we load the grid page
            if (localStorage.getItem("hideFolderTree") !== undefined &&
                localStorage.getItem("hideFolderTree") !== null &&
                JSON.parse(localStorage.getItem("hideFolderTree")) !== $('#grid-folder-filter').is(":hidden")
            ) {
                adjustDatatableSize(false);
            }
            // if node has children and User does not have suitable permissions, disable the node
            // If node does NOT have children and User does not have suitable permissions, hide the node completely
            $.each(data.instance._model.data, function(index, e) {
                if (e?.original?.type === 'disabled') {
                    var node = $(container).jstree().get_node(e.id);
                    if (e.children.length === 0) {
                        $(container).jstree().hide_node(node);
                    } else {
                        $(container).jstree().disable_node(node);
                    }
                }

                if (e?.original?.isRoot === 1) {
                    $(container).find('a#'+e.id+'_anchor').attr('title', translations.folderRootTitle)
                }

                // get the home folder
                if (e.type !== undefined && e.type === 'home') {
                    homeNodeId = e.id;

                    // check state
                    let currentState = localStorage.getItem(id+'_folder_tree')
                    // if we have no state saved, select the homeFolderId in the tree.
                    if ((currentState === undefined || currentState === null) && !isForm) {
                        $(container).jstree(true).select_node(homeNodeId)
                    }
                }
            });

            // if we are on the form, we need to select tree node (currentWorkingFolder)
            // this is set/passed to twigs on render time
            if (isForm) {
                var folderIdInputSelector = '#'+id+' #folderId';

                // for upload forms
                if ($(folderIdInputSelector).length === 0) {
                    folderIdInputSelector = '#formFolderId';
                }

                let selectedFolder = !$(folderIdInputSelector).val() ? homeNodeId : $(folderIdInputSelector).val();

                if (selectedFolder !== undefined && selectedFolder !== '') {
                    $(this).jstree('select_node', selectedFolder);
                    if ($('#originalFormFolder').length) {
                        $('#originalFormFolder').text($(this).jstree().get_path($(this).jstree("get_selected", true)[0], ' > '));
                    }

                    if ($('#selectedFormFolder').length && folderIdInputSelector === '#formFolderId') {
                        $('#selectedFormFolder').text($(this).jstree().get_path($(this).jstree("get_selected", true)[0], ' > '));
                    }
                }
            }

            if (onReady && onReady instanceof Function) {
                onReady($(container).jstree(true), $(container));
            }
        }).bind("rename_node.jstree", function (e, data) {
            var dataObject = {};
            var folderId  = data.node.id;
            dataObject['text'] = data.text;

            $.ajax({
                url: foldersUrl + "/" + folderId,
                method: "PUT",
                dataType: "json",
                data: dataObject,
                success: function (data) {
                    if (container === '#container-folder-form-tree') {
                        // if we rename node on a form, make sure to refresh the js tree in the grid
                        $('#container-folder-tree').jstree(true).refresh();
                    }
                }
            });
        }).bind("create_node.jstree", function (e, data) {

            var node = data.node;
            node.text = translations.folderNew;

            var dataObject = {};
            dataObject['parentId'] = data.parent;
            dataObject['text'] = data.node.text;

            // when we create a new node, by default it will get jsTree default id
            // we need to change it to the folderId we have in our folder table
            // rename happens just after add, therefore this needs to be set as soon as possible
            $.ajax({
                url: foldersUrl,
                method: "POST",
                dataType: "json",
                data: dataObject,
                success: function (data) {
                    $(container).jstree(true).set_id(node, data.data.id);
                    // if we add a new node on a form, make sure to refresh the js tree in the grid
                    if (container === '#container-folder-form-tree') {
                        $('#container-folder-tree').jstree(true).refresh();
                    }
                },
            });
        }).bind("delete_node.jstree", function (e, data) {

            var dataObject = {};
            dataObject['parentId'] = data.parent;
            dataObject['text'] = data.node.text;
            var folderId = data.node.id;

            // delete has a check built-in, if it fails to remove node, it will show suitable message in toast
            // and reload the tree
            $.ajax({
                url: foldersUrl + "/"+folderId,
                method: "DELETE",
                dataType: "json",
                data: dataObject,
                success: function (data) {
                    if (data.success) {
                        toastr.success(translations.done)
                        // if we delete node on a form, make sure to refresh the js tree in the grid
                        if (container === '#container-folder-form-tree') {
                            $('#container-folder-tree').jstree(true).refresh();
                        }
                    } else {
                        if (data.message !== undefined) {
                            toastr.error(data.message)
                        } else {
                            toastr.error(translations.folderWithContent);
                        }
                        $(container).jstree(true).refresh();
                    }
                }
            });
        }).bind("changed.jstree", function (e, data) {
            var selectedFolderId = data.selected[0];
            var folderIdInputSelector = (isForm) ? '#'+id+' #folderId' : '#folderId';
            var node = $(container).jstree("get_selected", true);

            // for upload and multi select forms.
            if (isForm && $(folderIdInputSelector).length === 0) {
                folderIdInputSelector = '#formFolderId';
            }

            if (selectedFolderId !== undefined && isForm === false) {
                $("#breadcrumbs").text($(container).jstree().get_path(node[0], ' > ')).hide();
                $('#folder-tree-clear-selection-button').prop('checked', false)
            }

            // on grids, depending on the selected folder, we need to handle the breadcrumbs
            if ($(folderIdInputSelector).val() != selectedFolderId && isForm === false) {
                if (selectedFolderId !== undefined) {
                    $(folderIdInputSelector).val(selectedFolderId).trigger('change');
                } else {
                    $("#breadcrumbs").text('');
                    $('#folder-tree-clear-selection-button').prop('checked', true)
                    $('.XiboFilter').find('#folderId').val(null).trigger('change');
                }
            }

            // on form we always want to show the breadcrumbs to current and selected folder
            if (isForm && selectedFolderId !== undefined) {
                $(folderIdInputSelector).val(selectedFolderId).trigger('change');
                if ($('#selectedFormFolder').length) {
                    $('#selectedFormFolder').text($(container).jstree().get_path(node[0], ' > '));
                }
            }

            if (onSelected && onSelected instanceof Function) {
                onSelected(data);
            }
        }).bind("open_node.jstree", function(e, data) {
            if (data.node.type !== 'root' && data.node.type !== 'home') {
                data.instance.set_type(data.node,'open');
            }
        }).bind("close_node.jstree", function(e, data) {
            if (data.node.type !== 'root' && data.node.type !== 'home') {
                data.instance.set_type(data.node, 'default');
            }
        }).bind('search.jstree', function (nodes, str, res) {
            // by default the plugin shows all folders if search does not match anything
            // make it so we hide the tree in such cases,
            if (str.nodes.length === 0) {
                $(container).jstree(true).hide_all();
                $(container).parent().find('.folder-search-no-results').removeClass('d-none')
            } else {
                $(container).parent().find('.folder-search-no-results').addClass('d-none')
            }
        })

        // on froms that have more than one modal active, this is needed to not confuse bootstrap
        // the (X) needs to close just the inner modal
        // clicking outside of the tree select modal will work as well.
        $(".btnCloseInnerModal").on('click', function(e) {
            e.preventDefault();
            var folderTreeModalId = (isForm) ? '#folder-tree-form-modal' : '#folder-tree-modal';
            $(folderTreeModalId).modal('hide');
        });

        // this handler for the search everywhere checkbox on grid pages
        $("#folder-tree-clear-selection-button").on('click', function() {
            if ($("#folder-tree-clear-selection-button").is(':checked')) {
                $(container).jstree("deselect_all");
                $('.XiboFilter').find('#folderId').val(null).trigger('change');
            } else {
                $(container).jstree('select_node', homeNodeId ?? 1)
            }
        });

        // this is handler for the hamburger button on grid pages
        $('#folder-tree-select-folder-button').off("click").on('click', adjustDatatableSize)

        var folderSearch = _.debounce(function () {
            // show all folders, as it might be hidden if previous search returned empty.
            $(container).jstree(true).show_all();
            // for reasons, search event is not triggered on clear/empty search
            // make sure we hide the div with message about no results here.
            $(container).parent().find('.folder-search-no-results').addClass('d-none')
            // search for the folder via entered string.
            $(container).jstree(true).search($(this).val())
        }, 500);
        $('#jstree-search').on('keyup', folderSearch);
        $('#jstree-search-form').on('keyup', folderSearch)
    }

    // Make container resizable
    $('#grid-folder-filter').resizable({
        handles: 'e',
        minWidth: 200,
        maxWidth: 500,
    });
}

function adjustDatatableSize (reload) {
    // Display Map Resize
    function resizeDisplayMap() {
        if (typeof refreshDisplayMap === "function") {
            refreshDisplayMap();
        }
    }

    reload = (typeof reload == 'undefined') ? true : reload;
    // Shrink table to ease animation
    if($('#grid-folder-filter').is(":hidden")) {
        resizeDisplayMap();
    }

    $('#grid-folder-filter').toggle('fast', function() {
        if ($(this).is(":hidden")) {
            if (!$("#folder-tree-clear-selection-button").is(':checked')) {
                // if folder tree is hidden and select everywhere is not checked, then show breadcrumbs
                $("#breadcrumbs").show('slow');
            }
            resizeDisplayMap();
        } else {
            // if the tree folder view is visible, then hide breadcrumbs
            $("#breadcrumbs").hide('slow');
        }

        if (reload) {
            $(this).closest(".XiboGrid").find("table.dataTable").DataTable().ajax.reload();
        }
        // set current state of the folder tree visibility to local storage,
        // this is then used to hide/show the tree when User navigates to a different grid or reloads this page
        localStorage.setItem("hideFolderTree", JSON.stringify($('#grid-folder-filter').is(":hidden")));
    });
}

function disableFolders () {
    // if user does not have Folders feature enabled, then we need to remove couple of elements from the page
    // to prevent jsTree from executing, make the datatable take whole available width as well.
    $('#folder-tree-select-folder-button').parent().remove();
    $('#container-folder-tree').remove();
    $('#grid-folder-filter').remove();
}

/**
 * Create a mini layout preview
 * @param  {string} previewUrl
 */
function createMiniLayoutPreview(previewUrl) {
    // Add element to page if it's not already
    if($('.page-content').find('.mini-layout-preview').length == 0) {
        var miniPlayerTemplate = Handlebars.compile($('#mini-player-template').html());
        $('.page-content').append(miniPlayerTemplate());
    }

    var $layoutPreview = $('.mini-layout-preview');
    var $layoutPreviewContent = $layoutPreview.find('#content');

    // Create base template for preview content
    var previewTemplate = Handlebars.compile('<iframe scrolling="no" src="{{url}}" width="{{width}}px" height="{{height}}px" style="border:0;"></iframe>');

    // Clean all selected elements
    $layoutPreviewContent.html('');

    // Handle buttons
    $layoutPreview.find('#playBtn').show().off().on('click', function() {
        // Hide button
        $(this).hide();

        // Load and start preview
        $layoutPreview.find('#content').append(previewTemplate({
            url: previewUrl,
            width: $layoutPreview.hasClass('large') ? '760' : '440',
            height: $layoutPreview.hasClass('large') ? '420' : '240'
        }));
    });

    $layoutPreview.find('#closeBtn').off().on('click', function() {
        // Close preview and empty content
        $layoutPreview.find('#content').html('');
        $layoutPreview.removeClass('show');
        $layoutPreview.remove();
    });

    $layoutPreview.find('#newTabBtn').off().on('click', function() {
        // Open preview in new tab
        window.open(previewUrl,'_blank');
    });

    $layoutPreview.find('#sizeBtn').off().on('click', function() {
        // Empty content
        $layoutPreview.find('#content').html('');

        // Toggle size class
        $layoutPreview.toggleClass('large');

        // Change icon based on size state
        $(this).toggleClass('fa-arrow-circle-down', $layoutPreview.hasClass('large'));
        $(this).toggleClass('fa-arrow-circle-up', !$layoutPreview.hasClass('large'));
        // Re-show play button
        $layoutPreview.find('#playBtn').show();
    });

    // Show layout preview element
    $layoutPreview.addClass('show');
}

/**
 * https://stackoverflow.com/questions/15900485/correct-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript
 * @param {number} size
 * @param {number} precision
 * @returns {string}
 */
function formatBytes(size, precision){
    if (size === 0) {
        return "0 Bytes";
    }

    const c=0 > precision ? 0 : precision, d = Math.floor(Math.log(size)/Math.log(1024));
    return parseFloat((size/Math.pow(1024,d)).toFixed(c))+" "+["Bytes","KB","MB","GB","TB","PB","EB","ZB","YB"][d]
}

/**
 * Create bootstrap colorpicker
 * @param {object} element jquery object or CSS selector
 * @param {object} options bootstrap-colorpicker options (https://itsjavi.com/bootstrap-colorpicker/v2/)
 */
function createColorPicker(element, options) {
    var $self = $(element);

    // Disable autocomplete
    $self.attr('autocomplete', 'off');

    $self.colorpicker(Object.assign({
        format: "hex"
    }, options));
}

/**
 * Destroy bootstrap colorpicker
 * @param {object} element jquery object or CSS selector
 */
 function destroyColorPicker(element) {
    var $self = $(element);

    $self.colorpicker('destroy');
}

