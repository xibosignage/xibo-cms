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
var exec_filter_callback = function(outputDiv) {
	
	exec_filter('filter_form','data_table');
	
	return false;
}

var submit_form_callback = function(outputDiv) {
	
	//Just refresh
	//window.location = window.location.href;
	
	return false;
}

var region_options_callback = function(outputDiv)
{
	set_form_size(830,450);
	
	XiboInitialise('#div_dialog');
	
	//Get all the tooltip_hidden
	$(".tooltip_hidden").parent().hover(function()
	{
		var html = $(".tooltip_hidden",this).html();
		var left = this.offsetLeft - this.offsetParent.scrollLeft;;
		
		//Change the hidden div's content
		$('#tooltip_hover')	.html(html)
							.css("left",left)
							.show();
	}, function() 
	{
		$('#tooltip_hover').hide();
	});
	
	//Make the elements draggable
	$(".timebar_ctl").draggable({
		containment: document.getElementById("timeline_ctl")
	});
	
	$(".mediabreak").droppable({
		accept: ".timebar_ctl",
		drop: function(ev, ui) {
			orderRegion(ui, this);
		}
	});
}

var background_button_callback = function()
{
	//Want to attach an onchange event to the drop down for the bg-image
	var libraryloc = $('#libraryloc').val();
	var fileUrl = $('#bg_image').val();
	
	$('#bg_image_image').attr("src", "index.php?p=module&q=GetImage&file="+"tn_" + fileUrl);
}

var text_callback = function()
{	
	//Create the FCK editor
	var oFCKeditor = new FCKeditor( 'ta_text' ) ;
	oFCKeditor.BasePath = "3rdparty/fckeditor/" ;
	oFCKeditor.ReplaceTextarea();

	var regionid = $("#iRegionId").val();
	var width = $("#region_"+regionid).width();
	var height = $("#region_"+regionid).height();
	
	//Min width
	if (width < 800) width = 800;
	height = height + 75 //the width of the toolbar
	
	$('#ta_text___Frame').attr("width",width+"px");
	$('#ta_text___Frame').attr("height",height+"px");
	
	width = width + 50;
	height = height + 220;	
	
	set_form_size(width, height);
	
	return false; //prevent submit
}

$(document).ready(function() {
	
	//filter form bindings
	exec_filter('filter_form','data_table');
	
	$(' :input', '#filter_form').change(function(){
		
		$('#pages').attr("value","1"); //sets the pages to 1
		
		exec_filter('filter_form','data_table'); //calls the filter form
	});


	/**
	 * Buttons
	 *
	 */
	$('#add_button').click(function() {
		
		init_button(this,'Add Layout',exec_filter_callback, set_form_size(600,350));

		return false;
		
	});
	
	$('#edit_button').click(function() {
		
		init_button(this,'Properties',exec_filter_callback, set_form_size(600,350));

		return false;
		
	});
	
	$('#background_button').click(function() {
		
		init_button(this,'Background Properties',function() {window.location = window.location.href;}, set_form_size(600,250));

		return false;
		
	});
	
	$('#add_slide_button').click(function() {
				
		init_button(this,'Add Slide','',function() {
			slide_refresh_list($('#layoutid'),'styleid');
		});
		
		return false;
		
	});
	// End
	
	//add the tr hover class
	$(".show_slide_table tr").hover(
		function(){
			$(this).addClass("hover");
		},function() {
			$(this).removeClass("hover");
		}
	);
	
	var container = document.getElementById('layout');
	
	$('.region').draggable({containment:container, stop:function(e, ui){
		//Called when draggable is finished
		submitBackground(this);
	}}).resizable({containment:container, minWidth:25, minHeight:25, stop:function(e, ui){
		//Called when resizable is finished
		submitBackground(this);
	}}).contextMenu('regionMenu', {
	    bindings: {
	        'options': function(t) {
	            init_button(t,'Region Options','',region_options_callback)
	        },
			'deleteRegion': function(t) {
	            deleteRegion(t);
	        }
			,
			'setAsHomepage': function(t) {
				var layoutid = $(t).attr("layoutid");
				var regionid = $(t).attr("regionid");
				
	            load_form("index.php?p=user&q=SetUserHomepageForm&layoutid="+layoutid+"&regionid="+regionid, $('#div_dialog'),'',set_form_size(320,150));
	        }
    	}
	});
	
	$('#layout').contextMenu('layoutMenu', {
		bindings: {
			'addRegion': function(t){
				addRegion(t);
			},
			'editBackground': function(t) {
				init_button($('#background_button')[0],'Background Properties',function() {window.location = window.location.href;}, set_form_size(600,250));
			},
			'layoutProperties': function(t) {
				init_button($('#edit_button')[0],'Properties',exec_filter_callback, set_form_size(600,350));
			},
			'templateSave': function(t) {
				var layoutid = $(t).attr("layoutid");
			
				load_form("index.php?p=template&q=TemplateForm&layoutid="+layoutid, $('#div_dialog'),'',set_form_size(600,250));
			}
		}
	});
	
	
	// Preview
	$('.region').each(function(){
    	var preview = new Preview(this);
	});

});

/**
 * Adds a region to the specified layout
 * @param {Object} layout
 */
function addRegion(layout)
{
	var layoutid = $(layout).attr("layoutid");
	
	$.ajax({type:"post", url:"index.php?p=layout&q=AddRegion&layoutid="+layoutid+"&ajax=true", cache:false, datatype:"html", 
		success:function(transport) {
		
			var response = transport.split('|');
			
			if (response[0] == '0') {
				//success
				//Post notice somewhere?
			}
			else if (response[0] == '1') {
				//failure
				alert(response[1]);
			}
			else if (response[0] == '2') { //login
				alert("You need to login");
			}
			else if (response[0] == '3') { //redirect
				window.location = response[1];
			}
			else {
				alert("An unknown error occured");
			}
			
			return false;
		}
	});
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
	
	$.ajax({type:"post", url:"index.php?p=layout&q=RegionChange&layoutid="+layoutid+"&ajax=true", cache:false, datatype:"html", 
		data:{"width":width,"height":height,"top":top,"left":left,"regionid":regionid},
		success:function(transport) {
		
			var response = transport.split('|');
			
			if (response[0] == '0') {
				//success
				//Post notice somewhere?
			}
			else if (response[0] == '1') {
				//failure
				alert(response[1]);
			}
			else if (response[0] == '2') { //login
				alert("You need to login");
			}
			else if (response[0] == '3') { //redirect
				window.location = response[1];
			}
			else {
				alert("An unknown error occured");
			}
			
			return false;
		}
	});
}

/**
 * Deletes a region
 */
function deleteRegion(region)
{
	var regionid = $(region).attr("regionid");
	var layoutid = $(region).attr("layoutid");

	load_form("index.php?p=layout&q=DeleteRegionForm&layoutid="+layoutid+"&regionid="+regionid, $('#div_dialog'),'','');
}

/**
 * Reorders the Region specified by the timebar and its position
 * @param {Object} timeBar
 * @param {Object} mediaBreak
 */
function orderRegion(timeBar, mediaBreak)
{
	var layoutid = $(timeBar.element.offsetParent).attr("layoutid");
	var regionid = $(timeBar.element.offsetParent).attr("regionid");
	var mediaid = $(timeBar.element).attr("mediaid");
	var sequence = $(mediaBreak).attr("breakid");
	
	$.ajax({type:"post", url:"index.php?p=layout&q=RegionOrder&layoutid="+layoutid+"&callingpage=layout&ajax=true", cache:false, datatype:"html", 
		data:{"mediaid":mediaid,"sequence":sequence,"regionid":regionid},
		success:function(transport) {
		
			var response = transport.split('|');
			
			if (response[0] == '0') 
			{
				//success
				//Post notice somewhere?
			}
			else if (response[0] == '1') //failure
			{
				
				alert(response[1]);
			}
			else if (response[0] == '2') //login
			{ 
				alert("You need to login");
			}
			else if (response[0] == '3') 
			{ 
				window.location = response[1]; //redirect
			}
			else if (response[0] == '6') //success, load form
			{ 
				//we need: uri, callback, onsubmit
				var uri 		= response[1];
				var callback 	= response[2];
				var onsubmit 	= response[3];
				
				load_form(uri, $('#div_dialog'),callback,onsubmit);
			}
			else 
			{
				alert("An unknown error occured");
			}
			
			return false;
		}
	});
}

/**
 * Handles the tRegionOptions trigger
 */
function tRegionOptions()
{
	var regionid = gup("regionid");
	var layoutid = gup("layoutid");
	
	load_form('index.php?p=layout&layoutid='+layoutid+'&regionid='+regionid+'&q=RegionOptions', $('#div_dialog'),'',region_options_callback);
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
	
	// Bind the events to the Nav Buttons
	$(regionElement).hover(function(){
		//In
		$('.previewNav, .info', regionElement).fadeIn("slow");
	}, function(){
		//Out
		$('.previewNav, .info', regionElement).fadeOut("slow");
	});
	
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
	var previewElement	= this.previewElement;
	var maxSeq 			= $('#maxSeq', previewContent[0]).val();
	
	this.width	= $(this.regionElement).width();
	this.height = $(this.regionElement).height();
	
	//Get the sequence via AJAX
	$.ajax({type:"post", 
		url:"index.php?p=layout&q=RegionPreview&ajax=true", 
		cache:false, 
		datatype:"html", 
		data:{"layoutid":layoutid,"seq":seq,"regionid":regionid,"width":this.width, "height":this.height},
		success:function(transport) {
		
			var response = transport.split('|');
			
			if (response[0] == '0') 
			{
				// success
				// Attach to the preview div
				$(previewContent).html(response[1]);
			}
			else if (response[0] == '1') //failure
			{
				
				$(previewContent).html(response[1]);
			}
			else if (response[0] == '2') //login
			{ 
				SystemMessage("You need to login");
			}
			else 
			{
				$(previewContent).html("An unknown error occured");
			}
			
			return false;
		}
	});
}
