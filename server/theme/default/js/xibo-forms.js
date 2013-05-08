var text_callback = function(dialog)
{
    // Conjure up a text editor
    CKEDITOR.replace("ta_text");

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
            var text = "[" + $(this).html() + "|" + $(this).attr("datasetcolumnid") + "]"
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
    // Serialise the form and then submit it via Ajax.
    var href = $("#ModuleForm").attr('action') + "&ajax=true";

    // Get the two lists
    serializedData = $("#columnsIn").sortable('serialize') + "&" + $("#ModuleForm").serialize();

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

function LayoutAssignmentCallBack()
{
    $("#layoutsIn, #layoutsOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

        $(".li-sortable", "#div_dialog").dblclick(switchLists);
}

function LayoutsSubmit() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#layoutsIn").attr('href') + "&ajax=true";
    
    // Get the two lists        
    serializedData = $("#layoutsIn").sortable('serialize');
    
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