/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
$(document).ready(function(){
	
	// Set the height of the grid to be something sensible for the current screen resolution
	$('#LayoutJumpList .XiboGrid').css("height", $(window).height() - 200);
    
	$('#JumpListHeader').click(function(){
       if ($('#JumpListOpenClose').html() == "^")
           $('#JumpListOpenClose').html("v");
       else
           $('#JumpListOpenClose').html("^");
       
       $('#' + $(this).attr('JumpListGridID')).slideToggle("slow", "swing");
	});

	$("#layout").each(function(){

		$(this).find(".region")
			.draggable({
		            containment: this,
		            stop: regionPositionUpdate,
		            drag: updateRegionInfo
        		})
			.resizable({
		            containment: this,
		            minWidth: 25,
		            minHeight: 25,
		            stop: regionPositionUpdate,
		            resize: updateRegionInfo
		        });

		// Preview
		$('.regionPreview', this).each(function(){
            new Preview(this);
		});

        // Set an interval
        XiboPing('index.php?p=layout&q=LayoutStatus&layoutId=' + $(this).attr("layoutid"), '.layout-status');

        setInterval("XiboPing('index.php?p=layout&q=LayoutStatus&layoutId=" + $(this).attr("layoutid") + "', '.layout-status')", 1000 * 60); // Every minute
	});

    $('.RegionOptionsMenuItem').click(function() {

        var width   = $(this).closest('.region').css("width");
        var height  = $(this).closest('.region').css("height");
        var top     = $(this).closest('.region').css("top");
        var left    = $(this).closest('.region').css("left");
        var regionid = $(this).closest('.region').attr("regionid");
        var layoutid = $(this).closest('.region').attr("layoutid");
        var scale = $(this).closest('.region').attr("scale");
        var layoutWidth = $(this).closest('.layout').css("width");
        var layoutHeight = $(this).closest('.layout').css("height");

        var url = "index.php?p=timeline&q=ManualRegionPositionForm&layoutid=" + layoutid + "&regionid=" + regionid + "&top=" + top + "&left=" + left + "&width=" + width + "&height=" + height + "&scale=" + scale + "&layoutWidth=" + layoutWidth + "&layoutHeight=" + layoutHeight;

        XiboFormRender(url);
    });

});

/**
 * Update Region Information with Latest Width/Position
 * @param  {[type]} e  [description]
 * @param  {[type]} ui [description]
 * @return {[type]}    [description]
 */
function updateRegionInfo(e, ui) {

    var pos = $(this).position();
    var scale = $(this).attr("scale");
    $('.region-tip', this).html(Math.round($(this).width() * scale, 0) + " x " + Math.round($(this).height() * scale, 0) + " (" + Math.round(pos.left * scale, 0) + "," + Math.round(pos.top * scale, 0) + ")");
}

function regionPositionUpdate(e, ui) {

	var width 	= $(this).css("width");
	var height 	= $(this).css("height");
	var top 	= $(this).css("top");
	var left 	= $(this).css("left");
	var regionid = $(this).attr("regionid");
	var layoutid = $(this).attr("layoutid");

    // Update the region width / height attributes
    $(this).attr("width", width).attr("height", height);

    // Update the Preview for the new sizing
    var preview = Preview.instances[regionid];
    preview.SetSequence(preview.seq);

    // Expose a new button to save the positions
    if ($("#layout-save-all").length <= 0) {

	    $("<button/>",  {
	    		"class": "btn",
	    		id: "layout-save-all",
	    		html: translations.save_position_button
		    })
	    	.click(function() {
	    		// Save positions for all layouts / regions
	    		savePositions();
	    		return false;
		    })
		    .appendTo(".layout-meta");
    }
}

function savePositions() {

	// Ditch the button
	$("#layout-save-all").remove();

	// Update all layouts
	$("#layout").each(function(){

        // Store the Layout ID
        var layoutid = $(this).attr("layoutid");

        // Build an array of
        var regions = new Array();

		$(this).find(".region").each(function(){

            var region = {
                width: $(this).css("width"),
                height: $(this).css("height"),
                top: $(this).css("top"),
                left: $(this).css("left"),
                regionid: $(this).attr("regionid")
            }

            // Update the region width / height attributes
            $(this).attr("width", region.width).attr("height", region.height);

            // Add to the array
            regions.push(region);
		});

        $.ajax({
                type: "post", 
                url: "index.php?p=timeline&q=RegionChange&layoutid="+layoutid+"&ajax=true", 
                cache: false, 
                dataType: "json", 
                data: {regions : JSON.stringify(regions) },
                success: XiboSubmitResponse
            });
	});
}

function XiboAssignToLayout(layoutId, regionId) {

    var mediaitems = Array();

    $("#fileupload .files .name").each(function() {

        // Is this item in error?
        if ($(this).attr("status") != "error")
            mediaitems.push($(this).attr("id"));
    });

    $.ajax({
        type: "post",
        url: "index.php?p=timeline&q=AddFromLibrary&layoutid="+layoutId+"&regionid="+regionId+"&ajax=true",
        cache: false,
        dataType: "json",
        data: { MediaID: mediaitems },
        success: XiboSubmitResponse
    });
}

/**
 * Sets the layout to full screen
 */
function setFullScreenLayout() {
    $('#width', '.XiboForm').val($('#layoutWidth').val());
    $('#height', '.XiboForm').val($('#layoutHeight').val());
    $('#top', '.XiboForm').val('0');
    $('#left', '.XiboForm').val('0');
}

function transitionFormLoad() {
    $("#transitionType").change(transitionSelectListChanged);
    
    // Fire once for initialisation
    transitionSelectListChanged();
}

function transitionSelectListChanged() {
    // See if we need to disable any of the other form elements based on this selection
    var selectionOption = $("#transitionType option:selected");
    
    if (!selectionOption.hasClass("hasDuration"))
        $("tr.transitionDuration").hide();
    else
        $("tr.transitionDuration").show();
        
    if (!selectionOption.hasClass("hasDirection"))
        $("tr.transitionDirection").hide();
    else
        $("tr.transitionDirection").show();
}

var LoadTimeLineCallback = function() {

    // Refresh the preview
    var preview = Preview.instances[$('#timelineControl').attr('regionid')];
    preview.SetSequence(preview.seq);

    $("li.timelineMediaListItem").hover(function() {

        var position = $(this).position();

        //Change the hidden div's content
        $("div#timelinePreview")
            .html($("div.timelineMediaPreview", this).html())
            .css("margin-top", position.top + $('#timelineControl').closest('.modal-body').scrollTop())
            .show();

    }, function() {
        return false;
    });

    $(".timelineSortableListOfMedia").sortable();
}


var XiboTimelineSaveOrder = function(mediaListId, layoutId, regionId) {

    //console.log(mediaListId);

    // Load the media id's into an array
    var mediaList = "";

    $('#' + mediaListId + ' li.timelineMediaListItem').each(function(){
        mediaList = mediaList + $(this).attr("mediaid") + "," + $(this).attr("lkid") + "|";
    });

    //console.log("Media List: " + mediaList);

    // Call the server to do the reorder
    $.ajax({
        type:"post",
        url:"index.php?p=timeline&q=TimelineReorder&layoutid="+layoutId+"&ajax=true",
        cache:false,
        dataType:"json",
        data:{
            "regionid": regionId,
            "medialist": mediaList
        },
        success: XiboSubmitResponse
    });
}

/**
 * Library Assignment Form Callback
 */
var LibraryAssignCallback = function()
{
    // Attach a click handler to all of the little pointers in the grid.
    $("#LibraryAssignTable .library_assign_list_select").click(function(){
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

        newItem.appendTo("#LibraryAssignSortable");

        // Add a span to that new item
        $("<span/>", {
            "class": "icon-minus-sign",
            click: function(){
                $(this).parent().remove();
            }
        })
        .appendTo(newItem);

    });

    $("#LibraryAssignSortable").sortable().disableSelection();
}

var LibraryAssignSubmit = function(layoutId, regionId)
{
    // Serialize the data from the form and call submit
    var mediaList = $("#LibraryAssignSortable").sortable('serialize');

    mediaList = mediaList + "&regionid=" + regionId;

    //console.log(mediaList);

    $.ajax({
        type: "post",
        url: "index.php?p=timeline&q=AddFromLibrary&layoutid="+layoutId+"&ajax=true",
        cache: false,
        dataType: "json",
        data: mediaList,
        success: XiboSubmitResponse
    });
}

var background_button_callback = function() {
	//Want to attach an onchange event to the drop down for the bg-image
	var id = $('#bg_image').val();

	$('#bg_image_image').attr("src", "index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=" + id + "&width=80&height=80&dynamic");
}
