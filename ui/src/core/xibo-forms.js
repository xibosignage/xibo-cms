var text_callback = function(dialog, extraData) {

    var extra = extraData;

    if (extraData === undefined || extraData === null) {
        extra = $('.bootbox').data().extra;
    }

    // Choose a complementary color
    var color = $c.complement($("#layout").data().backgroundColor);

    // Apply some CSS to set a scale for these editors
    var $layout = $("#layout");
    var scale = $layout.attr('designer_scale');
    var regionWidth = $("#region_" + $layout.data().currentRegionId).attr("width");
    var regionHeight = $("#region_" + $layout.data().currentRegionId).attr("height");
    var applyContentsToIframe = function(field) {
        //console.log('Applying iframe adjustments to ' + field);
        $("#cke_" + field + " iframe").contents().find("head").append("" +
            "<style>" +
            "body {" +
            "width: " + regionWidth + "px; " +
            "height: " + regionHeight + "px; border:2px solid red; " +
            "background: " + $('#layout').css('background-color') + "; " +
            "transform: scale(" + scale + "); " +
            "transform-origin: 0 0; }" +
            "h1, h2, h3, h4, p { margin-top: 0;}" +
            "</style>");
    };

    var applyTemplateContentIfNecessary = function(data, extra) {
        // Check to see if the override template check box is unchecked
        if (!$("#overrideTemplate").is(":checked")) {
            // Get the currently selected templateId
            var templateId = $("#templateId").val();

            // Parse each field
            $.each(extra, function(index, value) {
                if (value.id == templateId) {
                    data = value.template.replace(/#Color#/g, color);
                    $("#ta_css").val(value.css);

                    // Go through each property
                    $.each(value, function (key, value) {

                        if (key != "template" && key != "css") {
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

        data = data.replace(regex, function (match) {
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
        CKEDITOR.instances["ta_text"].on('contentDom', function () { applyContentsToIframe("ta_text") });

        // Get the template data
        var data = CKEDITOR.instances["ta_text"].getData();

        // Default config for fonts
        if (data == "" && !$("#overrideTemplate").is(":checked")) {
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
    if ($("#noDataMessage").length > 0) {
        CKEDITOR.replace("noDataMessage", CKEDITOR_DEFAULT_CONFIG);
        CKEDITOR.instances["noDataMessage"].on('instanceReady', function () {
            // Apply scaling to this editor instance
            applyContentsToIframe("noDataMessage");

            // Reapply the background style after switching to source view and back to the normal editing view
            CKEDITOR.instances["noDataMessage"].on('contentDom', function () { applyContentsToIframe("noDataMessage") });

            // Get the template data
            var data = CKEDITOR.instances["noDataMessage"].getData();
            if (data === "") {
                data = "<span style=\"font-size: 48px;\"><span style=\"color: " + color + ";\">" + translations.noDataMessage + "</span></span>";
            }

            // Handle initial template set up
            data = convertLibraryReferences(data);

            CKEDITOR.instances["noDataMessage"].setData(data);
        });
    }

    // Make sure when we close the dialog we also destroy the editor
    dialog.on("hide.bs.modal", function(e) {
        if(e.namespace === 'bs.modal') {
            try {
                if (CKEDITOR.instances["ta_text"] !== undefined) {
                    CKEDITOR.instances["ta_text"].destroy();
                }

                if (CKEDITOR.instances["noDataMessage"] !== undefined) {
                    CKEDITOR.instances["noDataMessage"].destroy();
                }
            } catch (e) {
                console.log("Unable to remove CKEditor instance. " + e);
            }

            // Remove colour picker
            $("#backgroundColor").colorpicker('destroy');
        }
    });

    // Do we have any items to click on that we might want to insert? (these will be our items and not CKEditor ones)
    $('.ckeditor_snippits', dialog).dblclick(function(){
        // Linked to?
        var linkedTo = $(this).attr("linkedto");
        var text;

        if (CKEDITOR.instances[linkedTo] != undefined) {
            if ($(this).attr("datasetcolumnid") != undefined)
                text = "[" + $(this).html() + "|" + $(this).attr("datasetcolumnid") + "]";
            else
                text = "[" + $(this).html() + "]";

            CKEDITOR.instances[linkedTo].insertText(text);
        }

        return false;
    });

    // Do we have a media selector?
    var $selectPicker = $(".ckeditor_library_select");
    if ($selectPicker.length > 0) {
        $selectPicker.select2({
            ajax: {
                url: $selectPicker.data().searchUrl,
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
                    if (params.page != null) {
                        query.start = (params.page - 1) * 10;
                    }

                    // Find out what is inside the search box for this list, and save it (so we can replay it when the list
                    // is opened again)
                    if (params.term !== undefined) {
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
            },
            dropdownParent: $(dialog)
        }).on('select2:select', function (e) {
                var linkedTo = $(this).data().linkedTo;
                var value = e.params.data.imageUrl;

                console.log('Value is ' + value + ", linked control is " + linkedTo);

                if (value !== undefined && value !== "" && linkedTo != null) {
                    if (CKEDITOR.instances[linkedTo] != undefined) {
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
 * Switches an item between 2 connected lists.
 */
function switchLists(e) {
   // determine which list they are in
   // http://www.remotesynthesis.com/post.cfm/working-with-related-sortable-lists-in-jquery-ui
   var otherList = $($(e.currentTarget).parent().sortable("option","connectWith")).not($(e.currentTarget).parent());

   otherList.append(e.currentTarget);
}

function GroupSecurityCallBack(dialog)
{
    $("#groupsIn, #groupsOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

    $(".li-sortable", dialog).dblclick(switchLists);
}

function GroupSecuritySubmit() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#groupsIn").attr('href') + "&ajax=true";
    
    // Get the two lists        
    serializedData = $("#groupsIn").sortable('serialize');
    
    $.ajax({
        type: "post",
        url: href,
        cache: false,
        dataType: "json",
        data: serializedData,
        success: XiboSubmitResponse
    });
    
    return;
}

function DisplayGroupManageMembersCallBack(dialog)
{
    $("#displaysIn, #displaysOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

    $(".li-sortable", dialog).dblclick(switchLists);
}

function DisplayGroupMembersSubmit() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#displaysIn").attr('href') + "&ajax=true";

    // Get the two lists
    serializedData = $("#displaysIn").sortable('serialize');

    $.ajax({
        type: "post",
        url: href,
        cache: false,
        dataType: "json",
        data: serializedData,
        success: XiboSubmitResponse
    });

    return;
}

/**
 * Library Assignment Form Callback
 */
var FileAssociationsCallback = function()
{
    // Attach a click handler to all of the little pointers in the grid.
    $("#FileAssociationsTable .library_assign_list_select").click(function(){
        // Get the row that this is in.
        var row = $(this).parent().parent();

        // Construct a new list item for the lower list and append it.
        var newItem = $("<li/>", {
            text: row.attr("litext"),
            id: row.attr("rowid"),
            "class": "li-sortable",
            dblclick: function(){
                $(this).remove();
            }
        });

        newItem.appendTo("#FileAssociationsSortable");

        // Add a span to that new item
        $("<span/>", {
            "class": "glyphicon glyphicon-minus-sign",
            click: function(){
                $(this).parent().remove();
                $(".modal-body .XiboGrid").each(function(){

                    var gridId = $(this).attr("id");

                    // Render
                    XiboGridRender(gridId);
                });
            }
        })
        .appendTo(newItem);

        // Remove the row
        row.remove();
    });

    // Attach a click handler to all of the little points in the trough
    $("#FileAssociationsSortable li .glyphicon-minus-sign").click(function() {

        // Remove this and refresh the table
        $(this).parent().remove();

    });

    $("#FileAssociationsSortable").sortable().disableSelection();
};

var FileAssociationsSubmit = function(displayGroupId)
{
    // Serialize the data from the form and call submit
    var mediaList = $("#FileAssociationsSortable").sortable('serialize');

    $.ajax({
        type: "post",
        url: "index.php?p=displaygroup&q=SetFileAssociations&displaygroupid="+displayGroupId+"&ajax=true",
        cache: false,
        dataType: "json",
        data: mediaList,
        success: XiboSubmitResponse
    });
};

var settingsUpdated = function(response) {
    if (!response.success) {
        SystemMessage((response.message == "") ? translation.failure : response.message, true);
    }
};

var attachmentFormSubmit = function(dialog) {

    var form = $(dialog);

    // Pull any text editor instances we have
    for (var editor in CKEDITOR.instances) {

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

    // Submit via ajax - change the attachment color on success
    $.ajax({
        type: form.attr("method"),
        url: form.attr("action"),
        cache: false,
        dataType: "json",
        data: $(form).serialize(),
        success: function(xhr, textStatus, error) {

            XiboSubmitResponse(xhr, form);

            if (xhr.success) {

                console.log('success');

            }
        },
        error: function(xhr, textStatus, errorThrown) {
            SystemMessage(xhr.responseText, false);
        }
    });
}

var attachmentFormSetup = function(dialog) {

    // Conjure up a text editor
    CKEDITOR.replace("body", CKEDITOR_DEFAULT_CONFIG);

    // Make sure when we close the dialog we also destroy the editor
    dialog.on("hide.bs.modal", function(event) {
        if (event.target.className == "bootbox modal in" && CKEDITOR.instances["body"] != undefined) {
            CKEDITOR.instances["body"].destroy();
        }
    });

    var attachmentImageList = $('#attachmentImageId');
    var attachmentChanged = false;

    // Bind to the attachment add button click
    $("#attachmentAddButton").on("click", function(e) {
        $(this).addClass("disabled");
        notificationAddFormAttachmentButtonClicked(e, dialog);
    });

    // Search for any forms that will need submitting
    // NOTE: The validation plugin does not like binding to multiple forms at once.
    dialog.find("#notificationForm").validate({
        submitHandler: attachmentFormSubmit,
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

};

/**
 * Add notification attachment add image button
 * @param e the event
 * @param dialog the dialog
 */
function notificationAddFormAttachmentButtonClicked(e, dialog) {
    e.preventDefault();

    // Hide the original button
    dialog.find('.attachment-add-button').hide();
    dialog.find('#notificationAddFormAttachmentUpload').show();

    // Append a background upload button to the dialog footer.
    var template = Handlebars.compile($("#attachment-upload-template").html());
    var footer = dialog.find("#notificationAddFormAttachmentUpload");

    footer.append(template());

    var form = footer.find("form");
    var url = form.prop("action");
    var refreshSessionInterval;

    // Initialize the jQuery File Upload widget:
    form.fileupload({
        url: url,
        uploadTemplateId: 'template-upload-simple',
        disableImageResize: true,
        previewMaxWidth: 100,
        previewMaxHeight: 100,
        previewCrop: true
    });

    // Upload server status check for browsers with CORS support:
    if ($.support.cors) {
        $.ajax({
            url: url,
            type: 'HEAD'
        }).fail(function () {
            $('<span class="alert alert-error"/>')
                .text('Upload server currently unavailable - ' + new Date())
                .appendTo(form);
        });
    }

    // Enable iframe cross-domain access via redirect option:
    form.fileupload(
        'option',
        'redirect',
        window.location.href.replace(
            /\/[^\/]*$/,
            '/cors/result.html?%s'
        )
    );

    form.bind('fileuploadsubmit', function (e, data) {

        // Disable the buttons on the form
        footer.find("button").addClass("disabled");

    }).bind('fileuploadstart', function (e, data) {

        // Show progress data
        form.find('.fileupload-progress .progress-extended').show();
        form.find('.fileupload-progress .progress-end').hide();

        if (form.fileupload("active") <= 0)
            refreshSessionInterval = setInterval("XiboPing('" + pingUrl + "?refreshSession=true')", 1000 * 60 * 3);

        return true;

    }).bind('fileuploaddone', function (e, data) {

        // Enable the buttons on the form
        footer.find("button").removeClass("disabled");

        if (refreshSessionInterval != null && form.fileupload("active") <= 0)
            clearInterval(refreshSessionInterval);

        console.log(data.result);

        if (data.result.files[0].error != null && data.result.files[0].error != "") {
            return;
        }

        // Take the image URL from the response, and use it to replace the background image fields
        dialog.find(".attachment-fields").slideUp();

        console.log(' hidefileinput-button');

        dialog.find(".fileinput-button").hide();
        dialog.find(".fileinput-close-button").removeClass('hidden');
        // Get the attachment filename
        var filename = data.result.files[0].name;

        dialog.find("input[name='attachedFilename']").remove();

        // Create a hidden field with the filename
        $("#notificationForm").append($("<input type='hidden' name='attachedFilename' value='" + filename + "'/>"));

        // Hide the stuff we've added
        // form.slideUp();
    }).bind('fileuploadprogressall', function(e, data) {
        // Hide progress data and show processing
        if(data.total > 0 && data.loaded == data.total) {
            form.find('.fileupload-progress .progress-extended').hide();
            form.find('.fileupload-progress .progress-end').show();
        }
    });

    // Click the browse button
    dialog.find('.fileinput-button input').click();

    // Click the close button
    dialog.find('.fileinput-close-button').click(function() {

        // dialog.find(".fileinput-button").show();
        // dialog.find(".fileinput-close-button").hide();
        dialog.find("input[name='attachedFilename']").remove();
        dialog.find('.attachment-add-button').show();
        dialog.find('#attachmentAddButton').removeClass('disabled');
        dialog.find('#notificationAddFormAttachmentUpload').hide();
        footer.html('');
    });
}

var backGroundFormSetup = function(dialog) {
    $('#backgroundColor').colorpicker({format: "hex"});

    // Tidy up colorpickers on modal close
    if(dialog.hasClass('modal')) {
        dialog.on("hide.bs.modal", function(e) {
            if(e.namespace === 'bs.modal') {
                // Remove colour pickers
                dialog.find("#backgroundColor").colorpicker('destroy');
            }
        });
    }

    var backgroundImageList = $('#backgroundImageId');
    var notFoundIcon = $('#bg_not_found_icon');
    var backgroundImage = $('#bg_image_image');
    var initialBackgroundImageId = backgroundImageList.val();
    var backgroundChanged = false;

    function backgroundImageChange() {
        // Want to attach an onchange event to the drop down for the bg-image
        var id = backgroundImageList.val();

        var src;
        // If the image is not defined
        if ([0, ''].indexOf(id) !== -1) {

            // Show not found icon and hide image
            notFoundIcon.show();
            backgroundImage.hide();
        } else {

            // Hide not found icon and show image
            notFoundIcon.hide();
            backgroundImage.show();

            // Replace image source
            src = backgroundImage.data().url.replace(":id", id);
            backgroundImage.attr("src", src);
        }

        if (id != initialBackgroundImageId)
            backgroundChanged = true;
    }

    backgroundImageList.change(backgroundImageChange);

    backgroundImageChange();

    // Bind to the background add button click
    $("#backgroundAddButton").on("click", function(e) {
        $(this).addClass("disabled");
        layoutEditBackgroundButtonClicked(e, dialog);
    });

    // Bind to the layout form submit
    $("#layoutEditForm").submit(function(e) {
        e.preventDefault();

        var form = $(this);

        // Submit via ajax - change the background color on success
        $.ajax({
            type: form.attr("method"),
            url: form.attr("action"),
            cache: false,
            dataType: "json",
            data: $(form).serialize(),
            success: function(xhr, textStatus, error) {

                XiboSubmitResponse(xhr, form);

                if (xhr.success) {
                    var layout = $("div#layout");

                    if (layout.length > 0) {
                        var color = form.find("#backgroundColor").val();
                        layout.data().backgroundColor = color;
                        layout.css("background-color", color);

                        if (backgroundChanged)
                            window.location.reload();
                    } else {
                        // We assume we're on the layout page - call render
                        // If we're not, table is a Chrome/Safari/FireBug global function
                        if (backgroundChanged && typeof(table) !== 'undefined' && table.hasOwnProperty('ajax'))
                            table.ajax.reload(null, false);
                    }
                }
            },
            error: function(xhr, textStatus, errorThrown) {
                SystemMessage(xhr.responseText, false);
            }
        });
    })
};

/**
 * Layout edit background add image button
 * @param e the event
 * @param dialog the dialog
 */
function layoutEditBackgroundButtonClicked(e, dialog) {
    e.preventDefault();

    // Hide the original button
    dialog.find('.background-image-add-button').hide();
    dialog.find('#layoutEditFormBackgroundUpload').show();

    // Append a background upload button to the dialog footer.
    var template = Handlebars.compile($("#layout-background-image-upload-template").html());
    var footer = dialog.find("#layoutEditFormBackgroundUpload");

    footer.append(template());

    var form = footer.find("form");
    var url = form.prop("action");
    var refreshSessionInterval;

    // Initialize the jQuery File Upload widget:
    form.fileupload({
        url: url,
        disableImageResize: true,
        previewMaxWidth: 100,
        previewMaxHeight: 100,
        previewCrop: true
    });

    // Upload server status check for browsers with CORS support:
    if ($.support.cors) {
        $.ajax({
            url: url,
            type: 'HEAD'
        }).fail(function () {
            $('<span class="alert alert-error"/>')
                .text('Upload server currently unavailable - ' + new Date())
                .appendTo(form);
        });
    }

    // Enable iframe cross-domain access via redirect option:
    form.fileupload(
        'option',
        'redirect',
        window.location.href.replace(
            /\/[^\/]*$/,
            '/cors/result.html?%s'
        )
    );

    form.bind('fileuploadsubmit', function (e, data) {

        // Disable the buttons on the form
        footer.find("button").addClass("disabled");

    }).bind('fileuploadstart', function (e, data) {

        // Show progress data
        form.find('.fileupload-progress .progress-extended').show();
        form.find('.fileupload-progress .progress-end').hide();
        
        if (form.fileupload("active") <= 0)
            refreshSessionInterval = setInterval("XiboPing('" + pingUrl + "?refreshSession=true')", 1000 * 60 * 3);

        return true;

    }).bind('fileuploaddone', function (e, data) {

        // Enable the buttons on the form
        footer.find("button").removeClass("disabled");

        if (refreshSessionInterval != null && form.fileupload("active") <= 0)
            clearInterval(refreshSessionInterval);

        if (data.result.files[0].error != null && data.result.files[0].error != "") {
            return;
        }

        // Take the image URL from the response, and use it to replace the background image fields
        dialog.find(".background-image-fields").slideUp();

        // Get the mediaId
        var mediaId = data.result.files[0].mediaId;

        // Create a hidden field with the mediaId
        $("#layoutEditForm").append($("<input type='hidden' name='backgroundImageId' value='" + mediaId + "'/>"));

        var bgImagePreview = dialog.find("#bg_image_image");
        bgImagePreview.prop("src", bgImagePreview.data().url.replace(":id", mediaId));

        // Hide the stuff we've added
        form.slideUp();
    }).bind('fileuploadprogressall', function(e, data) {
        // Hide progress data and show processing
        if(data.total > 0 && data.loaded == data.total) {
            form.find('.fileupload-progress .progress-extended').hide();
            form.find('.fileupload-progress .progress-end').show();
        }
    });

    // Click the browse button
    dialog.find('.fileinput-button input').click();

    // Click the close button
    dialog.find('.fileinput-close-button').click(function() {
        dialog.find('.background-image-add-button').show();
        dialog.find('#backgroundAddButton').removeClass('disabled');
        dialog.find('#layoutEditFormBackgroundUpload').hide();
        footer.html('');
    });
}

function permissionsFormOpen(dialog) {

    var grid = $("#permissionsTable").closest(".XiboGrid");

    // initialise the permissions array
    if (grid.data().permissions.length <= 0)
        grid.data().permissions = {};

    var table = $("#permissionsTable").DataTable({ "language": dataTablesLanguage,
        serverSide: true, stateSave: true,
        "filter": false,
        searchDelay: 3000,
        "order": [[ 0, "asc"]],
        ajax: {
            url: grid.data().url,
            "data": function(d) {
                $.extend(d, grid.find(".permissionsTableFilter form").serializeObject());
            }
        },
        "columns": [
            {
                "data": "group",
                "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    if (row.isUser == 1)
                        return data;
                    else
                        return '<strong>' + data + '</strong>';
                }
            },
            { "data": "view", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in grid.data().permissions) {
                        var cache = grid.data().permissions[row.groupId];

                        checked = (cache.view !== undefined && cache.view === 1) ? 1 : 0;
                    } else {
                        checked = data;
                    }

                    // Cached changes to this field?
                    return "<input type=\"checkbox\" data-permission=\"view\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            },
            { "data": "edit", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in grid.data().permissions) {
                        var cache = grid.data().permissions[row.groupId];

                        checked = (cache.edit !== undefined && cache.edit === 1) ? 1 : 0;
                    } else {
                        checked = data;
                    }

                    return "<input type=\"checkbox\" data-permission=\"edit\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            },
            { "data": "delete", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in grid.data().permissions) {
                        var cache = grid.data().permissions[row.groupId];

                        checked = (cache.delete !== undefined && cache.delete === 1) ? 1 : 0;
                    } else {
                        checked = data;
                    }

                    return "<input type=\"checkbox\" data-permission=\"delete\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            }
        ]
    });

    table.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Bind to the checkboxes change event
        var target = $("#" + e.target.id);
        target.find("input[type=checkbox]").change(function() {
            // Update our global permissions data with this
            var groupId = $(this).data().groupId;
            var permission = $(this).data().permission;
            var value = $(this).is(":checked");
            //console.log("Setting permissions on groupId: " + groupId + ". Permission " + permission + ". Value: " + value);
            if (grid.data().permissions[groupId] === undefined) {
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
}

function permissionsFormSubmit(id) {

    var form = $("#" + id);
    var $formContainer = form.closest(".permissions-form");
    var permissions = {
        "groupIds": $(form).data().permissions,
        "ownerId": $formContainer.find("select[name=ownerId]").val(),
        "cascade": $formContainer.find("#cascade").is(":checked")
    };
    var data = $.param(permissions);

    $.ajax({
        type: "POST",
        url: form.data().url,
        cache: false,
        dataType: "json",
        data: data,
        success: function(xhr, textStatus, error) {
            XiboSubmitResponse(xhr, form);
        },
        error: function(xhr, textStatus, errorThrown) {
            SystemMessage(xhr.responseText, false);
        }
    });
}

function membersFormOpen(dialog) {

    // Get our table
    var table = $(dialog).find(".membersTable");

    if (table.data().members == undefined)
        table.data().members = {};

    // Bind to the checkboxes change event
    table.find("input[type=checkbox]").change(function() {
        // Update our global members data with this
        var memberId = $(this).data().memberId;
        var value = $(this).is(":checked");

        //console.log("Setting memberId: " + memberId + ". Value: " + value);

        table.data().members[memberId] = (value) ? 1 : 0;
    });
}

function membersFormSubmit(id) {

    var form = $("#" + id);
    var members = form.find(".membersTable").data().members;

    // There may not have been any changes
    if (members == undefined) {
        // No changes
        XiboDialogClose();
        return;
    }

    // Build an array of id's to assign and an array to unassign
    var assign = [];
    var unassign = [];

    $.each(members, function(name, value) {
        if (value == 1)
            assign.push(name);
        else
            unassign.push(name);
    });

    var error = false;
    var data = {};
    data[form.data().param] = assign;
    data[form.data().paramUnassign] = unassign;

    $.ajax({
        type: "POST",
        url: form.data().url,
        cache: false,
        dataType: "json",
        data: $.param(data),
        success: function(xhr, textStatus, error) {
            XiboSubmitResponse(xhr, form);
        },
        error: function(xhr, textStatus, errorThrown) {
            SystemMessage(xhr.responseText, false);
        }
    });
}

// Callback for the media form
function mediaDisplayGroupFormCallBack() {

    var container = $("#FileAssociationsAssign");
    if (container.data().media == undefined)
        container.data().media = {};

    var mediaTable = $("#mediaAssignments").DataTable({ "language": dataTablesLanguage,
            serverSide: true, stateSave: true,
            searchDelay: 3000,
            "order": [[ 0, "asc"]],
            "filter": false,
            ajax: {
                "url": $("#mediaAssignments").data().url,
            "data": function(d) {
                $.extend(d, $("#mediaAssignments").closest(".XiboGrid").find(".FilterDiv form").serializeObject());
            }
        },
        "columns": [
            { "data": "name" },
            { "data": "mediaType" },
            {
                "sortable": false,
                "data": function(data, type, row, meta) {
                    if (type != "display")
                        return "";

                    // Create a click-able span
                    return "<a href=\"#\" class=\"assignItem\"><span class=\"glyphicon glyphicon-plus-sign\"></a>";
                }
            }
        ]
    });

    mediaTable.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Clicky on the +spans
        $(".assignItem", "#mediaAssignments").click(function() {
            // Get the row that this is in.
            var data = mediaTable.row($(this).closest("tr")).data();

            // Append to our media list
            container.data().media[data.mediaId] = 1;

            // Construct a new list item for the lower list and append it.
            var newItem = $("<li/>", {
                "text": data.name,
                "data-media-id": data.mediaId,
                "class": "btn btn-sm btn-default"
            });

            newItem.appendTo("#FileAssociationsSortable");

            // Add a span to that new item
            $("<span/>", {
                "class": "glyphicon glyphicon-minus-sign",
                click: function(){
                    container.data().media[$(this).parent().data().mediaId] = 0;
                    $(this).parent().remove();
                }
            }).appendTo(newItem);
        });
    });
    mediaTable.on('processing.dt', dataTableProcessing);

    // Make our little list sortable
    $("#FileAssociationsSortable").sortable();

    // Bind to the existing items in the list
    $("#FileAssociationsSortable").find('li span').click(function () {
        container.data().media[$(this).parent().data().mediaId] = 0;
        $(this).parent().remove();
    });

    // Bind to the filter
    $("#mediaAssignments").closest(".XiboGrid").find(".FilterDiv input, .FilterDiv select").change(function() {
        mediaTable.ajax.reload();
    });
}

function mediaAssignSubmit() {
    // Collect our media
    var container = $("#FileAssociationsAssign");

    // Build an array of id's to assign and an array to unassign
    var assign = [];
    var unassign = [];

    $.each(container.data().media, function(name, value) {
        if (value == 1)
            assign.push(name);
        else
            unassign.push(name);
    });

    assignMediaToCampaign(container.data().url, assign, unassign);
}

var assignMediaToCampaign = function(url, media, unassignMedia) {
    toastr.info("Assign Media", media);

    $.ajax({
        type: "post",
        url: url,
        cache: false,
        dataType: "json",
        data: {mediaId: media, unassignMediaId: unassignMedia},
        success: XiboSubmitResponse
    });
};

// Callback for the media form
function layoutFormCallBack() {

    var container = $("#FileAssociationsAssign");
    if (container.data().layout == undefined)
        container.data().layout = {};

    var layoutTable = $("#layoutAssignments").DataTable({ "language": dataTablesLanguage,
        serverSide: true, stateSave: true,
        searchDelay: 3000,
        "order": [[ 0, "asc"]],
        "filter": false,
        ajax: {
            "url": $("#layoutAssignments").data().url,
            "data": function(d) {
                $.extend(d, $("#layoutAssignments").closest(".XiboGrid").find(".FilterDiv form").serializeObject());
            }
        },
        "columns": [
            { "data": "layout" },
            {
                "sortable": false,
                "data": function(data, type, row, meta) {
                    if (type != "display")
                        return "";

                    // Create a click-able span
                    return "<a href=\"#\" class=\"assignItem\"><span class=\"glyphicon glyphicon-plus-sign\"></a>";
                }
            }
        ]
    });

    layoutTable.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Clicky on the +spans
        $(".assignItem", "#layoutAssignments").click(function() {
            // Get the row that this is in.
            var data = layoutTable.row($(this).closest("tr")).data();

            // Append to our layout list
            container.data().layout[data.layoutId] = 1;

            // Construct a new list item for the lower list and append it.
            var newItem = $("<li/>", {
                "text": data.layout,
                "data-layout-id": data.layoutId,
                "class": "btn btn-sm btn-default"
            });

            newItem.appendTo("#FileAssociationsSortable");

            // Add a span to that new item
            $("<span/>", {
                "class": "glyphicon glyphicon-minus-sign",
                click: function(){
                    container.data().layout[$(this).parent().data().layoutId] = 0;
                    $(this).parent().remove();
                }
            }).appendTo(newItem);
        });
    });
    layoutTable.on('processing.dt', dataTableProcessing);

    // Make our little list sortable
    $("#FileAssociationsSortable").sortable();

    // Bind to the existing items in the list
    $("#FileAssociationsSortable").find('li span').click(function () {
        container.data().layout[$(this).parent().data().layoutId] = 0;
        $(this).parent().remove();
    });

    // Bind to the filter
    $("#layoutAssignments").closest(".XiboGrid").find(".FilterDiv input, .FilterDiv select").change(function() {
        layoutTable.ajax.reload();
    });
}

function layoutAssignSubmit() {
    // Collect our layout
    var container = $("#FileAssociationsAssign");

    // Build an array of id's to assign and an array to unassign
    var assign = [];
    var unassign = [];

    $.each(container.data().layout, function(name, value) {
        if (value == 1)
            assign.push(name);
        else
            unassign.push(name);
    });

    assignLayoutToCampaign(container.data().url, assign, unassign);
}

var assignLayoutToCampaign = function(url, layout, unassignLayout) {
    toastr.info("Assign Layout", layout);

    $.ajax({
        type: "post",
        url: url,
        cache: false,
        dataType: "json",
        data: {layoutId: layout, unassignLayoutId: unassignLayout},
        success: XiboSubmitResponse
    });
};

function regionEditFormSubmit() {
    XiboFormSubmit($("#regionEditForm"), null, function(xhr, form) {

        if (xhr.success)
            window.location.reload();
    });
}

function userProfileEditFormOpen() {

    $("#qRCode").addClass("hidden");
    $("#recoveryButtons").addClass("hidden");
    $("#recoveryCodes").addClass("hidden");

    $("#twoFactorTypeId").on("change", function (e) {
        e.preventDefault();
        if ($("#twoFactorTypeId").val() == 2 && $('#userEditProfileForm').data().currentuser != 2) {
            $.ajax({
                url: $('#userEditProfileForm').data().setup,
                type: "GET",
                beforeSend: function () {
                    $("#qr").addClass('fa fa-spinner fa-spin loading-icon')
                },
                success: function (response) {
                    let qRCode = response.data.qRUrl;
                    $("#qrImage").attr("src", qRCode);
                },
                complete: function () {
                    $("#qr").removeClass('fa fa-spinner fa-spin loading-icon')
                }
            });
            $("#qRCode").removeClass("hidden");
        } else {
            $("#qRCode").addClass("hidden");
        }

        if ($("#twoFactorTypeId").val() == 0) {
            $("#recoveryButtons").addClass("hidden");
            $("#recoveryCodes").addClass("hidden");
        }

        if ($('#userEditProfileForm').data().currentuser != 0 && $("#twoFactorTypeId").val() != 0) {
            $("#recoveryButtons").removeClass("hidden");
        }
    });

    if ($('#userEditProfileForm').data().currentuser != 0) {
        $("#recoveryButtons").removeClass("hidden");
    }
    let generatedCodes = '';

    $('#generateCodesBtn').on("click", function (e) {
        $("#codesList").html("");
        $("#recoveryCodes").removeClass('hidden');
        $(".recBtn").attr("disabled", true).addClass("disabled");
        generatedCodes = '';

        $.ajax({
            url: $('#userEditProfileForm').data().generate,
            async: false,
            type: "GET",
            beforeSend: function () {
                $("#codesList").removeClass('well').addClass('fa fa-spinner fa-spin loading-icon');
            },
            success: function (response) {
                generatedCodes = JSON.parse(response.data.codes);
                $("#recoveryCodes").addClass('hidden');
                $(".recBtn").attr("disabled", false).removeClass("disabled");
                $('#showCodesBtn').click();
            },
            complete: function () {
                $("#codesList").removeClass('fa fa-spinner fa-spin loading-icon');
            }
        });
    });

    $('#showCodesBtn').on("click", function (e) {
        $(".recBtn").attr("disabled", true).addClass("disabled");
        $("#codesList").html("");
        $("#recoveryCodes").toggleClass('hidden');
        let codesList = [];

        $.ajax({
            url: $('#userEditProfileForm').data().show,
            type: "GET",
            data: {
                generatedCodes: generatedCodes,
            },
            success: function (response) {
                if (generatedCodes != '') {
                    codesList = generatedCodes;
                } else {
                    codesList = response.data.codes;
                }

                $('#twoFactorRecoveryCodes').val(JSON.stringify(codesList));
                $.each(codesList, function (index, value) {
                    $("#codesList").append(value + "<br/>");
                });
                $("#codesList").addClass('well');
                $(".recBtn").attr("disabled", false).removeClass("disabled");
            }
        });
    });
}

function tagsWithValues(formId) {
    $('#tagValue, label[for="tagValue"], #tagValueRequired').addClass("hidden");

    let tag;
    let tagWithOption = '';
    let tagN = '';
    let tagV = '';
    let tagOptions = [];
    let tagIsRequired = 0;

    let formSelector = '#' + formId + ' input#tags';

    $(formSelector).on('beforeItemAdd', function(event) {
        $('#tagValue').html('');
        tag = event.item;
        tagOptions = [];
        tagIsRequired = 0;
        tagN = tag.split('|')[0];
        tagV = tag.split('|')[1];

        if ($(formSelector).val().indexOf(tagN) === -1 && tagV === undefined) {
            $.ajax({
                url: $('#'+formId).data().gettag,
                type: "GET",
                data: {
                    name: tagN,
                },
                beforeSend: function () {
                    $("#loadingValues").addClass('fa fa-spinner fa-spin loading-icon')
                },
                success: function (response) {

                    if (response.success && response.data.tag != null) {
                        tagOptions = JSON.parse(response.data.tag.options);
                        tagIsRequired = response.data.tag.isRequired;

                        if (tagOptions != null && tagOptions != []) {
                            $('#tagValue, label[for="tagValue"]').removeClass("hidden");

                            $('#tagValue')
                                .append($("<option></option>")
                                    .attr("value", '')
                                    .text(''));

                            $.each(tagOptions, function (key, value) {
                                $('#tagValue')
                                    .append($("<option></option>")
                                        .attr("value", value)
                                        .text(value));
                            });

                            $('#tagValue').focus();
                        }
                    }
                },
                complete: function () {
                    $("#loadingValues").removeClass('fa fa-spinner fa-spin loading-icon')
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error(jqXHR, textStatus, errorThrown);
                }
            });
        }
    });

    $(formSelector).on('itemAdded', function(event) {
        if (tagOptions != null && tagOptions !== []) {
            $('#tagValue').focus();
        }
    });

    $(formSelector).on('itemRemoved', function(event) {

        if(tagN === event.item) {
            $('#tagValueRequired, label[for="tagValue"]').addClass('hidden');
            $('.save-button').prop('disabled', false);
            $('#tagValue').html('').addClass("hidden");
        } else if ($(".save-button").is(":disabled")) {
            // do nothing with jQuery
        } else {
            $('#tagValue').html('').addClass("hidden");
            $('label[for="tagValue"]').addClass("hidden");
        }
    });

    $("#tagValue").on("change", function (e) {
        e.preventDefault();
        tagWithOption = tagN + '|' + $(this).val();

        if (tagIsRequired === 0 || (tagIsRequired === 1 && $(this).val() !== '')) {
            $(formSelector).tagsinput('add', tagWithOption);
            $(formSelector).tagsinput('remove', tagN);
            $('#tagValue').html('').addClass("hidden");
            $('#tagValueRequired, label[for="tagValue"]').addClass('hidden');
            $('.save-button').prop('disabled', false);
        } else {
            $('#tagValueRequired').removeClass('hidden');
            $('#tagValue').focus();
        }
    });

    $('#tagValue').blur(function() {
        if($(this).val() === '' && tagIsRequired === 1 ) {
            $('#tagValueRequired').removeClass('hidden');
            $('#tagValue').focus();
            $('.save-button').prop('disabled', true);
        } else {
            $('#tagValue').html('').addClass("hidden");
            $('label[for="tagValue"]').addClass("hidden");
        }
    });
}