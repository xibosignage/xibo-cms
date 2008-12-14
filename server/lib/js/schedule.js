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
$(document).ready(function() {

	//set the z-index of the preview pane to be above the other content
	$('#whatson_pane').css("z-index", 2).bgiframe();
	
	$('#whatson_pane').css("width", $(window).width()- 20 );
	$('#whatson_pane').css("height", $(window).height()- 70 );
	$('div.scrollingWindow','#whatson_pane').css("height", $(window).height()- 180 );
	
	$('#whatson_close').click(function() {
		toggle_div_view('whatson_pane');
	});
	
	$('#whatson_refresh').click(function() {
		$.ajax({type:"get", url:"index.php?p=schedule&q=whats_on", datatype:"html", 
			success:function(response) {
				$('#whatson_pane .info_table').html(response);
			}
		});
	});
	
	$('#whatson_button').click(function() {
		toggle_div_view('whatson_pane');
	});

});
//END WHATS ON


function submit_form(form_name) {

	var form = $('#'+form_name);

	/* The form fields */
	var form_start = $("#form_starttime").val();
	var form_end = $("#form_endtime").val();
	var href = form.attr("action");
	
	if (form_start > form_end) {
		alert("The start time can not be before the end time");
		return false;
	}
	
	//call the load form
	load_form(href+'&starttime='+form_start+'&endtime='+form_end,$('#div_dialog'), exec_filter_callback, init_callback);
	
	return false;
}

function set_time(hour_period) {
	/* The form fields */
	var form_start = document.getElementById("form_starttime");
	var form_end = document.getElementById("form_endtime");
	
	/* The radio buttons */
	var set_start_time = document.getElementById("heading_select_start");
	var set_end_time = document.getElementById("heading_select_end");
	
	/* The start/end tds */
	var time_for_start = document.getElementById("time_for_start");
	var time_for_end = document.getElementById("time_for_end");
	

	if (set_start_time.checked) {	
		form_start.value = hour_period;
		set_end_time.checked = true;
	}
	else  {
		form_end.value = hour_period;
		set_start_time.checked = true;
	}
	
	
	form_start = document.getElementById("form_starttime");
	form_end = document.getElementById("form_endtime");
	
	time_for_start.innerHTML = format_date(form_start.value);
	time_for_end.innerHTML = format_date(form_end.value)
	
	eval_add_cells(get_hours(form_start.value),get_hours(form_end.value));
	
	return;
}

function format_date(date) {
	
	var d = new Date(date*1000);

	var hours = d.getHours();
	var minutes = d.getMinutes();
	
	if (hours < 10) hours = "0"+hours;
	if (minutes < 10) minutes = "0"+minutes;
	
	
	return hours + ":" + minutes;

}

function get_hours(date) {
	var d = new Date(date*1000);

	var hours = d.getHours();
	
	return hours;
}

function eval_add_cells(start, end) {
	/* Set them all to no color */
	for (var i=0; i<=23; i++) {
		var cell = document.getElementById("add_cell_"+i);
		
		if (i >= start && i <= end) {
			/* Want to set the color of the hour cells based on start and end time */
			//cell.style.backgroundColor = "red;";
			cell.className = "add";
			
		}
		else {
			/* set to no color */
			//cell.style.backgroundColor = "#f5f5f5";
			cell.className = "";
		}
	}

	return true;
}

function day_clicked(date, displayid) {

    var href = 'index.php?p=schedule&sp=add&q=display_form&date='+date+'&starttime='+date+'&endtime='+date+'&displayid='+displayid;

    //clear down the html before opening
	$('#div_dialog').html("");
	
	//do any changes to the dialog form here
	$('#div_dialog').parent().children().each(function(){
	 $(".ui-dialog-title", this).html("Add Event");
	});
	
	$('#div_dialog').parent().parent().width("900px").height("600px");

	//call the load form
	load_form(href,$('#div_dialog'), exec_filter_callback, init_callback);
	
	return true;
}

function exec_filter_callback() {
    //nothing in there, there is no filter
}

function init_callback() {
    //set up any date fields we have with the date picker
    $('.date-pick').datepicker({
		dateFormat: "dd/mm/yy",
		showOn: "button", 
    	buttonImage: "img/calendar.png", 
    	buttonImageOnly: true,
		beforeShow: customRange
	});

	/*if ( $('.date-pick').size() != 0 ) {
		$('#starttime').datepicker("setDate", $('#starttime').fieldValue()[0]);
		$('#endtime').datepicker("setDate", $('#endtime').fieldValue()[0]);		
		$('#rec_range').datepicker("setDate", $('#rec_range').fieldValue()[0]);		
	}*/

	ctlRec();
	
	$('#rec_type').change(function() {
		ctlRec();
	});
}

function customRange(input) { 
    return {minDate: (input.id == "endtime" ? $("#starttime").datepicker("getDate") : null), 
        maxDate: (input.id == "starttime" ? $("#endtime").datepicker("getDate") : null)}; 
} 

function ctlRec() {
	/*
	 * Recurrence
	 * 	If the recurrence type value is NULL then hide the rec_detail and rec_range fields
	 */
	var rec_type 	= $('#rec_type');
	var rec_detail 	= $('#rec_detail');
	var rec_range 	= $('#rec_range');
	
	if (rec_type.val()=="null") {
		rec_detail.parent().parent().hide();
		rec_range.parent().parent().hide();
	}
	else {
		rec_detail.parent().parent().show();
		rec_range.parent().parent().show();
	}
}

function edit_event_callback() {
    init_callback();
    
    set_form_size('900','600');
}