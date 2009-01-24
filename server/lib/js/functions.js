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

/**
 * Sets up the form dialog and then fills it with a load_form call
 * Gets the href from the source parameter
 * @param {Object} source
 * @param {Object} dialogTitle
 * @param {Object} exec_filter_callback
 * @param {Object} init_callback
 */
var init_button = function(source, dialogTitle, exec_filter_callback, init_callback) {
	
	//clear down the html before opening
	$('#div_dialog').dialog("close");
	$('#div_dialog').html("");
	
	//do any changes to the dialog form here
	$('#div_dialog').parent().children().each(function(){
		$(".ui-dialog-title", this).html(dialogTitle);
	});

	//call the load form
	load_form($(source).attr('href'),$('#div_dialog'), exec_filter_callback, init_callback);
	
	return false;
}

function exec_filter(filter, outputDiv) {
	
	//if the filter does not exist - exit without doing anything
	if ($('#'+filter).size() == 0) {
		return;
	}
	
    var openString = "index.php";
		
	$.ajax({type:"post", url:"index.php?ajax=true", datatype:"html", data:$('#'+filter).formSerialize(), 
		success:function(response) {
			
			var transport = response.split('|');
			
			if (transport[0] == '2') { //login
				//need to construct the login form on the page
				$('#div_dialog').html(transport[1]);
				
				//capture the form submit
				$('.dialog_form').submit(function() {
					ajax_submit_form($(this), $('#div_dialog'), '');
			
					return false;
				});
				
				//do any changes to the dialog form here
				$('#div_dialog').parent().children().each(function(){
					$(".ui-dialog-title", this).html("Please Login");
				});
				$('#div_dialog').parent().parent().width("450px").height("320px");
				
				$('#div_dialog').dialog("open");
				
				$('#username','#div_dialog').focus();
				
				return false;
			}
			
			$('#'+outputDiv).html(response);
			
			if (outputDiv == 'dialog_grid') {
				if($('.dialog_table table tbody').html()!="") {
					$(".dialog_table table").tablesorter({sortList:[[1,0]],widthFixed: true}).tablesorterPager({container: $("#paging_dialog")});
				}				
			}
			else if (outputDiv == 'pages_grid') {
				if($('.dialog_table table tbody').html()!="") {
					$(".dialog_table table").tablesorter({sortList:[[1,0]],widthFixed: true}).tablesorterPager({container: $("#paging_dialog")});
				}				
			}
			else {
				if($('.info_table table tbody').html()!="") {
					$(".info_table table").tablesorter({sortList:[[0,0]],widthFixed: true}).tablesorterPager({container: $("#paging")});
				}
			}
			
			// Temporary hack to allow the use of the new Xibo Core.js with the old exec filter tables.
			// All exec filter calls should eventually be replaced with XiboGrid declarations (which are bound by XiboInitialise)
			XiboInitialise('#data_table');
		}
	});

	return false; //so that we dont submit forms
}

/**
 * Loads the results from Url into Div
 * @param {Object} url
 * @param {Object} div
 * @param {Object} callback
 * @param {Object} onSuccess
 */
var load_form = function(url, div, callback, onSuccess) {
	
	$.ajax({type:"get", url:url+"&ajax=true", cache:false, datatype:"html",
		success:function(transport) {
			
			var response = transport.split('|');
			
			if (response[0] == '0') { //success
				$(div).html(response[1]);
				
				//capture the form submit
				$('form.dialog_form').submit(function() {
					ajax_submit_form($(this), div, callback);
			
					return false;
				});
				
				//capture the form submit
				$('form.dialog_text_form').submit(function() {
					var inst = FCKeditorAPI.GetInstance("ta_text");
					
					$('#ta_text').val(inst.GetHTML());

					ajax_submit_form($(this), div, callback);
			
					return false;
				});
							
				//capture the file form submit
				$('.dialog_file_form').submit(function() {
										
					ajax_submit_form($(this), div, callback);
					
					return false;
				});
				
				if (onSuccess != "" && onSuccess != undefined) {
					eval(onSuccess)(name);
				}
				
				$('#div_dialog').dialog("open");
				
				var index = 0;
				
				// Focus in the first form element
				$('input[@type=text]','#div_dialog').eq(0).focus();
			}
			else if (response[0] == '1') { //fail
				alert(response[1]);
			}
			else if (response[0] == '2') { //login
				//need to construct the login form on the page
				$(div).html(response[1]);
				
				//capture the form submit
				$('.dialog_form').submit(function() {
					ajax_submit_form($(this), div, callback);
			
					return false;
				});
				
				//do any changes to the dialog form here
				$('#div_dialog').parent().children().each(function(){
					$(".ui-dialog-title", this).html("Please Login");
				});
				$('#div_dialog').parent().parent().width("450px").height("320px");
				
				$('#div_dialog').dialog("open");
				
				$('#username','#div_dialog').focus();
			}
			else {
				SystemMessage("An unknown error has occured loading the form. Please Refresh.");
			}
		}
	});
			
	return false;
}

//submits a form via ajax
ajax_submit_form  = function(form, parentDialog, callback) {

	var url_loc = $(form).attr("action") + "&ajax=true";
	
	$.ajax({type:"post", url:url_loc, cache:false, datatype:"html", data:$(form).serialize(), 
		success:function(transport) {
		
			var response = transport.split('|');
			
			if (response[0] == '0') {
				//success
				$(parentDialog).dialog("close");
				
				if (callback != "" && callback != undefined) {
					eval(callback)(name);
				}
				
				SystemMessage(response[1]);
			}
			else if (response[0] == '1') {
				//failure
				SystemMessage(response[1]);
			}
			else if (response[0] == '2') { //login
				SystemMessage("You need to login");
				//success
				$(parentDialog).dialog("close");
			}
			else if (response[0] == '3') { //redirect
				window.location = response[1];
			}
			else if (response[0] == '4') { //success, but dont close the form
				if (callback != "" && callback != undefined) {
					eval(callback)(name);
				}
				SystemMessage(response[1]);
			}
			else if (response[0] == '5') { //success, refresh
			    SystemMessage(response[1]);
			    window.location.reload();
			}
			else if (response[0] == '6') { //success, load form
			
				//we need: uri, callback, onsubmit
				var uri 		= response[1];
				var cb 			= response[2];
				var onsubmit 	= response[3];
				
				//File forms give the URI back with &amp's in it
				uri = unescape(uri);
			
				load_form(uri, $('#div_dialog'), cb, onsubmit);
			}
			else {
				SystemMessage("An unknown error occured");
			}
			
			return false;
		}
	});
	
	return false; //so we dont then submit the form again
};

var set_form_size = function(width, height) {
	$('#div_dialog').parent().parent().width(width+"px").height(height+"px");
}

/**
 * 
 * @param {Object} width
 * @param {Object} height
 */
function grid_form(source, dialogTitle, exec_filter_callback,filter,output,width, height) {
	
	$('#div_dialog').parent().parent().width(width+"px").height(height+"px");
	
	//clear down the html before opening
	$('#div_dialog').html("").dialog("close");
	
	//do any changes to the dialog form here
	$('#div_dialog').parent().children().each(function(){
	 $(".ui-dialog-title", this).html(dialogTitle);
	});

	//call the load form
    load_form($(source).attr('href'), $('#div_dialog'), exec_filter_callback, function(){
        //init the filter bind
        $(' :input', '#' + filter).change(function(){
            exec_filter(filter, output);
        });
        
        //make sure the form doesnt get submitted using the traditional method
        $('#' + filter).submit(function(){
            return false;
        });
        
        //exec the filter on start
        exec_filter(filter, output);
        
        $('#div_dialog').dialog("open");
        
        return false;
    });
	
	return false;
}

/**
 * Submits a form via AJAX
 * @param {Object} link
 * @param {Object} callback
 */
function ajax_submit_link(link, callback){

    var url_loc = $(link).attr("href") + "&ajax=true";
    
    $.ajax({
        type: "post",
        url: url_loc,
        cache: false,
        datatype: "html",
        success: function(transport){
        
            var response = transport.split('|');
            
            if (response[0] == '0') {
                //success
                $('#div_dialog').dialog("close");
                
                if (callback != "" && callback != undefined) {
                    eval(callback)(name);
                }
                
                SystemMessage(response[1]);
            }
            else 
                if (response[0] == '1') {
                    //failure
                    SystemMessage(response[1]);
                }
                else 
                    if (response[0] == '2') { //login
                        SystemMessage("You need to login");
                        //success
                        $('#div_dialog').dialog("close");
                    }
                    else 
                        if (response[0] == '3') { //redirect
                            window.location = response[1];
                        }
                        else 
                            if (response[0] == '4') { //success, but dont close the form
                                if (callback != "" && callback != undefined) {
                                    eval(callback)(name);
                                }
                                SystemMessage(response[1]);
                            }
                            else 
                                if (response[0] == '5') { //success, refresh
                                    SystemMessage(response[1]);
                                    window.location.reload();
                                }
                                else 
                                    if (response[0] == '6') { //success, load form
                                        //we need: uri, callback, onsubmit
                                        var uri = response[1];
                                        var cb = response[2];
                                        var onsubmit = response[3];
                                        
                                        //File forms give the URI back with &amp's in it
                                        uri = unescape(uri);
                                        
                                        load_form(uri, $('#div_dialog'), cb, onsubmit);
                                    }
                                    else {
                                        SystemMessage("An unknown error occured");
                                    }
            
            return false;
        }
    });
    
    return false; //so we dont then submit the form again
};

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