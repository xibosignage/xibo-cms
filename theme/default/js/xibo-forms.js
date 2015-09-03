var text_callback = function(dialog)
{
    // Set the text template based on the selected template id
    if ($("#ta_text").val() == "" && !$("#overrideTemplate").is(":checked")) {
        // Set something sensible based on the color of the layout background
        var color = $c.complement($("#layout").data().backgroundColor);
        
        // Apply the complementary color and a not to small font-size to the first paragraph of the editor
        $("#ta_text").val('<p style="color:' + color + '; font-size:48px;"></p>');

        // Get the current template selected
        var templateId = $("#templateId").val();
            
        $.each($('.bootbox').data().extra, function(index, value) {
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
            
            $.each($('.bootbox').data().extra, function(index, value) {
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
    CKEDITOR.replace("ta_text");
    
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
        
    });

    // Make sure when we close the dialog we also destroy the editor
    dialog.on("hide", function() {
        if (CKEDITOR.instances["ta_text"] != undefined) {
            CKEDITOR.instances["ta_text"].destroy();
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

    // Turn the background colour into a picker
    $("#backgroundColor").colorpicker();

    return false;
};

var datasetview_callback = function(dialog)
{
    $("#columnsIn, #columnsOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

    return false; //prevent submit
}

var DataSetViewSubmit = function() {
    var form = $("#ModuleForm");
    // Get the two lists
    form.attr('action', form.attr('action') + "&ajax=true&" + $("#columnsIn").sortable('serialize')).submit();
};

function ManageMembersCallBack(dialog)
{
    $("#usersIn, #usersOut").sortable({
            connectWith: '.connectedSortable',
            dropOnEmpty: true
    }).disableSelection();

    $(".li-sortable", dialog).dblclick(switchLists);
}

function MembersSubmit() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#usersIn").attr('href') + "&ajax=true";

    // Get the two lists
    serializedData = $("#usersIn").sortable('serialize');

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
 * Layout Assignment Form Callback
 */
var LayoutAssignCallback = function(gridId)
{
    // Attach a click handler to all of the little pointers in the grid.
    $("#" + gridId).find(".layout_assign_list_select").click(function(){
        // Get the row that this is in.
        var row = $(this).closest("tr");

        // Construct a new list item for the lower list and append it.
        var newItem = $("<li/>", {
            text: row.data().litext,
            id: row.data().rowid,
            "class": "btn btn-sm btn-default",
            dblclick: function(){
                $(this).remove();
            }
        });

        newItem.appendTo("#LayoutAssignSortable");

        // Add a span to that new item
        $("<span/>", {
            "class": "glyphicon glyphicon-minus-sign",
            click: function(){
                $(this).parent().remove();
            }
        })
        .appendTo(newItem);

    });

    // There could be some existing items...
    $("#LayoutAssignSortable li span").click(function() {
        $(this).parent().remove();
    });

    $("#LayoutAssignSortable").sortable().disableSelection();
};

function LayoutsSubmit(campaignId) {
    // Serialise the form and then submit it via Ajax.
    var layouts = $("#LayoutAssignSortable").sortable('serialize');

    layouts = layouts + "&CampaignID=" + campaignId + "&assign_token=" + $("#LayoutAssignSortable input[name='assign_token']").val();
    
    $.ajax({
        type: "post",
        url: "index.php?p=campaign&q=SetMembers&ajax=true",
        cache: false,
        dataType: "json",
        data: layouts,
        success: XiboSubmitResponse
    });
    
    return;
}

function fileFormSubmit() {
    // Update the UI to say its submitting
    $('#uploadProgress').fadeIn("slow");
    $('#file_upload').hide();
}

/**
 * 
 * @param {Object} fileName
 * @param {Object} fileId
 * @param {Object} errorCode
 */
function fileUploadReport(fileName, fileId, errorCode) {
    
    var uploadProgress = $('#uploadProgress');
    
    if (errorCode == 0)
    {
        $('#txtFileName').val(fileName);
        $('#hidFileID').val(fileId);
        
        uploadProgress.html("File upload complete.");
    }
    else
    {
        uploadProgress.hide();
        $('#file_upload').show();
        
        if (errorCode == 1)
        {
            SystemMessage("The file exceeds the maximum allowed file size. [Code 1]");
        }
        else if (errorCode == 2)
        {
            SystemMessage("The file exceeds the maximum allowed file size. [Code 2]");
        }
        else if (errorCode == 3)
        {
            SystemMessage("The file upload was interrupted, please retry. [Code 3]");
        }
        else if (errorCode == 25000)
        {
            SystemMessage("Could not encrypt this file. [Code 25000]");
        }
        else if (errorCode == 25001)
        {
            SystemMessage("Could not save this file after encryption. [Code 25001]");
        }
        else
        {
            SystemMessage("There was an error uploading this file [Code " + errorCode + "]");
        }
    }
}

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

function MediaFormInitUpload(dialog) {
    // URL for the file upload handler
    var url = $('#fileupload').attr("action");
    
    // Initialize the jQuery File Upload widget:
    $('#fileupload').fileupload({
        url: url,
        disableImageResize: false
    });

    // Upload server status check for browsers with CORS support:
    if ($.support.cors) {
        $.ajax({
            url: url,
            type: 'HEAD'
        }).fail(function () {
            $('<span class="alert alert-error"/>')
                .text('Upload server currently unavailable - ' +
                        new Date())
                .appendTo('#fileupload');
        });
    }
    
    // Enable iframe cross-domain access via redirect option:
    $('#fileupload').fileupload(
        'option',
        'redirect',
        window.location.href.replace(
            /\/[^\/]*$/,
            '/cors/result.html?%s'
        )
    );

    $('#fileupload').bind('fileuploadsubmit', function (e, data) {
        var inputs = data.context.find(':input');
        if (inputs.filter('[required][value=""]').first().focus().length) {
            return false;
        }
        data.formData = inputs.serializeArray().concat($("#fileupload").serializeArray());

        inputs.filter("input").prop("disabled", true);
    });
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
                    $("#currentTemplate").val(value.main);
                    $("#dailyTemplate").val(value.daily);
                    $("#styleSheet").val(value.css);
                }
            });
        }
    });
};

var requestTab = function(tabName, url) {
    // Fill a new tab with the forecast information and then switch to that tab.
    $.ajax({
        type: "post",
        url: url+"&ajax=true",
        cache: false,
        data: "tab="+tabName,
        success: function(response, status, xhr) {
            $(".tab-content #" + tabName).html(response);

            $('.nav-tabs a[href="#' + tabName + '"]').tab('show');
        }
    });
};

var settingsUpdated = function(response) {
    if (response.success) {
        $("#SettingsForm input[name='token']").val($(response.nextToken).val());
    }
    else {
        SystemMessage((response.message == "") ? translation.failure : response.message, true);
    }
};
