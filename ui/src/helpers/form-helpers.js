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
    this.defaultBackgroundColor = '#eee';

    this.defaultRegionDimensions = {
        width: 800,
        height: 600,
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
     * Use a callback to toggle a selector visibility
     * @param {jQuery} triggerFields - jQuery element for the input field that triggers the "change" and "input" events
     * @param {jQuery} targetFields - jQuery element(s) for the input fields to be compared with the values to be toggled
     * @param {*} compareValue - value to be used to compare with the trigger input
     * @param {function} test - Function to test the condition (a,b)
     */
    this.setupConditionalInputFields = function(triggerFields, targetFields, compareValue, test) {

        /**
         * Check test and toggle visibility
         */
        const checkTestAndApply = function() {
            targetFields.toggle(test(compareValue));
        };

        // Init
        checkTestAndApply();

        // Change
        triggerFields.on('change input', checkTestAndApply);
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
    * Setup dual type text area (advanced and simple modes)
    * @param {object} dialog - Dialog object ( the object that contains the replaceable fields )
    * @param {string} textAreaID - text area ID - Snippets selector
    * @param {boolean=} inlineEditor - Use the text area as inline editor
    * @param {string=} customNoDataMessage - Custom message to appear when the field is empty
    */
    this.setupDualTypeTextArea = function(dialog, textAreaID, inlineEditor = false, customNoDataMessage = null) {

        // Get disable checkbox
        const self = this;
        const $advancedEditorOption = $('#' + textAreaID + '_advanced', dialog);
        
        const setupDualEditor = function(dialog, textAreaID) {

            // Inline editor
            if(inlineEditor) {
                
                $(dialog).data().viewerObject.setupInlineEditor(textAreaID, $advancedEditorOption.is(":checked"));

                if(!$advancedEditorOption.is(":checked")) {
                    // Setup text area snippets
                    self.setupTextArea(dialog, textAreaID, customNoDataMessage);
                }
            } else { // Form editor
                if($advancedEditorOption !== undefined && !$advancedEditorOption.is(":checked")) {

                    // Toggle elements visibility
                    dialog.find('.' + textAreaID + '-advanced-editor-show').hide();
                    dialog.find('.' + textAreaID + '-advanced-editor-hide').show();

                    // Destroy CKEditor if exists
                    self.destroyCKEditor(textAreaID);

                    // Try to hide the dimension controls
                    self.setupFormDimensionControls(dialog, false, textAreaID);

                    // Setup text area snippets
                    self.setupTextArea(dialog, textAreaID, customNoDataMessage);
                } else {
                    // Toggle elements visibility
                    dialog.find('.' + textAreaID + '-advanced-editor-show').show();
                    dialog.find('.' + textAreaID + '-advanced-editor-hide').hide();

                    self.setupCKEditor(dialog, null, textAreaID, false, customNoDataMessage);
                }
            }
        };
        // Register an onchange listener to callback function on advanced editor checkbox change if exists
        $advancedEditorOption.bootstrapSwitch().on('switchChange.bootstrapSwitch', function(event, state) {
            setupDualEditor(dialog, textAreaID);
        });

        setupDualEditor(dialog, textAreaID);
    };

    /**
    * Setup form textarea with text/library snippets and no data message
    * @param {object} dialog - Dialog object ( the object that contains the replaceable fields )
    * @param {string} textAreaId - text area ID - Snippets selector
    * @param {string=} customNoDataMessage - Custom message to appear when the field is empty
    */
    this.setupTextArea = function(dialog, textAreaId, customNoDataMessage = null) {
        
        // Get select elements
        const $snippets = $('.ckeditor_snippets_select[data-linked-to="' + textAreaId + '"]', dialog);
        const $mediaSelector = $('.ckeditor_library_select[data-linked-to="' + textAreaId + '"]', dialog);

        // Setup Snippets
        if($snippets.length > 0) {
            
            this.setupSnippetsSelector($snippets, function(e) {
                let linkedTo = $snippets.data().linkedTo;
                let target = $('#' + linkedTo, dialog);
                let value = e.params.data.element.value;

                if(target.length > 0 && value !== undefined) {
                    let text = "[" + value + "]";
                    let cursorPosition = target[0].selectionStart;
                    let previousText = target.val();

                    $('#' + linkedTo, dialog).val(previousText.substring(0, cursorPosition) + text + previousText.substring(cursorPosition));
                }
            });
        }

        // Setup Media
        if($mediaSelector.length > 0) {
            this.setupMediaSelector($mediaSelector, function(e) {
                var linkedTo = $mediaSelector.data().linkedTo;
                var value = e.params.data.id;
                let target = $('#' + linkedTo, dialog);

                if(target.length > 0 && value !== undefined) {
                    let text = '<img src="[' + value + ']"/>';
                    let cursorPosition = target[0].selectionStart;
                    let previousText = target.val();

                    $('#' + linkedTo, dialog).val(previousText.substring(0, cursorPosition) + text + previousText.substring(cursorPosition));
                }
            });
        }
        
        // Handle no message data
        if($('#' + textAreaId, dialog).val() == "") {

            // Background color from the mainObject
            var backgroundColor = (this.mainObject != undefined && typeof this.mainObject.backgroundColor != 'undefined' && this.mainObject.backgroundColor != null) ? this.mainObject.backgroundColor : this.defaultBackgroundColor;

            // Choose a complementary color
            var color = $c.complement(backgroundColor);
            
            let dataMessage = '';

            if(textAreaId === 'noDataMessage') {
                dataMessage = translations.noDataMessage;
            } else if(customNoDataMessage !== null) {
                dataMessage = customNoDataMessage;
            } else {
                dataMessage = translations.enterText;
            }

            $('#' + textAreaId, dialog).val('<span style="font-size: 48px; color:' + color + ';">' + dataMessage + '</span>');
        }
    };

    /**
     * Setup the snippets' selector
     * @param {object} selector - DOM select object
     * @param {function} callback - A function to run after setting the select2 instance 
     */
    this.setupSnippetsSelector = function(selector, callback) {
        selector.select2().off().on('select2:select', function(e) {

            // Call callback
            callback(e);

            // Reset selector
            $(this).val('').trigger('change');
        });
    };

    /**
     * Setup the library/media selector
     * @param {object} selector - DOM select object
     * @param {function} callback - A function to run after setting the select2 instance 
     */
    this.setupMediaSelector = function(selector, callback) {

        selector.select2({
            ajax: {
                url: selector.data().searchUrl,
                dataType: "json",
                data: function(params) {
                    var queryText = params.term;
                    var queryTags = '';

                    // Tags
                    if(params.term != undefined) {
                        var tags = params.term.match(/\[([^}]+)\]/);
                        if(tags != null) {
                            // Add tags to search
                            queryTags = tags[1];

                            // Replace tags in the query text
                            queryText = params.term.replace(tags[0], '');
                        }

                        // Remove whitespaces and split by comma
                        queryText = queryText.replace(' ', '');
                        queryTags = queryTags.replace(' ', '');
                    }

                    var query = {
                        media: queryText,
                        tags: queryTags,
                        type: "image",
                        retired: 0,
                        assignable: 1,
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
                            'imageUrl': selector.data().imageUrl.replace(':id', element.mediaId),
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
                    };
                },
                delay: 250
            }
        }).off().on('select2:select', function(e) {

            callback(e);

            // Reset selector
            $(this).val('').trigger('change');
        });
    };

       /**
     * Setup the library/media selector
     * @param {object} dialog - Dialog object ( the object that contains the overwrittable fields )
     * @param {string} triggerSelector - Overwrite Trigger object jquery selector
     * @param {string} templateFieldSelector - Selected template object jquery selector
     * @param {object} targetsObject - Object containining pairs of selctors for form fields and respective template replacements
     */
    this.setupTemplateOverriding = function(dialog, triggerSelector, templateFieldSelector, targetsObject) {

        // Get extra data
        var data = $(dialog).data().extra;

        const $trigger = $(triggerSelector, dialog);

        // Function to apply template contents to form
        const applyTemplateContentIfNecessary = function(data) {

            // Apply content only if override template is on
            if($trigger.is(":checked")) {

                // Get the currently selected templateId
                const templateId = $(templateFieldSelector, dialog).val();

                // Get available templates
                let templates = data;

                if(data.templates !== undefined) { // Fix for modules with templates as a param of data
                    templates = data.templates;
                }

                // Find selected template
                $.each(templates, function(templateIndex, template) {

                    if(template.id == templateId) {

                        $.each(targetsObject, function(targetSelector, targetTemplateField) {

                            let $target = $(targetSelector, dialog);
                            let targetType = $target.attr('type');

                            // Process types and assign values
                            if(targetType === 'checkbox') { // Checkbox
                                // If the checkbox is a bootstrap switch
                                if($target.hasClass('bootstrap-switch-target')) {
                                    $target.bootstrapSwitch('state', template[targetTemplateField]);
                                } else {
                                    $target.prop('checked', template[targetTemplateField]);
                                }
                            } else { // All the other input types
                                
                                $target.val(template[targetTemplateField]);
                            }
                        });
                    }

                });
            } else {

                // If one of the targets is a boostrap switch, switch it off
                forceBootstrapSwitchesOff();
            }
        };


        // Function to switch off all the bootstrapSwitch
        const forceBootstrapSwitchesOff = function() {
            // If one of the targets is a boostrap switch, switch it off
            $.each(targetsObject, function(targetSelector, targetTemplateField) {
                let $target = $(targetSelector, dialog);

                // Turn off the bootstrapSwitch
                if($target.attr('type') === 'checkbox' && $target.hasClass('bootstrap-switch-target')) { // bootstrap switch
                    $target.bootstrapSwitch('state', false);
                }
            });
        };

        // Register an onchange listener to manipulate the template content if the selector is changed.
        $trigger.on('change', function() {
            applyTemplateContentIfNecessary(data);
        });

        // On load, if the trigger is uncheckedand a target is a boostrap switch, switch it off
        if(!$trigger.is(":checked")) {
            forceBootstrapSwitchesOff();
        }
    };

    /**
     * Create a CKEDITOR instance to conjure a text editor
     * @param {object} dialog - Dialog object ( the object that contains the replaceable fields )
     * @param {object} extraData - Extra data
     * @param {string} textAreaId - Id of the text area to use for the editor
     * @param {bool=} inline - Inline editor option
     * @param {string=} customNoDataMessage - Custom message to appear when the field is empty
     * @param {boolean} focusOnBuild - Focus on the editor after building
     */
    this.setupCKEditor = function(dialog, extraData, textAreaId, inline = false, customNoDataMessage = null, focusOnBuild = false) {
        
        // Get extra data
        var extra = extraData;

        if(extraData === undefined || extraData === null) {
            extra = $(dialog).data().extra;
        }

        // COLORS
        // Background color for the editor
        var backgroundColor = (this.mainObject != undefined && typeof this.mainObject.backgroundColor != 'undefined' && this.mainObject.backgroundColor != null) ? this.mainObject.backgroundColor : this.defaultBackgroundColor;
        
        // Choose a complementary color
        var color = $c.complement(backgroundColor);
        
        // Calculate if inline BG colour should be shown
        var inlineHideBGColour = (inline && this.mainObject.backgroundImage != undefined && this.mainObject.backgroundImage != null);

        // DIMENSIONS
        var region = {};

        // Get region dimensions
        if(this.namespace == undefined){

            if(dialog.find('form').data('regionWidth') != undefined && dialog.find('form').data('regionHeight') != undefined) {
                // Get region dimension from form data
                region.dimensions = {
                    width: dialog.find('form').data('regionWidth'),
                    height: dialog.find('form').data('regionHeight')
                };
            } else {
                // Empty region ( no dimensions set )
                region = {};
            }
        } else if(this.namespace.selectedObject.type == 'widget') {
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

            // Try to show the dimension controls
            this.setupFormDimensionControls(dialog, true);

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
                        this.restartCKEditors(dialog);
                    }
                    
                });
            });

            // Scale
            $(dialog).find('.text_editor_scale').off().change(() => {
                this.restartCKEditors(dialog);
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

            if(this.namespace != undefined) {
                // Calculate scale based on the region previewed in the viewer
                scale = this.namespace.viewer.DOMObject.find('.viewer-element').width() / regionDimensions.width;
            }
        }

        var applyContentsToIframe = function(field) {

            if(inline) {
                // Inline editor div tweaks to make them behave like the iframe rendered content
                $(".cke_textarea_inline").css('width', regionDimensions.width);
                $(".cke_textarea_inline").css('height', regionDimensions.height);
                
                // Show background colour if there's no background image on the layout
                if(!inlineHideBGColour) {
                    $(".cke_textarea_inline").css('background', backgroundColor);
                }
                
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
            CKEDITOR.instances[textAreaId].on('contentDom', function() {
                applyContentsToIframe(textAreaId);
            });

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
            
            if(focusOnBuild) {
                CKEDITOR.instances[textAreaId].focus();
            }
        });

        // Do we have any snippets selector?
        var $selectPickerSnippets = $('.ckeditor_snippets_select[data-linked-to="' + textAreaId + '"]', dialog);
            // Select2 has been initialized
        if($selectPickerSnippets.length > 0) {

            this.setupSnippetsSelector($selectPickerSnippets, function(e) {
                var linkedTo = $selectPickerSnippets.data().linkedTo;
                let value = e.params.data.element.value;
                
                if(CKEDITOR.instances[linkedTo] != undefined && value !== undefined) {
                    let text = "[" + value + "]";

                    CKEDITOR.instances[linkedTo].insertText(text);
                }
            });
        }

        // Do we have a media selector?
        var $selectPicker = $('.ckeditor_library_select[data-linked-to="' + textAreaId + '"]', dialog);
        if($selectPicker.length > 0) {

            this.setupMediaSelector($selectPicker, function(e){
                var linkedTo = $selectPicker.data().linkedTo;
                var value = e.params.data.imageUrl;

                if(value !== undefined && value !== "" && linkedTo != null) {
                    if(CKEDITOR.instances[linkedTo] != undefined) {
                        CKEDITOR.instances[linkedTo].insertHtml("<img src=\"" + value + "\" />");
                    }
                }

            });
        }

        // Turn the background colour into a picker
        $("#backgroundColor").colorpicker();

        return false;
    };
    
    /**
     * Restart all CKEDITOR instances
     */
    this.restartCKEditors = function(dialog) {

        const self = this;
        
        $.each(CKEDITOR.instances, function(index, value) {

            CKEDITOR.instances[index].destroy();

            self.setupCKEditor(dialog, null, index);
        });
    };

    /**
     * Update text callback CKEDITOR instance
     */
    this.updateCKEditor = function(instance) {
        
        const self = this;

        try {
            // Update specific instance
            if(instance != undefined && CKEDITOR.instances[instance] != undefined) {
                // Parse editor data and update it
                self.parseCKEditorData(instance);
            } else {
                $.each(CKEDITOR.instances, function(index, value) {
                    // Parse editor data and update it
                    self.parseCKEditorData(index);
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
        
        const self = this;

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
                    // Parse instance data before destroying 
                    self.parseCKEditorData(instance, CKEDITOR.instances[instance].destroy);
                } else {
                    console.warn("CKEditor instance does not exist.");
                }
            }

        } catch(e) {
            console.warn("Unable to remove CKEditor instance. " + e);
            CKEDITOR.instances = {};
        }
    };

    /**
     * Parse Editor data to turn media path into library tags
     * @param {string} instance - CKEditor instance name to update
     * @param {function=} callback - A function to run after data update
     */
    this.parseCKEditorData = function(instance, callback) {
        
        // If instance is not set, stop right here
        if(CKEDITOR.instances[instance] === undefined) {
            return;
        }

        const regex = new RegExp(CKEDITOR_DEFAULT_CONFIG.imageDownloadUrl.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&").replace(":id", "([0-9]+)"), "g");
        
        let data = CKEDITOR.instances[instance].getData();

        data = data.replace(regex, function(match, group1) {
            return "[" + group1 + "]";
        });

        // Update text field with the new data ( to avoid the setData delay on save )
        $('textarea#' + instance).val(data);

        // Set the appropriate text editor field with this data
        if(callback !== undefined) {
            CKEDITOR.instances[instance].setData(data, callback);
        } else {
            CKEDITOR.instances[instance].setData(data);
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

        footer.prepend(replaceButton);
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
            console.error('Template ' + templateName + ' does not exist on formHelpers file!');
        }

        return templates[templateName];
    };

    /**
     * Hide or show the form dimension controls for the editor
     * @param {boolean} toggleFlag - Flag to show/hide controls
     * @param {string} instanceToDestroy - Name of the instance marked to be destroyed
     */
    this.setupFormDimensionControls = function(dialog, toggleFlag, instanceToDestroy) {

        if(toggleFlag) {
            // Display controls
            $(dialog).find('.form-editor-controls').show();
        } else {
            // Hide the controls if there are no CKEditor instances or the one that is left is marked to be destroyed
            if($.isEmptyObject(CKEDITOR.instances) || (Object.keys(CKEDITOR.instances).length === 1 && CKEDITOR.instances[instanceToDestroy] !== undefined)) {
                // Hide controls
                $(dialog).find('.form-editor-controls').hide();
            }
        }
    };

    /**
     * Run after opening the permission form to set up the fields      
     * @param {object} dialog - Dialog object
     */
    this.permissionsFormAfterOpen = function(dialog) {

        var grid = $("#permissionsTable", dialog).closest(".XiboGrid");

        var table = $("#permissionsTable", dialog).DataTable({
            "language": dataTablesLanguage,
            serverSide: true, stateSave: true,
            "filter": false,
            searchDelay: 3000,
            "order": [[0, "asc"]],
            ajax: {
                url: grid.data().url,
                "data": function(d) {
                    $.extend(d, grid.find(".permissionsTableFilter form").serializeObject());
                }
            },
            "columns": [
                {
                    "data": "group",
                    "render": function(data, type, row, meta) {
                        if(type != "display")
                            return data;

                        if(row.isUser == 1)
                            return data;
                        else
                            return '<strong>' + data + '</strong>';
                    }
                },
                {
                    "data": "view", "render": function(data, type, row, meta) {
                        if(type != "display")
                            return data;

                        return "<input type=\"checkbox\" data-permission=\"view\" data-group-id=\"" + row.groupId + "\" " + ((data == 1) ? "checked" : "") + " />";
                    }
                },
                {
                    "data": "edit", "render": function(data, type, row, meta) {
                        if(type != "display")
                            return data;

                        return "<input type=\"checkbox\" data-permission=\"edit\" data-group-id=\"" + row.groupId + "\" " + ((data == 1) ? "checked" : "") + " />";
                    }
                },
                {
                    "data": "delete", "render": function(data, type, row, meta) {
                        if(type != "display")
                            return data;

                        return "<input type=\"checkbox\" data-permission=\"delete\" data-group-id=\"" + row.groupId + "\" " + ((data == 1) ? "checked" : "") + " />";
                    }
                }
            ]
        });

        table.on('draw', function(e, settings) {
            dataTableDraw(e, settings);

            // permissions should be an object not an array
            if(grid.data().permissions.length <= 0)
                grid.data().permissions = {};

            // Bind to the checkboxes change event
            var target = $("#" + e.target.id);
            target.find("input[type=checkbox]").change(function() {
                // Update our global permissions data with this
                var groupId = $(this).data().groupId;
                var permission = $(this).data().permission;
                var value = $(this).is(":checked");
                if(grid.data().permissions[groupId] === undefined) {
                    grid.data().permissions[groupId] = {};
                }
                grid.data().permissions[groupId][permission] = (value) ? 1 : 0;
            });
        });
        table.on('processing.dt', dataTableProcessing);

        // Bind our filter
        grid.find(".permissionsTableFilter form input, .permissionsTableFilter form select").change(function() {
            table.ajax.reload();
        });
    };

    /**
    * Run before submitting the permission form to process data
    * @param {object} dialog - Dialog object
    * @returns {object} Processed data
    */
    this.permissionsFormBeforeSubmit = function(dialog) {

        var $formContainer = $(".permissions-form", dialog);
        
        var permissions = {
            "groupIds": $('.permissionsGrid', dialog).data().permissions,
            "ownerId": $formContainer.find("select[name=ownerId]").val(),
            "cascade": $formContainer.find("#cascade").is(":checked")
        };

        return $.param(permissions);
    };

    /**
     * Renders the formid provided
     * @param {Object} sourceObj
     * @param {Object} data
     * @param {number=} step
     */
    this.widgetFormRender = function(sourceObj, data, step) {
        const self = this;

        var formUrl = "";
        if(typeof sourceObj === "string" || sourceObj instanceof String) {
            formUrl = sourceObj;
        } else {
            formUrl = sourceObj.attr("href");
        }

        // To fix the error generated by the double click on button
        if(formUrl == undefined) {
            return false;
        }

        // Currently only support one of these at once.
        bootbox.hideAll();

        // Add step to the form url if it exists
        if(step != undefined) {
            formUrl = formUrl.split('?')[0] + '?step=' + step;
        }

        // Call with AJAX
        $.ajax({
            type: "get",
            url: formUrl,
            cache: false,
            dataType: "json",
            success: function(response) {
                // Was the Call successful
                if(response.success) {

                    // Set the dialog HTML to be the response HTML
                    var dialogTitle = "";

                    // Is there a title for the dialog?
                    if(response.dialogTitle != undefined && response.dialogTitle != "") {
                        // Set the dialog title
                        dialogTitle = response.dialogTitle;
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

                    // Buttons
                    let buttons = self.widgetFormRenderButtons(response.buttons);

                    if(buttons !== '') {
                        // Append a footer to the dialog
                        var footer = $("<div>").addClass("modal-footer");
                        dialog.find(".modal-content").append(footer);

                        var i = 0;
                        var count = Object.keys(buttons).length;
                        $.each(
                            buttons,
                            function(index, value) {
                                i++;
                                var extrabutton = $('<button id="dialog_btn_' + i + '" class="btn">').html(value.name);

                                extrabutton.addClass(value.type);

                                extrabutton.attr('id', index);

                                extrabutton.click(function(e) {
                                    e.preventDefault();

                                    self.widgetFormEditAction(dialog, value.action, response.data.module.widget.type, {sourceObj, data, step});

                                    return false;
                                });

                                footer.append(extrabutton);
                            });
                    }

                    // Focus in the first input
                    $('input[type=text]', dialog).eq(0).focus();

                    $('input[type=text]', dialog).each(function(index, el) {
                        formRenderDetectSpacingIssues(el);

                        $(el).on("keyup", _.debounce(function() {
                            formRenderDetectSpacingIssues(el);
                        }, 500));
                    });

                    // Check to see if there are any tab actions
                    $('a[data-toggle="tab"]', dialog).on('shown.bs.tab', function(e) {

                        if($(e.target).data().enlarge === 1) {
                            $(e.target).closest(".modal").addClass("modal-big");
                        }
                        else {
                            $(e.target).closest(".modal").removeClass("modal-big");
                        }
                    });

                    // Check to see if the current tab has the enlarge action
                    $('a[data-toggle="tab"]', dialog).each(function() {
                        if($(this).data().enlarge === 1 && $(this).closest("li").hasClass("active"))
                            $(this).closest(".modal").addClass("modal-big");
                    });

                    // Call Xibo Init for this form
                    XiboInitialise("#" + dialog.attr("id"));

                    // Do we have to call any functions due to this success?
                    if(response.callBack !== "" && response.callBack !== undefined) {
                        eval(response.callBack)(dialog);
                    }

                    // Pass widget options to the form as data
                    let widgetOptions = {};
                    for(let option in response.data.module.widget.widgetOptions) {
                        const currOption = response.data.module.widget.widgetOptions[option];

                        if(currOption.type === 'attrib') {
                            widgetOptions[currOption.option] = currOption.value;
                        }
                    }
                    dialog.find('form').data('elementOptions', widgetOptions);

                    // Store region dimentions to the form
                    if(data.regionWidth != undefined && data.regionHeight != undefined) {
                        dialog.find('form').data('regionWidth', data.regionWidth);
                        dialog.find('form').data('regionHeight', data.regionHeight);
                    }

                    dialog.data('formEditorOnly', true);

                    // Widget after form open specific functions
                    self.widgetFormEditAfterOpen(dialog, response.data.module.widget.type);
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
    };

    /**
     * Run before submitting the permission form to process data
     * @param {object} container - Container object containing form
     * @param {object} widgetType - Widget/module type
     */
    this.widgetFormEditAfterOpen = function(container, widgetType) {

        // Check if form edit open function exists
        if(typeof window[widgetType + '_form_edit_open'] === 'function') {
            window[widgetType + '_form_edit_open'].bind(container)();
        }

        // Hide/Show back button depending on the type of widget
        if(container.find('form').data('formStep') != undefined && container.find('form').data('formStep') > 1) {
            container.find('button#back').show();
        } else {
            container.find('button#back').hide();
        }
    };

    /**
     * Run before submitting the permission form to process data
     * @param {object} container - Container object containing form
     * @param {object} widgetType - Widget/module type
     */
    this.widgetFormEditBeforeSubmit = function(container, widgetType) {

        // Check if form edit submit function exists
        if(typeof window[widgetType + '_form_edit_submit'] === 'function') {
            window[widgetType + '_form_edit_submit'].bind(container)();
        }
    };

    /**
     * Run before submitting the permission form to process data
     * @param {string} actionType - Type of action ( default type or a function call )
     * @param {string} widgetType - Widget type
     * @param {object=} options
     */
    this.widgetFormEditAction = function(container, actionType, widgetType, options = {}) {

        switch(actionType) {
            case 'save':
                this.widgetFormEditSubmit(container, widgetType);
                break;

            case 'back':
                // Get current step
                const currentStep = container.find('form').data('formStep');
                this.widgetFormRender(options.sourceObj, options.data, (currentStep - 1));
                break;

            case 'close':
                container.modal('hide');
                break;

            default:
                if(typeof window[actionType] === 'function') {
                    window[actionType](container);
                } else {
                }
                break;
        }
    };

    /**
     * Submit form
     * @param {object} container - Container object containing form
     * @param {string} widgetType - Widget type
     */
    this.widgetFormEditSubmit = function(container, widgetType) {

        const self = this;

        var changeSaveButtonState = function(disable = true) {
            if(disable) {
                // Disable the button ( Fix https://github.com/xibosignage/xibo/issues/1467)
                container.find('#save').append('<span class="saving fa fa-cog fa-spin"></span>');
                container.find('#save').attr('disabled', 'disabled');
            } else {
                // Re-enable the button
                container.find('#save .saving').remove();
                container.find('#save').attr('disabled', null);
            }
        };

        changeSaveButtonState();

        this.widgetFormEditBeforeSubmit(container, widgetType);

        const $form = container.find('form');

        if($form.valid()) {
            // Get the URL from the action part of the form)
            var url = $form.attr("action");

            $.ajax({
                type: $form.attr("method"),
                url: url,
                cache: false,
                dataType: "json",
                data: $form.serialize(),
                success: function(response) {


                    changeSaveButtonState(false);

                    // success
                    if(response.success) {
                        if(response.message != '')
                            SystemMessage(response.message, true);

                        bootbox.hideAll();

                        XiboRefreshAllGrids();

                        if($form.data("nextFormUrl") != undefined) {
                            self.widgetFormRender($form.data().nextFormUrl.replace(":id", response.id));
                        }
                    } else {
                        // Why did we fail?
                        if(response.login) {
                            // We were logged out
                            LoginBox(response.message);
                        }
                        else {
                            // Likely just an error that we want to report on
                            SystemMessageInline(response.message, $form.closest(".modal"));
                        }
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    SystemMessage(xhr.responseText, false);
                }
            });

            return false;
        } else {
            changeSaveButtonState(false);
        }
    };

    /**
     * Get buttons from twig and generate a buttons object
     * @param {object} - Buttons from twig file
     */
    this.widgetFormRenderButtons = function(inputButtons) {


        let buttons = {};

        // Process buttons from result
        for(let button in inputButtons) {

            // If button is not a cancel or save button, add it to the button object
            if(!(inputButtons[button].includes('XiboDialogClose') || inputButtons[button].includes('.submit()'))) {
                buttons[button] = {
                    name: button,
                    type: 'btn-default',
                    click: inputButtons[button]
                };
            }
        }

        // Add back button
        buttons.back = {
            name: editorsTrans.back,
            type: 'btn-default',
            action: 'back'
        };

        // Add save button
        buttons.save = {
            name: translations.save,
            type: 'btn-info',
            action: 'save'
        };

        return buttons;
    };

};


module.exports = new formHelpers();