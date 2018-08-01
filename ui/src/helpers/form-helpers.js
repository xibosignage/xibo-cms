let formHelpers = function() {

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
    this.setupObjectValueInputFields = function(form, inputValueSelector, inputFieldsArray, customIndexValues = null, inverted = false) {

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
     * Create a CKEDITOR instance to conjure a text editor
     * @param {object} dialog - Dialog object ( the object that contains the replaceable fields )
     * @param {object} extraData- Extra data
     */
    this.textCallback = function(dialog, extraData) {

        var extra = extraData;

        if(extraData === undefined || extraData === null) {
            extra = $(dialog).data().extra;
        }

        // Choose a complementary color
        var color = $c.complement(lD.layout.backgroundColor);
        var regionDimensions = null;

        // Get region dimensions
        if(lD.selectedObject.type == 'widget') {
            regionDimensions = lD.layout.regions[lD.selectedObject.regionId].dimensions;
        } else {
            regionDimensions = lD.layout.regions[lD.selectedObject.id].dimensions;
        }

        // Calculate scale based on the region previewed in the viewer
        var scale = lD.viewer.containerElementDimensions.width / regionDimensions.width; //$layout.attr('designer_scale');

        var applyContentsToIframe = function(field) {
            $("#cke_" + field + " iframe").contents().find("head").append("" +
                "<style>" +
                "body {" +
                "width: " + regionDimensions.width + "px; " +
                "height: " + regionDimensions.height + "px; border:2px solid red; " +
                "margin-right: 10px; " +
                "background: " + lD.layout.backgroundColor + "; " +
                "transform: scale(" + scale + "); " +
                "transform-origin: 0 0; }" +
                "h1, h2, h3, h4, p { margin-top: 0;}" +
                "</style>");
        };

        var applyTemplateContentIfNecessary = function(data, extra) {
            // Check to see if the override template check box is unchecked
            if(!$("#overrideTemplate").is(":checked")) {
                // Get the currently selected templateId
                var templateId = $("#templateId").val();

                // Parse each field
                $.each(extra, function(index, value) {
                    if(value.id == templateId) {

                        data = value.template.replace(/#Color#/g, color);
                        $("#ta_css").val(value.css);

                        // Go through each property
                        $.each(value, function(key, value) {

                            if(key != "template" && key != "css") {
                                // Try to match a field
                                $("#" + key).val(value);
                            }
                        });
                    }
                });
            }

            return data;
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

        // Conjure up a text editor
        CKEDITOR.replace("ta_text", CKEDITOR_DEFAULT_CONFIG);

        // Bind to instance ready so that we can adjust some things about the editor.
        CKEDITOR.instances["ta_text"].on('instanceReady', function() {
            // Apply scaling to this editor instance
            applyContentsToIframe("ta_text");

            // Reapply the background style after switching to source view and back to the normal editing view
            CKEDITOR.instances["ta_text"].on('contentDom', function() {applyContentsToIframe("ta_text")});

            // Get the template data
            var data = CKEDITOR.instances["ta_text"].getData();

            // Default config for fonts
            if(data == "" && !$("#overrideTemplate").is(":checked")) {
                data = "<span style=\"font-size: 48px;\"><span style=\"color: " + color + ";\">" + translations.enterText + "</span></span>";
            }

            // Handle initial template set up
            data = applyTemplateContentIfNecessary(data, extra);
            data = convertLibraryReferences(data);

            CKEDITOR.instances["ta_text"].setData(data);
        });

        // Register an onchange listener to manipulate the template content if the selector is changed.
        $("#templateId").on('change', function() {
            CKEDITOR.instances["ta_text"].setData(applyTemplateContentIfNecessary(CKEDITOR.instances["ta_text"].getData(), extra));
        });

        // Create a no data message editor if one is present
        if($("#noDataMessage").length > 0) {
            CKEDITOR.replace("noDataMessage", CKEDITOR_DEFAULT_CONFIG);
            CKEDITOR.instances["noDataMessage"].on('instanceReady', function() {

                // Apply scaling to this editor instance
                applyContentsToIframe("noDataMessage");

                // Reapply the background style after switching to source view and back to the normal editing view
                CKEDITOR.instances["noDataMessage"].on('contentDom', function() {applyContentsToIframe("noDataMessage")});

                // Get the template data
                var data = CKEDITOR.instances["noDataMessage"].getData();
                if(data === "") {
                    data = "<span style=\"font-size: 48px;\"><span style=\"color: " + color + ";\">" + translations.noDataMessage + "</span></span>";
                }

                // Handle initial template set up
                data = convertLibraryReferences(data);

                CKEDITOR.instances["noDataMessage"].setData(data);
            });
        }

        // Do we have any items to click on that we might want to insert? (these will be our items and not CKEditor ones)
        $('.ckeditor_snippits', dialog).dblclick(function() {
            // Linked to?
            var linkedTo = $(this).attr("linkedto");
            var text;

            if(CKEDITOR.instances[linkedTo] != undefined) {
                if($(this).attr("datasetcolumnid") != undefined)
                    text = "[" + $(this).html() + "|" + $(this).attr("datasetcolumnid") + "]";
                else
                    text = "[" + $(this).html() + "]";

                CKEDITOR.instances[linkedTo].insertText(text);
            }

            return false;
        });

        // Do we have a media selector?
        var $selectPicker = $(".ckeditor_library_select");
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
            }).on('select2:select', function(e) {
                var linkedTo = $(this).data().linkedTo;
                var value = e.params.data.imageUrl;

                console.log('Value is ' + value + ", linked control is " + linkedTo);

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
     * Update text callback CKEDITOR instance
     */
    this.textCallbackUpdate = function() {

        try {
            if(CKEDITOR.instances["ta_text"] !== undefined) {
                CKEDITOR.instances["ta_text"].updateElement();
            }

            if(CKEDITOR.instances["noDataMessage"] !== undefined) {
                CKEDITOR.instances["noDataMessage"].updateElement();
            }
        } catch(e) {
            console.log("Unable to update CKEditor instances. " + e);
        }
    };

    /**
     * Destroy text callback CKEDITOR instance
     */
    this.textCallbackDestroy = function() {

        // Make sure when we close the dialog we also destroy the editor
        try {
            if(CKEDITOR.instances["ta_text"] !== undefined) {
                CKEDITOR.instances["ta_text"].destroy();
            }

            if(CKEDITOR.instances["noDataMessage"] !== undefined) {
                CKEDITOR.instances["noDataMessage"].destroy();
            }
        } catch(e) {
            console.log("Unable to remove CKEditor instance. " + e);
            CKEDITOR.instances = {};
        }
    };


    /**
     * Create and attach a Replace button, and open a upload form on click to replace media
     * @param {object} dialog - Dialog object
     */
    this.mediaEditFormOpen = function(dialog) {
        
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
            lD.toolbar.openUploadForm(
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
                            lD.timeline.resetZoom();
                            lD.reloadData(lD.layout);
                        }
                    }
                }
            );
        });

        footer.find('#Save').before(replaceButton);
    };
};

module.exports = new formHelpers();