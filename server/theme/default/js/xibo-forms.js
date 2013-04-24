function ManageMembersCallBack()
{
    $("#usersIn, #usersOut").sortable({
            connectWith: '.connectedSortable',
            dropOnEmpty: true
    }).disableSelection();

    $(".li-sortable", "#div_dialog").dblclick(switchLists);
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

function GroupSecurityCallBack()
{
    $("#groupsIn, #groupsOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

        $(".li-sortable", "#div_dialog").dblclick(switchLists);
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

function DisplayGroupMembersCallBack()
{
    $("#displaygroupsIn, #displaygroupsOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

        $(".li-sortable", "#div_dialog").dblclick(switchLists);
}

function DisplayGroupMembersSubmit() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#displaygroupsIn").attr('href') + "&ajax=true";

    // Get the two lists
    serializedData = $("#displaygroupsIn").sortable('serialize');

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