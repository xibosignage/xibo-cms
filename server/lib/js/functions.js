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
// regular expression to match alphanumeric characters and spaces
var re = /^[\w ]+$/;

if (window.attachEvent) window.attachEvent("onload", sfHover); //if IE6 imitate hover

$(document).ready(function() {

	//help tabs - where they are found
	$('#helptabs').tabs({ fxSlide: true, fxFade: true, fxSpeed: 'fast' });
	
	$(' :input').css("z-index","1");
	$(' :input').css("position","relative");
	$('#notes').css("z-index","2");
	
	//fixes the IE hover and select list problem
	$('#nav li').css("z-index","2");
	$("li ul","#nav").css("z-index","2").bgiframe();
	
	$('#notes').bgiframe();

	//handle the trigger function (we might want to trigger some javascript on the page load)
	var trigger = gup('trigger')
	if (trigger != "")
	{
		eval(trigger)(name);
	}

});

function sfHover () {
	// IF IE6 replace hover
	var sfEls = document.getElementById("nav").getElementsByTagName("LI");
	for (var i=0; i<sfEls.length; i++) {
		sfEls[i].onmouseover=function() {
			this.className+=" sfhover";
		}
		sfEls[i].onmouseout=function() {
			this.className=this.className.replace(new RegExp(" sfhover\\b"), "");
		}
	}
}

/**
 * Checks all the checkboxes for a particular form
 * @param {Object} formid
 * @param {Object} field
 * @param {Object} value
 * @param {Object} button
 */
function checkAll(formid, field, value, button) { //checks all the boxes with the provided name
	
	var form = document.getElementById(formid);
	var checkboxes = form.elements[field];
	if(typeof checkboxes.length == 'undefined') checkboxes = [checkboxes];
	
	for (var i=0;i<checkboxes.length;i++) {
		if(value == 1) {
			checkboxes[i].checked = true;
			document.getElementById(button).disabled = false;
		} else {
			checkboxes[i].checked = false;
			document.getElementById(button).disabled = true;
		}
	}
}

/**
 * Inverts all the checkboxes on a form
 * @param {Object} formid
 * @param {Object} field
 */
function invertChecked(formid, field) { //inverts the selected checkboxes
	
	var form = document.getElementById(formid);
	var checkboxes = form.elements[field];
	if(typeof checkboxes.length == 'undefined') checkboxes = [checkboxes];
	
	for (var i=0; i<checkboxes.length; i++) {

		var value = checkboxes[i].checked;
		
		if(value) {
			checkboxes[i].checked = false;
		} else {
			checkboxes[i].checked = true;
		}
	}	
}

function set_field_value(field, value) { //used on paging buttons
	$('#'+field).attr("value", value);
}

function set_field(field, from_field) { //used on paging dropdown

	$('#'+field).attr("value", $('#'+from_field).attr("value"));
}

//replaces the content of "source" with "target" where content = innerHTML
function replace_content(source, target) {

	$('#'+target).html($('#'+source).html);

	return true;
}

function toggle_div_view(div) {
	
	if ($('#'+div)[0].style.display == "none" || $('#'+div)[0].style.display == "hidden") {
    	$('#'+div).fadeIn("slow");
    }
    else {
    	$('#'+div).fadeOut("slow");
    }
	
	return true;
}

function confirm_delete() {
	return confirm('Are you sure you want to delete?');
}

var set_form_size = function(width,height) {
	$('div_dialog').parent().parent().width(width+"px").height(height+"px");
}

/**
 * Returns the parameter specified in name from the window.location.href
 * @param {Object} name
 */
function gup( name ){  
	name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");  
	var regexS = "[\\?&]"+name+"=([^&#]*)";  
	var regex = new RegExp( regexS );  
	var results = regex.exec( window.location.href );  
	
	if( results == null )    return "";  
	else    return results[1];
}

/**
 * Gets the current window URL
 */
function getUrl() {
	var url = window.location.href;
	url = url.split("?");
	
	return url[0];
}

function fileFormSubmit() {
	// Update the UI to say its submitting
	$('#uploadProgress').fadeIn("slow");
	$('#file_upload').parent().hide();
}

/**
 * 
 * @param {Object} fileName
 * @param {Object} fileId
 * @param {Object} errorCode
 */
function fileUploadReport(fileName, fileId, errorCode) {
	var button = document.getElementById("btnSave");
	var uploadProgress = $('#uploadProgress');
	
	if (errorCode == 0)
	{
		$('#txtFileName').val(fileName);
		$('#hidFileID').val(fileId);
		
		button.disabled = false;
		
		uploadProgress.html("File upload complete.");
	}
	else
	{
		button.disabled = true;
		
		uploadProgress.hide();
		$('#file_upload').parent().show();
		
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