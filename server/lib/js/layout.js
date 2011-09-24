/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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

var region_options_callback = function(outputDiv)
{	
	//Get all the tooltip_hidden
	$(".tooltip_hidden").parent().hover(function() {
		var html = $(".tooltip_hidden",this).html();
		var left = this.offsetLeft - this.offsetParent.scrollLeft;;
		
		//Change the hidden div's content
		$('#tooltip_hover')	.html(html)
							.css("left",left)
							.show();
	}, function() {
		$('#tooltip_hover').hide();
	});
	
	// Make the elements draggable
	$(".timebar_ctl").draggable({
		containment: document.getElementById("timeline_ctl")
	});
	
	$(".mediabreak").droppable({
		accept: ".timebar_ctl",
		tolerance: "pointer",
		drop: function(ev, ui) {
			orderRegion(ui.draggable, this);
		}
	});

        // Refresh the preview
        var preview = Preview.instances[$('#timeline_ctl').attr('regionid')];
        preview.SetSequence(preview.seq);
}

var background_button_callback = function()
{
	//Want to attach an onchange event to the drop down for the bg-image
	var id = $('#bg_image').val();

	$('#bg_image_image').attr("src", "index.php?p=module&q=GetImage&id=" + id + "&width=80&height=80&dynamic");
}

var text_callback = function()
{
    // Conjure up a text editor
    $("#ta_text").ckeditor();

    // Make sure when we close the dialog we also destroy the editor
    $("#div_dialog").bind("dialogclose.xibo", function(event, ui){
        $("#ta_text").ckeditorGet().destroy();
        $("#div_dialog").unbind("dialogclose.xibo");
    })

    var regionid = $("#iRegionId").val();
    var width = $("#region_"+regionid).width();
    var height = $("#region_"+regionid).height();

    // Min width
    if (width < 800) width = 800;

    // Adjust the width and height
    width = width + 80;
    height = height + 295;

    $('#div_dialog').height(height+"px");
    $('#div_dialog').dialog('option', 'width', width);
    $('#div_dialog').dialog('option', 'height', height);
    $('#div_dialog').dialog('option', 'position', 'center');

    return false; //prevent submit
}

var microblog_callback = function()
{
    // Conjure up a text editor
    $("#ta_template").ckeditor();
    $("#ta_nocontent").ckeditor();

    // Make sure when we close the dialog we also destroy the editor
    $("#div_dialog").bind("dialogclose.xibo", function(event, ui){
        $("#ta_template").ckeditorGet().destroy();
        $("#ta_nocontent").ckeditorGet().destroy();

        $("#div_dialog").unbind("dialogclose.xibo");
    })
    
    var regionid = $("#iRegionId").val();
    var width = $("#region_"+regionid).width();
    var height = $("#region_"+regionid).height();

    //Min width
    if (width < 800) width = 800;
    height = height - 170;

    // Min height
    if (height < 300) height = 300;

    width = width + 80;
    height = height + 480;

    $('#div_dialog').height(height+"px");
    $('#div_dialog').dialog('option', 'width', width);
    $('#div_dialog').dialog('option', 'height', height);
    $('#div_dialog').dialog('option', 'position', 'center');

    return false; //prevent submit
}

var datasetview_callback = function()
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

$(document).ready(function() {
	
	var container = document.getElementById('layout');
	
	$('.region').draggable({
            containment:container,
            stop:function(e, ui){
                //Called when draggable is finished
                submitBackground(this);
            },
            drag: updateRegionInfo
        }).resizable({
            containment:container,
            minWidth:25,
            minHeight:25,
            stop:function(e, ui){
                //Called when resizable is finished
                submitBackground(this);
            },
            resize: updateRegionInfo
        }).contextMenu('regionMenu', {
	    bindings: {
                'btnTimeline': function(t) {
                    XiboFormRender($(t).attr("href"));
	        },
                'options' : function(region) {
                    var width 	= $(region).css("width");
                    var height 	= $(region).css("height");
                    var top 	= $(region).css("top");
                    var left 	= $(region).css("left");
                    var regionid = $(region).attr("regionid");
                    var layoutid = $(region).attr("layoutid");

                    var layout = $('#layout');

                    XiboFormRender("index.php?p=layout&q=ManualRegionPositionForm&layoutid="+layoutid+"&regionid="+regionid+"&top="+top+"&left="+left+"&width="+width+"&height="+height+"&layoutWidth="+layout.width()+"&layoutHeight="+layout.height());
                },
		'deleteRegion': function(t) {
	            deleteRegion(t);
	        },
		'setAsHomepage': function(t) {
                    var layoutid = $(t).attr("layoutid");
                    var regionid = $(t).attr("regionid");

	            XiboFormRender("index.php?p=layout&q=RegionPermissionsForm&layoutid="+layoutid+"&regionid="+regionid);
	        }
            }
	});
	
	$('#layout').contextMenu('layoutMenu', {
		bindings: {
			'addRegion': function(t){
				addRegion(t);
			},
			'editBackground': function(t) {
				XiboFormRender($('#background_button').attr("href"));
			},
			'layoutProperties': function(t) {
				XiboFormRender($('#edit_button').attr("href"));
			},
			'templateSave': function(t) {
				var layoutid = $(t).attr("layoutid");
			
				XiboFormRender("index.php?p=template&q=TemplateForm&layoutid="+layoutid);
			}
		}
	});
	
	
	// Preview
	$('.regionPreview').each(function(){
            new Preview(this);
	});

        // Aspect ration option
       $('#lockAspectRatio').change(function(){
            var opt = $('#lockAspectRatio').val();

            if (opt == "on") {
                alert("on");
                $('.region').resizable('option', 'aspectRatio', true);
            }
            else {
                $('.region').resizable('option', 'aspectRatio', false);
            }
       });
});

/*
 * Updates the Region Info
 */
function updateRegionInfo(e, ui) {
    var pos = $(this).position();
    $('.regionInfo', this).html($(this).width() + " x " + $(this).height() + " (" + pos.left + "," + pos.top + ")");
}

/**
 * Adds a region to the specified layout
 * @param {Object} layout
 */
function addRegion(layout)
{
	var layoutid = $(layout).attr("layoutid");
	
	$.ajax({type:"post", url:"index.php?p=layout&q=AddRegion&layoutid="+layoutid+"&ajax=true", cache:false, dataType:"json",success: XiboSubmitResponse});
}

/**
 * Submits the background changes from draggable / resizable
 * @param {Object} region
 */
function submitBackground(region)
{
	var width 	= $(region).css("width");
	var height 	= $(region).css("height");
	var top 	= $(region).css("top");
	var left 	= $(region).css("left");
	var regionid = $(region).attr("regionid");
	var layoutid = $(region).attr("layoutid");

        var preview = Preview.instances[regionid];
        preview.SetSequence(preview.seq);
	
	$.ajax({type:"post", url:"index.php?p=layout&q=RegionChange&layoutid="+layoutid+"&ajax=true", cache:false, dataType:"json", 
		data:{"width":width,"height":height,"top":top,"left":left,"regionid":regionid},success: XiboSubmitResponse});
}

/**
 * Deletes a region
 */
function deleteRegion(region) {
	var regionid = $(region).attr("regionid");
	var layoutid = $(region).attr("layoutid");

	XiboFormRender("index.php?p=layout&q=DeleteRegionForm&layoutid="+layoutid+"&regionid="+regionid);
}

/**
 * Reorders the Region specified by the timebar and its position
 * @param {Object} timeBar
 * @param {Object} mediaBreak
 */
function orderRegion(timeBar, mediaBreak) {
	var timeLine = $(timeBar).parent().parent();
	
	var layoutid = timeLine.attr("layoutid");
	var regionid = timeLine.attr("regionid");
	var mediaid = $(timeBar).attr("mediaid");
        var lkid     = $(timeBar).attr("lkid");
	var sequence = $(mediaBreak).attr("breakid");
	
	$.ajax({type:"post", url:"index.php?p=layout&q=RegionOrder&layoutid="+layoutid+"&callingpage=layout&ajax=true", cache:false, dataType:"json", 
		data:{"mediaid":mediaid,"lkid":lkid,"sequence":sequence,"regionid":regionid},success: XiboSubmitResponse});
}

/**
 * Handles the tRegionOptions trigger
 */
function tRegionOptions() {
    var regionid = gup("regionid");
    var layoutid = gup("layoutid");
	
    XiboFormRender('index.php?p=layout&layoutid='+layoutid+'&regionid='+regionid+'&q=RegionOptions');
}

function manualPositionCallback() {
    $('#btnFullScreen').click(function(){
        $('#width', '.XiboForm').val($('#layoutWidth').val());
        $('#height', '.XiboForm').val($('#layoutHeight').val());
        $('#top', '.XiboForm').val('0');
        $('#left', '.XiboForm').val('0');
    })
}

function Preview(regionElement)
{
	// Load the preview - sequence 1
	this.seq = 1;
	this.layoutid = $(regionElement).attr("layoutid");
	this.regionid = $(regionElement).attr("regionid");
	this.regionElement	= regionElement;
	this.width	= $(regionElement).width();
	this.height = $(regionElement).height();
	
	var regionHeight = $(regionElement).height();
	var arrowsTop = regionHeight / 2 - 28;
	var regionid = this.regionid;
	
	this.previewElement = $('.preview',regionElement);
	this.previewContent = $('.previewContent', this.previewElement);

	// Setup global control tracking
	Preview.instances[this.regionid] = this;
	
	// Create the Nav Buttons
	$('.previewNav',this.previewElement)	
		.append("<div class='prevSeq' style='position:absolute; left:1px; top:"+ arrowsTop +"px'><img src='img/arrow_left.gif' /></div>")
		.append("<div class='nextSeq' style='position:absolute; right:1px; top:"+ arrowsTop +"px'><img src='img/arrow_right.gif' /></div>");

	$('.prevSeq', $(this.previewElement)).click(function() {
		var preview = Preview.instances[regionid];
		var maxSeq 	= $('#maxSeq', preview.previewContent[0]).val();
				
		var currentSeq = preview.seq;
		currentSeq--;
		
		if (currentSeq <= 0)
		{
			currentSeq = maxSeq;
		}
		
		preview.SetSequence(currentSeq);
	});
	
	$('.nextSeq', $(this.previewElement)).click(function() {
		var preview = Preview.instances[regionid];
		var maxSeq 	= $('#maxSeq', preview.previewContent[0]).val();
		
		var currentSeq = preview.seq;
		currentSeq++;
		
		if (currentSeq > maxSeq)
		{
			currentSeq = 1;
		}
		
		preview.SetSequence(currentSeq);
	});	
	
	this.SetSequence(1);
}

Preview.instances = {};

Preview.prototype.SetSequence = function(seq)
{
	this.seq = seq;
	
	var layoutid 		= this.layoutid;
	var regionid 		= this.regionid;
	var previewContent 	= this.previewContent;

	this.width	= $(this.regionElement).width();
	this.height = $(this.regionElement).height();
	
	//Get the sequence via AJAX
	$.ajax({type:"post", 
		url:"index.php?p=layout&q=RegionPreview&ajax=true", 
		cache:false, 
		dataType:"json", 
		data:{"layoutid":layoutid,"seq":seq,"regionid":regionid,"width":this.width, "height":this.height},
		success:function(response) {
		
			if (response.success) {
				// Success - what do we do now?
				$(previewContent).html(response.html);
			}
			else {
				// Why did we fail? 
				if (response.login) {
					// We were logged out
		            LoginBox(response.message);
		            return false;
		        }
		        else {
		            // Likely just an error that we want to report on
		            $(previewContent).html(response.html);
		        }
			}
			return false;
		}
	});
}