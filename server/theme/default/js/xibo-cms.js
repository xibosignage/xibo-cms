/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
$(document).ready(function() {

    // Code from: http://stackoverflow.com/questions/7585351/testing-for-console-log-statements-in-ie/7585409#7585409
    // Handles console.log calls when there is no console
    if ( ! window.console ) {

        (function() {
          var names = ["log", "debug", "info", "warn", "error",
              "assert", "dir", "dirxml", "group", "groupEnd", "time",
              "timeEnd", "count", "trace", "profile", "profileEnd"],
              i, l = names.length;

          window.console = {};

          for ( i = 0; i < l; i++ ) {
            window.console[ names[i] ] = function() {};
          }
        }());
    }
	
	setInterval("XiboPing('index.php?p=clock&q=GetClock')", 1000 * 60); // Every minute
	
	setInterval("XiboPing('index.php?p=index&q=PingPong')", 1000 * 60 * 3); // Every 3 minutes	

    $.ajaxSetup({
    beforeSend:function(){
        $("#xibo-loading-gif").css({top:'50%',left:'50%',margin:'-'+($('#myDiv').height() / 2)+'px 0 0 -'+($('#myDiv').width() / 2)+'px'}).show();
    },
    complete:function(){
        // hide gif here, eg:
        $("#xibo-loading-gif").hide();
    }
});

	XiboInitialise("");
});

/**
 * Initialises the page/form
 * @param {Object} scope (the form or page)
 */
function XiboInitialise(scope) {

    // If the scope isnt defined then assume the entire page
    if (scope == undefined || scope == "") {
        scope = " ";
    }

    // Search for any grids on the page and render them
    $(scope + " .XiboGrid").each(function() {

        var gridId = $(this).attr("id");
        
        // Keep this filter form open?
        if ($('.XiboFilter form :input.XiboFilterPinned', this).length > 0) {
            if ($('.XiboFilter form :input.XiboFilterPinned', this).is(':checked')) {
                $('.XiboFilter', this).children(':first').show();
            }
            else {
                $('.XiboFilter', this).children(':first').hide();
            }
        }
        
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
    $(scope + " .XiboFormButton").click(function() {

        var formUrl = $(this).attr("href");

        XiboFormRender(formUrl);

        return false;
    });

    // Search for any Buttons that redirect to another page
    $(scope + " .XiboRedirectButton").click(function() {

        window.location = $(this).attr("href");

    });

    // Search for any Buttons / Linkson the page that are used to load hover tooltips
    $(scope + " .XiboHoverButton").hover(
        function(e){

            var formUrl = $(this).attr("href");

            XiboHoverRender(formUrl, e.pageX, e.pageY);

            return false;
        },
        function(){

            // Dont do anything on hover off - the hover on deals with
            // destroying itself.
            return false;
        }
    );

    // Search for any forms that will need submitting
    // NOTE: The validation plugin does not like binding to multiple forms at once.
    $(scope + ' .XiboForm').validate({
        submitHandler: XiboFormSubmit,
        highlight: function(element) {
			$(element).closest('.control-group').removeClass('success').addClass('error');
		},
		success: function(element) {
			element
				.text('OK!').addClass('valid')
				.closest('.control-group').removeClass('error').addClass('success');
		}
    });

    // Links that just need to be submitted as forms
    $(scope + ' .XiboAjaxSubmit').click(function(){
		
		$.ajax({
			type: "post", 
			url: $(this).attr("href") + "&ajax=true", 
			cache:false, 
			dataType:"json",
			success: XiboSubmitResponse
		});

		return false;
    });

    // Forms that we want to be submitted without validation.
    $(scope + ' .XiboAutoForm').submit( function() {
        XiboFormSubmit(this);

        return false;
    });

    // Search for any text forms that will need submitting
    $(scope + ' .XiboTextForm').validate({
        submitHandler: XiboFormSubmit,
        highlight: function(element) {
            $(element).closest('.control-group').removeClass('success').addClass('error');
        },
        success: function(element) {
            element
                .text('OK!').addClass('valid')
                .closest('.control-group').removeClass('error').addClass('success');
        }
    });

    // Search for any help enabled elements
    $(scope + " .XiboHelpButton").click(function(){

        var formUrl = $(this).attr("href");

        XiboHelpRender(formUrl);

        return false;
    });

    // Search for any charts
    $(scope + " .flot-chart").each(function() {
        
        var id = $(this).attr("id");
        var data = eval(id);

        $.plot("#" + id, data.points, data.config);

    });
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
        data: filter.serialize(),
        success: function(response) {

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

            // Do we have to call any functions due to this success?
            if (response.callBack != "" && response.callBack != undefined) {
                eval(response.callBack)(gridId);
            }

            // Call XiboInitialise for this form
            XiboInitialise(gridDiv);
            
            // Do we have rows in the table?
            var sortingDiv = '#'+ gridId + ' ' + response.sortingDiv;
            var hasRows = ($('tbody', sortingDiv).html() != "");

            // Do we need to do anything else now?
            if (response.sortable) {
                // See if we have the order stored
                var sortOrder = $('#' + gridId).data("sorting");
                if (sortOrder == undefined)
                    sortOrder = [[response.initialSortColumn,response.initialSortOrder]];
                
                if (hasRows) {
                    $(sortingDiv).tablesorter({
                        sortList: sortOrder,
                        widthFixed: true,
                        theme: 'blue'
                    });
                    
                    $(sortingDiv).on('sortEnd', function(e) { 
                        // Store on the XiboGrid
                        $('#' + gridId).data("sorting", e.target.config.sortList);
                    });
                }
            }
            
            // Do we need to add a pager?
            if (response.paging && response.sortable) {
                
                // See if we have a page number
                var pageNumber = $('#' + gridId).data("paging"); 
                if (pageNumber == undefined)
                    pageNumber = 0;
                
                if (response.pageNumber != 0)
                    pageNumber = response.pageNumber;
                
                if ($("#XiboPager_" + gridId).length > 0 && hasRows) {
                    $("#XiboPager_" + gridId).show();
                    
                    $(sortingDiv + ".tablesorter").tablesorterPager({
                       container: $("#XiboPager_" + gridId),
                       positionFixed: false,
                       page: pageNumber,
                       size: response.pageSize
                    });
                       
                    $(sortingDiv).on('pagerComplete', function(e,c) {
                        $('#' + gridId).data("paging", c.page);
                    });
                }
                else {
                    $("#XiboPager_" + gridId).hide();
                }
            }
            else {
                $("#XiboPager_" + gridId).hide();
            }

            return false;
        }
    });

    //so that we dont submit forms
    return false;
}

/**
 * Renders the formid provided
 * @param {String} formUrl
 */
function XiboFormRender(formUrl) {

	// Currently only support one of these at once.
	bootbox.hideAll();

    // Call with AJAX
    $.ajax({
        type: "get",
        url: formUrl + "&ajax=true",
        cache: false,
        dataType: "json",
        success: function(response) {

            // Was the Call successful
            if (response.success) {

                // Set the dialog HTML to be the response HTML
                var message = response.html;

                var dialogTitle = "Xibo";

                // Is there a title for the dialog?
                if (response.dialogTitle != undefined && response.dialogTitle != "") {
                    // Set the dialog title
                    dialogTitle =  response.dialogTitle;
                }

                // Buttons?
                var buttons = [];

                if (response.buttons != '') {
                    $.each(
                        response.buttons,
                        function(index, value) {
                            var extrabutton = {};

                            extrabutton.label = index;
                            extrabutton.callback = function(){
                                eval(value);

                                // Keep the modal window open!
                                return false;
                            }

                            buttons.push(extrabutton);
                        }
                        );
                }

                var id = new Date().getTime();

                // Create the dialog with our parameters
                var dialog = bootbox.dialog(
                		response.html,
                		buttons, {
            				"header": dialogTitle,
                            "animate": false
                		}
                	).attr("id", id);

                if (response.dialogClass != '') {
                	dialog.addClass(response.dialogClass);
                }

                // Do we have to call any functions due to this success?
                if (response.callBack != "" && response.callBack != undefined) {
                    eval(response.callBack)(dialog);
                }

                // Focus in the first form element
                if (response.focusInFirstInput) {
                    $('input[type=text]', dialog).eq(0).focus();
                }

                // Do we need to do anything else now?
                if (response.sortable) {
                    // Call paging
                    if ($(response.sortingDiv + ' tbody', dialog).html() != "") {
                        $(response.sortingDiv, dialog).tablesorter({
                            sortList: [[0, 0]],
                            widthFixed: true
                        })
                    }
                }

                // Call Xibo Init for this form
                XiboInitialise("#"+dialog.attr("id"));
            }
            else {
                // Login Form needed?
                if (response.login) {
                    LoginBox(response.message);

                    return false;
                }
                else {
                    // Just an error we dont know about
                    if (response.message == undefined) {
                        SystemMessage(response);
                    }
                    else {
                        SystemMessage(response.message);
                    }
                }
            }

            return false;
        },
        error: function(response) {
            SystemMessage(response.responseText);
        }
    });

    // Dont then submit the link/button
    return false;
}

/**
 * Xibo Ping
 * @param {String} url
 */
function XiboPing(url, updateDiv) {

    // Call with AJAX
    $.ajax({
        type: "get",
        url: url + "&ajax=true",
        cache: false,
        dataType: "json",
        success: function(response){

            // Was the Call successful
            if (response.success) {

                if (updateDiv != undefined) {
                    $(updateDiv).html(response.html);
                }
            }
            else {
                // Login Form needed?
                if (response.login) {
                    
                    LoginBox(response.message);
                    
                    return false;
                }

                if (response.clockUpdate) {
                    XiboClockUpdate(response.html);
                }
            }

            return false;
        }
    });

    // Dont then submit the link/button
    return false;
}

/**
 * Updates the Clock with the latest time
 * @param {Object} time
 */
function XiboClockUpdate(time)
{
    $('#XiboClock').html(time);

    return;
}

/**
 * Submits the Form
 * @param {Object} form
 */
function XiboFormSubmit(form) {
    // Get the URL from the action part of the form)
    var url = $(form).attr("action") + "&ajax=true";

    // Pull any text editor instances we have
    for (var editor in CKEDITOR.instances) {

        //console.log("Name: " + editor);
        //console.log("Content: " + CKEDITOR.instances[editor].getData());

        // Set the appropriate text editor field with this data.
        $("#" + editor).val(CKEDITOR.instances[editor].getData());
    }

    $.ajax({
        type:"post",
        url:url,
        cache:false,
        dataType:"json",
        data:$(form).serialize(),
        success: function(xhr, textStatus, error) {
        	
        	XiboSubmitResponse(xhr, form);
        }
    });

    return false;
}

/**
 * Handles the submit response from an AJAX call
 * @param {Object} response
 */
function XiboSubmitResponse(response, form) {
	
    // Did we actually succeed
    if (response.success) {
        // Success - what do we do now?

        // We might need to keep the form open
        if (!response.keepOpen) {
            bootbox.hideAll();
        }

        // Should we display the message?
        if (!response.hideMessage) {
            if (response.message != '')
                SystemMessage(response.message, true);
        }

        // Do we need to fire a callback function?
        if (response.callBack != null && response.callBack != "") {
            eval(response.callBack)(name);
        }

        // Do we need to load a new form?
        if (response.loadForm) {
            // We need: uri, callback, onsubmit
            var uri = response.loadFormUri;

            // File forms give the URI back with &amp's in it
            uri = unescape(uri);

            XiboSwapDialog(uri);
        }

        // Should we refresh the window or refresh the Grids?
        if (response.refresh) {
            // We need to refresh - check to see if there is a new location provided
            if (response.refreshLocation == undefined || response.refreshLocation == "") {
                // If not refresh the current location
                window.location.reload();
            }
            else {
                // Refresh to the new location
                window.location = response.refreshLocation;
            }
        }
        else {
            // We should refresh the grids (this is a global refresh)
            $(" .XiboGrid").each(function(){

                var gridId = $(this).attr("id");

                // Render
                XiboGridRender(gridId);
            });
        }
    }
    else {
        // Why did we fail?
        if (response.login) {
            // We were logged out
            LoginBox(response.message);
        }
        else {
            // Likely just an error that we want to report on
            SystemMessageInline(response.message, $(form).closest(".modal"));
        }
    }

    return false;
}

/**
 * Renders the formid provided
 * @param {String} formId
 */
function XiboHelpRender(formUrl) {

	// Call with AJAX
    $.ajax({
        type: "get",
        url: formUrl + "&ajax=true",
        cache: false,
        dataType: "json",
        success: function(response){

            // Was the Call successful
            if (response.success) {
                
                var dialogTitle = "Xibo Help";
                
                // Is there a title for the dialog?
                if (response.dialogTitle != undefined && response.dialogTitle != "") {
                    // Set the dialog title
                    dialogTitle =  response.dialogTitle;
                }

                // Create the dialog with our parameters
                bootbox.dialog(
                		response.html,
                		[], {
            				"header": dialogTitle
                		}
                	).addClass(
                		'modal-big'
                	).addClass(
                        'help-modal-big'
                    );

                // Adjust the height of the iframe
                var height = $(".full-iframe").parent().parent().height(); 
                $(".full-iframe").height(height - 80);
                $(".full-iframe").parent().css("max-height", (height - 70) + "px");
            }
            else {
                // Login Form needed?
                if (response.login) {
                    LoginBox(response.message);
                    return false;
                }
                else {
                    // Just an error we dont know about
                    if (response.message == undefined) {
                        SystemMessage(response);
                    }
                    else {
                        SystemMessage(response.message);
                    }
                }
            }

            return false;
        }
    });

    // Dont then submit the link/button
    return false;
}

/**
 * Renders a Hover window and sets up events to destroy the window.
 */
function XiboHoverRender(url, x, y)
{
    // Call some AJAX
    // TODO: Change this to be hover code
    $.ajax({
        type: "get",
        url: url + "&ajax=true",
        cache: false,
        dataType: "json",
        success: function(response){

            // Was the Call successful
            if (response.success) {

                var dialogWidth = "500";
                var dialogHeight = "500";

                // Do we need to alter the dialog size?
                if (response.dialogSize) {
                    dialogWidth 	= response.dialogWidth;
                    dialogHeight	= response.dialogHeight;
                }

                // Create the the popup bubble with our parameters
                $("body").append("<div class=\"XiboHover\"></div>");

                $(".XiboHover").css("position", "absolute").css(
                {
                    display: "none",
                    width:dialogWidth,
                    height:dialogHeight,
                    top: y,
                    left: x
                }
                ).fadeIn("slow").hover(
                    function(){
                        return false
                    },
                    function(){
                        $(".XiboHover").hide().remove();
                        return false;
                    }
                    );

                // Set the dialog HTML to be the response HTML
                $('.XiboHover').html(response.html);

                // Do we have to call any functions due to this success?
                if (response.callBack != "" && response.callBack != undefined) {
                    eval(response.callBack)(name);
                }

                // Call Xibo Init for this form
                XiboInitialise(".XiboHover");

            }
            else {
                // Login Form needed?
                if (response.login) {
                    LoginBox(response.message);
                    return false;
                }
                else {
                    // Just an error we dont know about
                    if (response.message == undefined) {
                        SystemMessage(response);
                    }
                    else {
                        SystemMessage(response.message);
                    }
                }
            }

            return false;
        }
    });

    // Dont then submit the link/button
    return false;
}

/**
 * Closes the dialog window
 */
function XiboDialogClose() {
    bootbox.hideAll();
}

function XiboSwapDialog(formUrl) {
    bootbox.hideAll();
    XiboFormRender(formUrl);
}

function XiboRefreshAllGrids() {
    // We should refresh the grids (this is a global refresh)
    $(" .XiboGrid").each(function(){

        var gridId = $(this).attr("id");

        // Render
        XiboGridRender(gridId);
    });
}

/**
 * Display a login box
 * @param {String} message
 */
function LoginBox(message) {

    // Reload the page (appending the message)
    window.location.href = window.location.href;
    location.reload(false);
}

/**
 * Displays the system message
 * @param {String} messageText
 * @param {Bool} success
 */
function SystemMessage(messageText, success) {

    if (messageText == '' || messageText == null) 
    	return;

    var options = {};
	options.backdrop = false;

	// Buttons
	var buttons = [];

	// Only add certain things
	if (!success) {
		options.header = 'Application Message';

		buttons.push({
		label: 'Close',
			callback: function() {
				dialog.modal('hide');
			}
		});
	}

	// Open dialog
    var dialog = bootbox.dialog(
    		messageText, buttons, options
    	);

    if (success) {    
	    // Close after 1 second
    	setTimeout(function() {
        	dialog.modal('hide');
    	}, 2000);
	}
}

/**
 * Displays the system message
 * @param {String} messageText
 * @param {Bool} success
 */
function SystemMessageInline(messageText, modal) {

    if (messageText == '' || messageText == null) 
    	return;

    // TODO: if modal is null (or not a form), then pick the nearest .text error instead.
    if (modal == undefined || modal == null || modal.length == 0)
        modal = $(".modal");

    // Remove existing errors
    $(".text-error", modal).remove();

    $("<div/>", {
    	class: "well text-error text-center",
    	html: messageText
    }).appendTo(modal.find(".modal-footer"));
}

/**
 * Toggles the FilterForm view
 */
function ToggleFilterView(div)
{
    if ($('#'+div).css("display") == "none") {
        $('#'+div).fadeIn("slow");
    }
    else {
        $('#'+div).fadeOut("slow");
    }

    return false;
}