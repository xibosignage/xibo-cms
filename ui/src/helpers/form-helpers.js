// Include templates
const templates = {
    dataSetOrderClauseTemplate: require("../templates/form-helpers-data-set-order-clause.hbs"),
    dataSetFilterClauseTemplate: require('../templates/form-helpers-data-set-filter-clause.hbs'),
    subPlaylistFormTemplate: require('../templates/form-helpers-sub-playlist-form.hbs'),
    twittermetroColorsTemplate: require('../templates/form-helpers-twitter-metro-colors.hbs'),
    chartColorsTemplate: require('../templates/form-helpers-chart-colors.hbs'),
    chartGraphConfigTemplate: require('../templates/form-helpers-chart-graph-config.hbs')
};

let formHelpers = function() {

    // Default params ( might change )
    this.defaultBackgroundColor = '#111';

    this.defaultRegionDimensions = {
        width: 1920,
        height: 1080,
        scale: 1
    };

    /**
     * Set helpers to work with the tool that is using it
     * @param {object} namespace - Helper namespace
     * @param {string} mainObject - Helper main object
     */
    this.setup = function(namespace, mainObject) {        
        this.namespace = namespace;
        this.mainObject = mainObject;
    };

    /**
     * Use passed main checkbox object's value (checkBoxSelector) to toggle the secondary passed fields (inputFieldsSelector OR inputFieldsSelectorOpposite) inside the form
     * @param {object} form - Form object
     * @param {string} checkBoxSelect - CSS selector for the checkbox object
     * @param {string} inputFieldsSelector - CSS selector for the input fields to toggle ( show on checked, hide on unchecked)
     * @param {string=} inputFieldsSelectorOpposite - CSS selector for the input fields that behave diferently from the select fields on previous param ( hide on checked, show on unchecked)
     */
    this.setupCheckboxInputFields = function(form, checkBoxSelector, inputFieldsSelector, inputFieldsSelectorOpposite) {
        const checkboxObj = $(form).find(checkBoxSelector);
        const inputFieldsObj = $(form).find(inputFieldsSelector);
        const inputFieldsObjOpposite = $(form).find(inputFieldsSelectorOpposite);

        const displayInputFields = function() {
            // Init
            if(checkboxObj.is(':checked') == false) {
                inputFieldsObj.css('display', 'none');
                inputFieldsObjOpposite.css('display', 'block');
            } else if(checkboxObj.is(':checked') == true) {
                inputFieldsObj.css('display', 'block');
                inputFieldsObjOpposite.css('display', 'none');
            }

        };

        // Init
        displayInputFields();

        // Change
        checkboxObj.on('change', displayInputFields);
    };

    /**
     * Use passed main input object's value (inputValueSelector) to toggle the secondary passed fields (inputFieldsArray) inside the form
     * @param {object} form - Form object
     * @param {string} inputValueSelector - CSS selector for the input field that triggers the "change" and "input" events
     * @param {Array.<string>} inputFieldsArray - Array of CSS selector for the input fields to be compared with the values to be toggled
     * @param {Array.<>} customIndexValues - Array of values to compare to the inputFieldsArray, if it matches, the field will be shown/hidden according to the inverted flag state
     * @param {bool=} inverted - Use hide element instead of show just element ( default )
     */
    this.setupObjectValueInputFields = function(form, inputValueSelector, inputFieldsArray, customIndexValues = null, inverted = false, customTarget = null) {

        const elementClass = (!inverted) ? 'block' : 'none';
        const inverseClass = (!inverted) ? 'none' : 'block';

        const inputValueField = $(form).find(inputValueSelector);

        const displayInputFields = function() {

            let inputValue = inputValueField.val();

            // Hide/show all fields first
            for(let index = 0;index < inputFieldsArray.length;index++) {
                const element = $(form).find(inputFieldsArray[index]);

                $(element).css('display', inverseClass);
            }

            // If there is a custom target for the marked fields
            if(customTarget != null) {
                form = customTarget;
            }

            // Hide/Show only the marked ones
            for(let index = 0;index < inputFieldsArray.length;index++) {
                const element = $(form).find(inputFieldsArray[index]);

                let currentIndex = index;

                if(customIndexValues != null) {
                    currentIndex = customIndexValues[index];
                }

                if(currentIndex == inputValue) {
                    $(element).css('display', elementClass);
                }
            }
        };

        // Init
        displayInputFields();

        // Change
        inputValueField.on('change input', displayInputFields);
    };

    /**
     * Append an error message on form ( create or update a previously created one )
     * @param {object} form - Form object that contains one object with id = "errorMessage"
     * @param {string} message- Message to be displayed
     * @param {string} type - Type of message (Bootstrap Alert: success, danger, info, warning)
     */
    this.displayErrorMessage = function(form, message, type) {

        if($(form).find('#errorMessage').length) {
            // Replace message in form error
            $(form).find('#errorMessage p').html(message);
        } else {
            // Build message html and append to form
            let html = '';
            html += '<div id="errorMessage" class="alert alert-' + type + '"><div class="row"><div class="col-sm-12 ">';
            html += '<p>' + message + '</p>';
            html += '</div></div></div>';

            // Append message to the form
            $(form).append(html);
        }

    };

    /**
     * Fill a tab with the ajax request information and then switch to that tab
     * @param {string} tabName - Tab name
     * @param {string} url- Request url
     */
    this.requestTab = function(tabName, url) {
        $.ajax({
            type: "get",
            url: url,
            cache: false,
            data: "tab=" + tabName,
            success: function(response, status, xhr) {
                $(".tab-content #" + tabName).html(response.html);

                $('.nav-tabs a[href="#' + tabName + '"]').tab('show');
            }
        });
    };

    /**
    * Setup form with a CKEditor and AdvancedEditor
    * @param {object} dialog - Dialog object ( the object that contains the replaceable fields )
    * @param {callback=} callbackFunction - Function called when override template or advance editor is changed
    * @param {string=} textAreaName - Textarea editor, if exists
    */
    this.setupForm = function(dialog, callbackFunction = null, textAreaName = null) {
        
        const self = this;

        // Get extra data
        var data = $(dialog).data().extra;

        // Function to apply template contents to form
        var applyTemplateContentIfNecessary = function(data) {

            // Apply content only if override template is on
            if($("#overrideTemplate", dialog).is(":checked")) {

                // Get the currently selected templateId
                var templateId = $("#templateId").val();

                // To update text area value, we need to destroy the editor if there's one attached to it
                let textAreaId = '';
                if(textAreaName != null) {
                    textAreaId = dialog.find('textarea[name="' + textAreaName + '"]').attr('id');
                    self.destroyCKEditor(textAreaId);
                }

                let templates = data;

                // Fix for modules with templates as a param of data
                if(data.templates !== undefined) {
                    templates = data.templates;
                }

                // Parse each field
                $.each(templates, function(index, value) {
 
                    if(value.id == templateId) {

                        // Update html and css on the form
                        if(textAreaName != null) {
                            $('#' + textAreaId, dialog).val(value.template);
                        }
                        
                        $('#ta_css', dialog).val(value.css);

                        // Check/uncheck advanced editor input if available
                        if(value.advancedEditor != undefined) {
                            $('#advancedEditor', dialog).prop("checked", value.advancedEditor);
                        }

                        // Go through each other property
                        $.each(value, function(key, value) {

                            if(key != "template" && key != "css" && key != "advancedEditor") {
                                // Try to match a advancedEditorfield
                                $("#" + key, dialog).val(value);
                            }
                        });
                    }
                });
            }
        };

        var initialiseTextareaSnippets  = function(snippets) {

            if(textAreaName != null) {

                const textAreaId = dialog.find('textarea[name="' + textAreaName + '"]').attr('id');
                

                // Apply content only if advanced editor is off
                if(!$("#advancedEditor", dialog).is(":checked")) {

                    if(snippets.length > 0) {

                        snippets.select2().off().on('select2:select', function(e) {
                            let linkedTo = $(this).data().linkedTo;
                            let text;
                            let target = $('#' + linkedTo, dialog);

                            if(target.length > 0) {
                                text = "[" + e.params.data.element.value + "]";

                                let cursorPosition = target[0].selectionStart;
                                let previousText = target.val();
                                
                                $('#' + linkedTo, dialog).val(previousText.substring(0, cursorPosition) + text + previousText.substring(cursorPosition));
                            }

                            // Reset selector
                            $(this).val('').trigger('change');
                        });
                    }
                }
            }
        };

        // Register an onchange listener to manipulate the template content if the selector is changed.
        $('#overrideTemplate', dialog).on('change', function() {

            applyTemplateContentIfNecessary(data);

            if(callbackFunction !== null) {
                callbackFunction();
            }
        });

        // Register an onchange listener to callback function on advancedEditor checkbox change
        $('#advancedEditor', dialog).on('change', function() {

            initialiseTextareaSnippets($('.ckeditor_snippets_select', dialog));

            if(callbackFunction !== null) {
                callbackFunction();
            }
        });

        // Run initialiseTextareaSnippets if checkbox advanced starts as false
        initialiseTextareaSnippets($('.ckeditor_snippets_select', dialog));
    };

    /**
     * Create a CKEDITOR instance to conjure a text editor
     * @param {object} dialog - Dialog object ( the object that contains the replaceable fields )
     * @param {object} extraData - Extra data
     * @param {string} textAreaName - Name of the text area to use for the editor
     * @param {bool=} inline - Inline editor option
     * @param {string=} customNoDataMessage - Custom message to appear when the field is empty
     */
    this.setupCKEditor = function(dialog, extraData, textAreaName, inline = false, customNoDataMessage = null) {

        // Get form text area Id by name
        let textAreaId = dialog.find('textarea[name="' + textAreaName + '"]').attr('id');

        // Stop here if there's no text area element
        if(textAreaId === undefined) {
            return;
        }

        // Get extra data
        var extra = extraData;

        if(extraData === undefined || extraData === null) {
            extra = $(dialog).data().extra;
        }

        // COLORS
        // Background color for the editor
        var backgroundColor = (typeof this.mainObject.backgroundColor != 'undefined') ? this.mainObject.backgroundColor : this.defaultBackgroundColor;
        // Choose a complementary color
        var color = $c.complement(backgroundColor);
        
        // DIMENSIONS
        var region = {};

        // Get region dimensions
        if(this.namespace.selectedObject.type == 'widget') {
            region = this.namespace.getElementByTypeAndId('region', this.namespace.selectedObject.regionId);
        } else {
            region = this.namespace.getElementByTypeAndId('region', this.namespace.selectedObject.id);
        }

        let regionDimensions = null;
        let scale = 1;
        const iframeMargin = 10;
        const iframeBorderWidth = 2;

        // Calculate dimensions
        if(region.dimensions === undefined) { // Without region

            regionDimensions = this.defaultRegionDimensions;

            // Display controls
            $(dialog).find('.form-editor-controls').show();

            // Dimensions
            ['width', 'height'].forEach((dimension) => {
                // Get or set the value
                if($.isNumeric($(dialog).find('.text_editor_' + dimension).val())) {
                    regionDimensions[dimension] = parseFloat($(dialog).find('.text_editor_' + dimension).val());
                } else {
                    $(dialog).find('.text_editor_' + dimension).val(regionDimensions[dimension]);
                }

                // Handle input change
                $(dialog).find('.text_editor_' + dimension).focusout(() => {

                    // If the value was updated
                    if($(dialog).find('.text_editor_' + dimension).val() != regionDimensions[dimension]) {
                        // Destroy and rebuild the CKEDITOR
                        this.destroyCKEditor();
                        this.setupCKEditor(dialog, extra, textAreaName);
                    }
                    
                });
            });

            // Scale
            $(dialog).find('.text_editor_scale').off().change(() => {
                // Destroy and rebuild the CKEDITOR
                this.destroyCKEditor();
                this.setupCKEditor(dialog, extra, textAreaName);
            });

            // Calculate scale based on the form text area ( if scale checkbox check is true)
            if($(dialog).find('.text_editor_scale').is(':checked')) {

                // Inner width and a padding for the scrollbar
                let width = $(dialog).find('form').innerWidth() - 30;

                // Element side plus margin
                let elementWidth = regionDimensions.width + (iframeMargin * 2);
                scale = width / elementWidth;
            }

        } else {
            // If region dimensions are defined, use them as base for the editor
            regionDimensions = region.dimensions;

        // Calculate scale based on the region previewed in the viewer
            scale = this.namespace.viewer.containerElementDimensions.width / regionDimensions.width;
        }

        var applyContentsToIframe = function(field) {

            if(inline) {
                // Inline editor div tweaks to make them behave like the iframe rendered content
                $(".cke_textarea_inline").css('width', regionDimensions.width);
                $(".cke_textarea_inline").css('height', regionDimensions.height);
                $(".cke_textarea_inline").css('transform', 'scale(' + scale + ')');
                $(".cke_textarea_inline").css('transform-origin', '0 0');
                $(".cke_textarea_inline").css('word-wrap', 'inherit');
                $(".cke_textarea_inline").css('overflow', 'hidden');
                $(".cke_textarea_inline").css('line-height', 'normal');
                $(".cke_textarea_inline p").css('margin', '0 0 16px');
                $(".cke_textarea_inline").show();
            } else {
                $("#cke_" + field + " iframe").contents().find("head").append("" +
                    "<style>" +
                    "html { height: 100%; " +
                    "}" +
                    "body {" +
                    "width: " + regionDimensions.width + "px; " +
                    "height: " + regionDimensions.height + "px; " +
                    "border: " + iframeBorderWidth + "px solid red; " +
                    "background: " + backgroundColor + "; " +
                    "transform: scale(" + scale + "); " +
                    "margin: " + iframeMargin + "px; " +
                    "word-wrap: inherit; " +
                    "transform-origin: 0 0; }" +
                    "h1, h2, h3, h4, p { margin-top: 0;}" +
                    "</style>");
            }
        };

        var convertLibraryReferences = function(data) {
            // We need to convert any library references [123] to their full URL counterparts
            // we leave well alone non-library references.
            var regex = /\[[0-9]+]/gi;

            data = data.replace(regex, function(match) {
                var inner = match.replace("]", "").replace("[", "");
                return CKEDITOR_DEFAULT_CONFIG.imageDownloadUrl.replace(":id", inner);
            });

            return data;
        };

        // Set CKEDITOR viewer height based on region height ( plus content default margin + border*2: 40px )
        const newHeight = (regionDimensions.height * scale) + (iframeMargin * 2);
        CKEDITOR.config.height = (newHeight > 500) ? 500 : newHeight;

        // Conjure up a text editor
        if(inline) {
            CKEDITOR.inline(textAreaId, CKEDITOR_DEFAULT_CONFIG);
        } else {
            CKEDITOR.replace(textAreaId, CKEDITOR_DEFAULT_CONFIG);
        }
        
        // Bind to instance ready so that we can adjust some things about the editor.
        CKEDITOR.instances[textAreaId].on('instanceReady', function() {

            // If not defined, cancel instance setup
            if(CKEDITOR.instances[textAreaId] === undefined) {
                return;
            }
            
            // Apply scaling to this editor instance
            applyContentsToIframe(textAreaId);

            // Reapply the background style after switching to source view and back to the normal editing view
            CKEDITOR.instances[textAreaId].on('contentDom', function() {applyContentsToIframe(textAreaId)});

            // Get the template data from the text area field
            var data = $('#' + textAreaId).val();

            // Replace color if exists
            if(data != undefined) {
                data = data.replace(/#Color#/g, color);
            }
                        
            // Handle no message data
            if(data == "") {

                let dataMessage = '';

                if(textAreaId === 'noDataMessage') {
                    dataMessage = translations.noDataMessage;
                } else if(customNoDataMessage !== null) {
                    dataMessage = customNoDataMessage;
                } else {
                    dataMessage = translations.enterText;
                }

                data = "<span style=\"font-size: 48px;\"><span style=\"color: " + color + ";\">" + dataMessage + "</span></span>";
            }

            // Handle initial template set up
            data = convertLibraryReferences(data);

            CKEDITOR.instances[textAreaId].setData(data);
        });

        // Do we have any snippets selector?
        var $selectPickerSnippets = $('.ckeditor_snippets_select', dialog);
            // Select2 has been initialized
        if($selectPickerSnippets.length > 0) {

            $selectPickerSnippets.select2().off().on('select2:select', function(e) {
                var linkedTo = $(this).data().linkedTo;
                var text;
                
                if(CKEDITOR.instances[linkedTo] != undefined) {
                    text = "[" + e.params.data.element.value + "]";

                    CKEDITOR.instances[linkedTo].insertText(text);
                }

                // Reset selector
                $(this).val('').trigger('change');
            });
        }

        // Do we have a media selector?
        var $selectPicker = $(".ckeditor_library_select", dialog);
        if($selectPicker.length > 0) {
            $selectPicker.select2({
                ajax: {
                    url: $selectPicker.data().searchUrl,
                    dataType: "json",
                    data: function(params) {
                        var query = {
                            media: params.term,
                            type: "image",
                            start: 0,
                            length: 10
                        };

                        // Set the start parameter based on the page number
                        if(params.page != null) {
                            query.start = (params.page - 1) * 10;
                        }

                        // Find out what is inside the search box for this list, and save it (so we can replay it when the list
                        // is opened again)
                        if(params.term !== undefined) {
                            localStorage.liveSearchPlaceholder = params.term;
                        }

                        return query;
                    },
                    processResults: function(data, params) {
                        var results = [];

                        $.each(data.data, function(index, element) {
                            results.push({
                                "id": element.mediaId,
                                "text": element.name,
                                'imageUrl': $selectPicker.data().imageUrl.replace(':id', element.mediaId),
                                'disabled': false
                            });
                        });

                        var page = params.page || 1;
                        page = (page > 1) ? page - 1 : page;

                        return {
                            results: results,
                            pagination: {
                                more: (page * 10 < data.recordsTotal)
                            }
                        }
                    },
                    delay: 250
                }
            }).off().on('select2:select', function(e) {
                var linkedTo = $(this).data().linkedTo;
                var value = e.params.data.imageUrl;

                if(value !== undefined && value !== "" && linkedTo != null) {
                    if(CKEDITOR.instances[linkedTo] != undefined) {
                        CKEDITOR.instances[linkedTo].insertHtml("<img src=\"" + value + "\" />");
                    }
                }
                                // Reset selector
                $(this).val('').trigger('change');
            });
        }

        // Turn the background colour into a picker
        $("#backgroundColor").colorpicker();

        return false;
    };
    
    /**
     * Restart CKEDITOR instance
     */
    this.restartCKEditor = function(dialog, textAreaName) {

        // Get form text area Id by name
        let textAreaId = dialog.find('textarea[name="' + textAreaName + '"]').attr('id');

        this.destroyCKEditor(textAreaId);
        this.setupCKEditor(dialog, null, textAreaName);
    };

    /**
     * Update text callback CKEDITOR instance
     */
    this.updateCKEditor = function(instance) {

        try {
            // Update specific instance
            if(instance != undefined && CKEDITOR.instances[instance] != undefined) {
                CKEDITOR.instances[instance].updateElement();
            } else {
                $.each(CKEDITOR.instances, function(index, value) {
                    CKEDITOR.instances[index].updateElement();
                });
            }
        } catch(e) {
            console.warn("Unable to update CKEditor instances. " + e);
        }
    };

    /**
     * Destroy text callback CKEDITOR instance
     */
    this.destroyCKEditor = function(instance) {
        
        // Make sure when we close the dialog we also destroy the editor
        try {
            if(instance === undefined) {
                // Destroy all instances
                $.each(CKEDITOR.instances, function(index, value) {
                    CKEDITOR.instances[index].destroy();
                });
            } else {
                // Destroy specific instance
                if(CKEDITOR.instances[instance] != undefined) {
                    CKEDITOR.instances[instance].destroy();
                }
            }

        } catch(e) {
            console.warn("Unable to remove CKEditor instance. " + e);
            CKEDITOR.instances = {};
        }
    };

    /**
     * Create and attach a Replace button, and open a upload form on click to replace media
     * @param {object} dialog - Dialog object
     */
    this.mediaEditFormOpen = function(dialog) {
        
        const self = this;

        if(dialog.find('form').data().mediaEditable != 1)
            return;

        // Create a new button
        var footer = dialog.find('.button-container');
        var mediaId = dialog.find('form').data().mediaId;
        var widgetId = dialog.find('form').data().widgetId;
        var validExtensions = dialog.find('form').data().validExtensions;
        
        // Append
        var replaceButton = $('<button class="btn btn-warning">').html(playlistAddFilesTrans.uploadMessage);
        replaceButton.click(function(e) {
            e.preventDefault();

            // Open the upload dialog with our options.
            self.namespace.openUploadForm(
                {
                    oldMediaId: mediaId,
                    widgetId: widgetId,
                    updateInAllChecked: uploadFormUpdateAllDefault,
                    trans: playlistAddFilesTrans,
                    upload: {
                        maxSize: $(this).data().maxSize,
                        maxSizeMessage: $(this).data().maxSizeMessage,
                        validExtensionsMessage: translations.validExtensions + ': ' + dialog.find('form').data().validExtensions,
                        validExt: validExtensions
                    }
                }, {
                    main: {
                        label: translations.done,
                        className: 'btn-primary',
                        callback: function() {
                            if(typeof self.namespace.timeline.resetZoom === 'function') {
                                self.namespace.timeline.resetZoom();
                            }
                            
                            self.namespace.reloadData(self.mainObject);
                        }
                    }
                }
            );
        });

        footer.find('#save').before(replaceButton);
    };

    /**
     * Configure the query builder ( order and filter )
     * @param {object} dialog - Dialog object
     * @param {object} translations - Object with all the translations
     */
    this.configureQueryBuilder = function(dialog, translations) {

        // Order Clause
        var orderClauseFields = $("#orderClause");

        if(orderClauseFields.length == 0)
            return;
        
        var orderClauseTemplate = templates.dataSetOrderClauseTemplate;

        var ascTitle = translations.ascTitle;
        var descTitle = translations.descTitle;

        if(dialog.data().extra.orderClause.length == 0) {
            // Add a template row
            var context = {columns: dialog.data().extra.columns, title: "1", orderClause: "", orderClauseAsc: "", orderClauseDesc: "", buttonGlyph: "fa-plus", ascTitle: ascTitle, descTitle: descTitle};
            orderClauseFields.append(orderClauseTemplate(context));
        } else {
            // For each of the existing codes, create form components
            var i = 0;
            $.each(dialog.data().extra.orderClause, function(index, field) {
                i++;

                var direction = (field.orderClauseDirection == "ASC");

                var context = {columns: dialog.data().extra.columns, title: i, orderClause: field.orderClause, orderClauseAsc: direction, orderClauseDesc: !direction, buttonGlyph: ((i == 1) ? "fa-plus" : "fa-minus"), ascTitle: ascTitle, descTitle: descTitle};

                orderClauseFields.append(orderClauseTemplate(context));
            });
        }

        // Nabble the resulting buttons
        orderClauseFields.on("click", "button", function(e) {
            e.preventDefault();

            // find the gylph
            if($(this).find("i").hasClass("fa-plus")) {
                var context = {columns: dialog.data().extra.columns, title: orderClauseFields.find('.form-group').length + 1, orderClause: "", orderClauseAsc: "", orderClauseDesc: "", buttonGlyph: "fa-minus", ascTitle: ascTitle, descTitle: descTitle};
                orderClauseFields.append(orderClauseTemplate(context));
            } else {
                // Remove this row
                $(this).closest(".form-group").remove();
            }
        });

        //
        // Filter Clause
        //
        var filterClauseFields = $("#filterClause");
        var filterClauseTemplate = templates.dataSetFilterClauseTemplate;
        var filterOptions = translations.filterOptions;
        var filterOperatorOptions = translations.filterOperatorOptions;

        if(dialog.data().extra.filterClause.length == 0) {
            // Add a template row
            var context2 = {
                columns: dialog.data().extra.columns,
                filterOptions: filterOptions,
                filterOperatorOptions: filterOperatorOptions,
                title: "1",
                filterClause: "",
                filterClauseOperator: "AND",
                filterClauseCriteria: "",
                filterClauseValue: "",
                buttonGlyph: "fa-plus"
            };
            filterClauseFields.append(filterClauseTemplate(context2));
        } else {
            // For each of the existing codes, create form components
            var j = 0;
            $.each(dialog.data().extra.filterClause, function(index, field) {
                j++;

                var context2 = {
                    columns: dialog.data().extra.columns,
                    filterOptions: filterOptions,
                    filterOperatorOptions: filterOperatorOptions,
                    title: j,
                    filterClause: field.filterClause,
                    filterClauseOperator: field.filterClauseOperator,
                    filterClauseCriteria: field.filterClauseCriteria,
                    filterClauseValue: field.filterClauseValue,
                    buttonGlyph: ((j == 1) ? "fa-plus" : "fa-minus")
                };

                filterClauseFields.append(filterClauseTemplate(context2));
            });
        }

        // Nabble the resulting buttons
        filterClauseFields.on("click", "button", function(e) {
            e.preventDefault();

            // find the gylph
            if($(this).find("i").hasClass("fa-plus")) {
                var context = {
                    columns: dialog.data().extra.columns,
                    filterOptions: filterOptions,
                    filterOperatorOptions: filterOperatorOptions,
                    title: filterClauseFields.find('.form-group').length + 1,
                    filterClause: "",
                    filterClauseOperator: "AND",
                    filterClauseCriteria: "",
                    filterClauseValue: "",
                    buttonGlyph: "fa-minus"
                };
                filterClauseFields.append(filterClauseTemplate(context));
            } else {
                // Remove this row
                $(this).closest(".form-group").remove();
            }
        });
    };

    /**
     * Get pre-built template
     * @param {object} templateName - Template name
     * @returns {object} Template object
     */
    this.getTemplate = function(templateName) {
        if(templates[templateName] === undefined) {
            console.error('Template ' + templateName + ' does not exist on formHelpers file!')
        }

        return templates[templateName];
    };
};

module.exports = new formHelpers();