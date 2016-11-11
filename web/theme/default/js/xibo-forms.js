var text_callback = function(dialog, extra) {

    if (extra == null) {
        extra = $('.bootbox').data().extra;
    }

    // Set the text template based on the selected template id
    if ($("#ta_text").val() == "" && !$("#overrideTemplate").is(":checked")) {
        // Set something sensible based on the color of the layout background
        var color = $c.complement($("#layout").data().backgroundColor);
        
        // Apply the complementary color and a not to small font-size to the first paragraph of the editor
        $("#ta_text").val('<p style="color:' + color + ';"></p>');

        // Get the current template selected
        var templateId = $("#templateId").val();
            
        $.each(extra, function(index, value) {
            if (value.id == templateId) {
                // Substitute the #Color# references with the suggested complimentary color
                $("#ta_text").val(value.template.replace(/#Color#/g, color));
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

    // Register an onchange listener to do the same if the template is changed
    $("#templateId").on('change', function() {
        // Check to see if the override template check box is unchecked
        if (!$("#overrideTemplate").is(":checked")) {

            var color = $c.complement($("#layout").data().backgroundColor);
            var templateId = $("#templateId").val();
            
            $.each(extra, function(index, value) {
                if (value.id == templateId) {
                    CKEDITOR.instances["ta_text"].setData(value.template.replace(/#Color#/g, color));
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
    });

    // Conjure up a text editor
    CKEDITOR.replace("ta_text", CKEDITOR_DEFAULT_CONFIG);
    
    CKEDITOR.instances["ta_text"].on('instanceReady', function() {
        var scale = $('#layout').attr('designer_scale');

        $("#cke_ta_text .cke_contents").css({
            background: $('#layout').css('background-color')
        });
        
        $("#cke_ta_text iframe").css({
            "background": "transparent"
        });
        
        // Reapply the background style after switching to source view and back to the normal editing view
        CKEDITOR.instances["ta_text"].on('contentDom', function() {
            var scale = $('#layout').attr('designer_scale');
            
            $("#cke_ta_text .cke_contents").css({
                background: $('#layout').css('background-color')
            });
        
            $("#cke_ta_text iframe").css({
                "background": "transparent"
            });
        });

        // We need to convert any library references [123] to their full URL counterparts
        // we leave well alone non-library references.
        var regex = /\[[0-9]+\]/gi;

        var data = CKEDITOR.instances["ta_text"].getData().replace(regex, function (match) {
            var inner = match.replace("]", "").replace("[", "");
            var replacement = CKEDITOR_DEFAULT_CONFIG.imageDownloadUrl.replace(":id", inner);
            //console.log("match = " + match + ". replacement = " + replacement);
            return replacement;
        });

        CKEDITOR.instances["ta_text"].setData(data);
    });

    if ($("#noDataMessage").length > 0) {
        CKEDITOR.replace("noDataMessage", CKEDITOR_DEFAULT_CONFIG);
        CKEDITOR.instances["noDataMessage"].on('instanceReady', function () {

            $("#cke_noDataMessage .cke_contents").css({
                background: $('#layout').css('background-color')
            });

            $("#cke_noDataMessage iframe").css({
                "background": "transparent"
            });

            // Reapply the background style after switching to source view and back to the normal editing view
            CKEDITOR.instances["noDataMessage"].on('contentDom', function () {

                $("#cke_noDataMessage .cke_contents").css({
                    background: $('#layout').css('background-color')
                });

                $("#cke_noDataMessage iframe").css({
                    "background": "transparent"
                });
            });
        });
    }

    // Make sure when we close the dialog we also destroy the editor
    dialog.on("hide", function() {
        if (CKEDITOR.instances["ta_text"] != undefined) {
            CKEDITOR.instances["ta_text"].destroy();
        }

        if (CKEDITOR.instances["noDataMessage"] != undefined) {
            CKEDITOR.instances["noDataMessage"].destroy();
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
    $("#ckeditor_library_select").selectpicker({
        liveSearch: true
    }).on('changed.bs.select', function (e) {
        var select = $(e.target);
        var linkedTo = select.data().linkedTo;
        var value = $(e.target).find(":selected").data().imageUrl;
        
        if (value != "" && linkedTo != null) {
            if (CKEDITOR.instances[linkedTo] != undefined) {
                CKEDITOR.instances[linkedTo].insertHtml("<img src=\"" + value + "\" />");
            }
        }
    });

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

var forecastIoFormSetup = function() {
    $('#color').colorpicker();

    // If all 3 of the template fields are empty, then the template should be reapplied.
    if (!$("#overrideTemplate").is(":checked") && ($("#currentTemplate").val() == "" || $("#dailyTemplate").val() == "" || $("#styleSheet").val() == "")) {
        // Reapply
        var templateId = $("#templateId").val();

        $.each($('.bootbox').data().extra, function(index, value) {
            if (value.id == templateId) {
                $("#widgetOriginalWidth").val(value.widgetOriginalWidth);
                $("#widgetOriginalHeight").val(value.widgetOriginalHeight);
                $("#currentTemplate").val(value.main);
                $("#dailyTemplate").val(value.daily);
                $("#styleSheet").val(value.css);
            }
        });
    }

    $("#templateId").on('change', function() {
        // Check to see if the override template check box is unchecked
        if (!$("#overrideTemplate").is(":checked")) {

            var templateId = $("#templateId").val();

            $.each($('.bootbox').data().extra, function(index, value) {
                if (value.id == templateId) {
                    $("#widgetOriginalWidth").val(value.widgetOriginalWidth);
                    $("#widgetOriginalHeight").val(value.widgetOriginalHeight);
                    $("#currentTemplate").val(value.main);
                    $("#dailyTemplate").val(value.daily);
                    $("#styleSheet").val(value.css);
                }
            });
        }
    });
};

var financeFormSetup = function() {
    $('#backgroundColor').colorpicker({format: 'rgba'});

    // If all 3 of the template fields are empty, then the template should be reapplied.
    if (!$("#overrideTemplate").is(":checked") && ($("#currentTemplate").val() == "" || $("#mainTemplate").val() == "" || $("#itemTemplate").val() == "" || $("#styleSheet").val() == "")) {
        // Reapply
        var templateId = $("#templateId").val();

        $.each($('.bootbox').data().extra, function(index, value) {
            if (value.id == templateId) {
                $("#widgetOriginalWidth").val(value.widgetOriginalWidth);
                $("#widgetOriginalHeight").val(value.widgetOriginalHeight);
                $("#mainTemplate").val(value.main);
                $("#itemTemplate").val(value.item);
                $("#styleSheet").val(value.css);
                $("#maxItemsPerPage").val(value.maxItemsPerPage);
            }
        });
    }

    $("#templateId").on('change', function() {
        // Check to see if the override template check box is unchecked
        if (!$("#overrideTemplate").is(":checked")) {

            var templateId = $("#templateId").val();

            $.each($('.bootbox').data().extra, function(index, value) {
                if (value.id == templateId) {
                    $("#widgetOriginalWidth").val(value.widgetOriginalWidth);
                    $("#widgetOriginalHeight").val(value.widgetOriginalHeight);
                    $("#mainTemplate").val(value.main);
                    $("#itemTemplate").val(value.item);
                    $("#styleSheet").val(value.css);
                    $("#maxItemsPerPage").val(value.maxItemsPerPage);
                }
            });
        }
    });
};

var requestTab = function(tabName, url) {
    // Fill a new tab with the forecast information and then switch to that tab.
    $.ajax({
        type: "get",
        url: url,
        cache: false,
        data: "tab="+tabName,
        success: function(response, status, xhr) {
            $(".tab-content #" + tabName).html(response.html);

            $('.nav-tabs a[href="#' + tabName + '"]').tab('show');
        }
    });
};

var settingsUpdated = function(response) {
    if (!response.success) {
        SystemMessage((response.message == "") ? translation.failure : response.message, true);
    }
};

var backGroundFormSetup = function(dialog) {
    $('#backgroundColor').colorpicker({format: "hex"});

    var backgroundImageList = $('#backgroundImageId');
    var backgroundImage = $('#bg_image_image');
    var initialBackgroundImageId = backgroundImageList.val();
    var backgroundChanged = false;

    function backgroundImageChange() {
        // Want to attach an onchange event to the drop down for the bg-image
        var id = backgroundImageList.val();

        var src;
        if (id == 0)
            src = backgroundImage.data().notFoundUrl;
        else
            src = backgroundImage.data().url.replace(":id", id);

        backgroundImage.attr("src", src);

        if (id != initialBackgroundImageId)
            backgroundChanged = true;
    }

    backgroundImageList.change(backgroundImageChange);

    backgroundImageChange();

    // Bind to the background add button click
    $("#backgroundAddButton").on("click", function(e) {
        $(this).addClass("disabled");
        layoutEditBackgroundButtonClicked(e, dialog)
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
                        // On the layout page - call render
                        if (backgroundChanged && table != undefined)
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

    // Append a background upload button to the dialog footer.
    var template = Handlebars.compile($("#layout-background-image-upload-template").html());
    var footer = dialog.find(".modal-footer");

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
    });
}

function permissionsFormOpen(dialog) {

    var grid = $("#permissionsTable").closest(".XiboGrid");

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

                    return "<input type=\"checkbox\" data-permission=\"view\" data-group-id=\"" + row.groupId + "\" " + ((data == 1) ? "checked" : "") + " />";
                }
            },
            { "data": "edit", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    return "<input type=\"checkbox\" data-permission=\"edit\" data-group-id=\"" + row.groupId + "\" " + ((data == 1) ? "checked" : "") + " />";
                }
            },
            { "data": "delete", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    return "<input type=\"checkbox\" data-permission=\"delete\" data-group-id=\"" + row.groupId + "\" " + ((data == 1) ? "checked" : "") + " />";
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

            grid.data().permissions[groupId][permission] = (value) ? 1 : 0;
        })
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
function mediaFormCallBack() {

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