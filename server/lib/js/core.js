/**
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008,2009 Daniel Garner
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
	XiboInitialise();
});

/**
 * Initialises the page/form
 * @param {Object} Optional: scope (the form or page)
 */
function XiboInitialise(scope){
	
	// If the scope isnt defined then assume the entire page
	if (scope == undefined || scope == "") {
		scope = " ";
	}

	// Search for any grids on the page and render them 
	$(scope + " .XiboGrid").each(function(){
		
		var gridId = $(this).attr("id");
		
		// For each one setup the filter form bindings
		$('.XiboFilter form :input', this).change(function(){
			XiboGridRender(gridId);
		});
		
		$('.XiboFilter form', this).submit(function(){
			// We dont actually want the form to be submittable (just in case)
			return false;
		});
		
		// Render
		XiboGridRender(gridId);
	});
	
	// Search for any Buttons / Links on the page that are used to load forms
	$(scope + " .XiboFormButton").click(function(){
		
		var formId = $(this).attr("id");
		
		XiboFormRender(formId);
		
		return false;
	});
	
	/*// Search for any forms that will need submitting
    $('form.dialog_form').submit(function(){
        ajax_submit_form($(this), div, callback);
        
        return false;
    });
    
    // Capture the form submit
    $('form.dialog_text_form').submit(function(){
        var inst = FCKeditorAPI.GetInstance("ta_text");
        
        $('#ta_text').val(inst.GetHTML());
        
        ajax_submit_form($(this), div, callback);
        
        return false;
    });
    
    // Capture the file form submit
    $('.dialog_file_form').submit(function(){
    
        ajax_submit_form($(this), div, callback);
        
        return false;
    });*/
}

/**
 * Renders any Xibo Grids that are detected
 * @param {Object} gridId
 */
function XiboGridRender(gridId){

    // Grid ID tells us which grid we need to render
	var gridDiv 	= '#' + gridId;
    var filter 		= $('#' + gridId + ' .XiboFilter form');
    var outputDiv 	= $('#' + gridId + ' .XiboData ');
    
    // AJAX call to get the XiboData	
    $.ajax({
        type: "post",
        url: "index.php?ajax=true",
        dataType: "json",
        data: filter.formSerialize(),
        success: function(response){
        
            var respHtml;
            
            if (response.success) {
                respHtml = response.html;
            }
            else {
                // Login Form needed?
                if (response.login) {
                    LoginBox(response.message);
                    return false;
                }
                else {
                    // Just an error we dont know about
                    respHtml = response.message;
                }
            }
            
            $(outputDiv).html(respHtml);
            
            // Do we need to do anything else now?
            if (response.paging) {
                // Call paging
                var pagingDiv = response.pagingDiv;
                
                if ($('tbody', pagingDiv).html() != "") {
                    $(pagingDiv).tablesorter({
                        sortList: [[1, 0]],
                        widthFixed: true
                    })
                };
			}
			
			// Call XiboInitialise for this form
			XiboInitialise(gridDiv);
            
            return false;
        }
    });
    
    //so that we dont submit forms
    return false;
}

/**
 * Renders the formid provided
 * @param {Number} formId
 */
function XiboFormRender(formId) {
	
	// Use the formId to get the URL we need to call for this form
	var url = $('#'+formId).attr("href");
	
	// Prepare the Dialog
	$('#div_dialog').dialog("close");
	$('#div_dialog').html("");
	
	// Call with AJAX
    $.ajax({
        type: "get",
        url: url + "&ajax=true",
        cache: false,
        dataType: "json",
        success: function(response){
        
			// Was the Call successful
            if (response.success) {
				// Set the dialog HTML to be the response HTML
                $('#div_dialog').html(response.html);
				
				// Is there a title for the dialog?
				if (response.dialogTitle != undefined && response.dialogTitle != "") {
					// Set the dialog title
					$('#div_dialog').parent().children().each(function(){
						$(".ui-dialog-title", this).html(response.dialogTitle);
					});
				}
				
				// Do we need to alter the dialog size?
				if (response.dialogSize) {
					$('#div_dialog').parent().parent().width(response.dialogWidth).height(response.dialogHeight);
				}
				
				$('#div_dialog').dialog("open");
                             
                // Focus in the first form element
                $('input[@type=text]', '#div_dialog').eq(0).focus();
                
                // Do we have to call any functions due to this success?
                if (response.callBack != "" && response.callBack != undefined) {
                    eval(callBack)(name);
                }
				
				// Call Xibo Init for this form
				XiboInitialise("#div_dialog");
            }
			else {
				// Login Form needed?
	            if (response.login) {
	                LoginBox(response.message);
	                return false;
	            }
	            else {
	                // Just an error we dont know about
	                SystemMessage(response.message);
	            }
			}
            
            return false;
        }
    });
	
	// Dont then submit the link/button	
	return false;
}

/**
 * Display a login box
 */
function LoginBox(message){
    $('#div_dialog').html(message);
    
    //capture the form submit
    $('.dialog_form').submit(function(){
        ajax_submit_form($(this), $('#div_dialog'), '');
        
        return false;
    });
    
    //do any changes to the dialog form here
    $('#div_dialog').parent().children().each(function(){
        $(".ui-dialog-title", this).html("Please Login");
    });
	
    $('#div_dialog').parent().parent().width("450px").height("320px");
    
    $('#div_dialog').dialog("open");
    
    $('#username', '#div_dialog').focus();
    
    return;
}