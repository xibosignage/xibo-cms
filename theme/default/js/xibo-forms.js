var text_callback = function(dialog)
{
    // Conjure up a text editor
    CKEDITOR.replace("ta_text");
    CKEDITOR.config.contentsCss = 'body {background-color:' + $('#layout').css('background-color') + ';} html.cke_panel_container body { background-color: #FFF;}';

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

        if (CKEDITOR.instances[linkedTo] != undefined) {
            if ($(this).attr("datasetcolumnid") != undefined)
                var text = "[" + $(this).html() + "|" + $(this).attr("datasetcolumnid") + "]"
            else
                var text = "[" + $(this).html() + "]"

            CKEDITOR.instances[linkedTo].insertText(text);
        }

        return false;
    });
    
    return false;
}

var microblog_callback = function(dialog)
{
    // Conjure up a text editor
    CKEDITOR.replace("ta_template");
    CKEDITOR.replace("ta_nocontent");
    CKEDITOR.config.contentsCss = 'body {background-color:' + $('#layout').css('background-color'); + ';}';

    // Make sure when we close the dialog we also destroy the editor
    dialog.on("hide", function() {
        if (CKEDITOR.instances["ta_template"] != undefined) {
            CKEDITOR.instances["ta_template"].destroy();
        }
        
        if (CKEDITOR.instances["ta_nocontent"] != undefined) {
            CKEDITOR.instances["ta_nocontent"].destroy();
        }
    });

    return false;
}

var datasetview_callback = function(dialog)
{
    $("#columnsIn, #columnsOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

    return false; //prevent submit
}

var DataSetViewSubmit = function() {
    
    // Get the two lists
    $("#ModuleForm").attr('action', $("#ModuleForm").attr('action') + "&ajax=true&" + $("#columnsIn").sortable('serialize')).submit();
    return;
}

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
var LayoutAssignCallback = function()
{
    // Attach a click handler to all of the little pointers in the grid.
    $("#LayoutAssignTable .layout_assign_list_select").click(function(){
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

        newItem.appendTo("#LayoutAssignSortable");

        // Add a span to that new item
        $("<span/>", {
            "class": "icon-minus-sign",
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
}

function LayoutsSubmit(campaignId) {
    // Serialise the form and then submit it via Ajax.
    var layouts = $("#LayoutAssignSortable").sortable('serialize');

    layouts = layouts + "&CampaignID=" + campaignId + "&token=" + $('#LayoutAssignTable input[name=token]').val();
    
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
    //var url = "upload.php";

    // Initialize the jQuery File Upload widget:
    $('#fileupload').fileupload({
        // Uncomment the following to send cross-domain cookies:
        //xhrFields: {withCredentials: true},
        url: url
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
    
    // Initialize the jQuery File Upload widget:
    $('#fileupload').fileupload('option', {
        url: url,
        disableImageResize: false
    });

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
            "class": "icon-minus-sign",
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
    $("#FileAssociationsSortable li .icon-minus-sign").click(function() {

        // Remove this and refresh the table
        $(this).parent().remove();

    });

    $("#FileAssociationsSortable").sortable().disableSelection();
}

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
}