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
	
	//filter form bindings
	exec_filter('filter_form','data_table');
	
	$(' :input', '#filter_form').change(function(){
		
		$('#pages').attr("value","1"); //sets the pages to 1
		
		exec_filter('filter_form','data_table'); //calls the filter form
	});
	
	$('.date-pick')
				.datePicker({clickInput:true, createButton:false})
				.bind(
					'dateSelected',
					function(e, selectedDate, $td)
					{
						send_form($(this).parent());
					}
				)
				.each (function() {
					var d = Date.fromString($(this).fieldValue()[0]);
					
					if (d) {
						$(this).dpSetSelected($(this).fieldValue()[0]);
					}
				});
	
	//on change			
	$(' :input[@name=value]','form.dataset_data').change(function(){
		//send the form
		send_form($(this).parent());
	});

	//null the submit
	$('form.dataset_data').submit(function(){	
		return false;
	});
	
	//CSV import form bindings
	$('form.csv_nav').submit(function(){
		csv_import('csv_import', this);
		
		return false;
	});
	
	$('#upload_form').submit(function(){
		if($(' :input[@name=csv_file]',this).fieldValue() == "") {
			alert("Please select a file");
			return false;
		}
	});
});

function generate_list_content(src, target) {

	$('#'+target).attr("value",$('#'+src).attr("value"));
	
	return true;
}

function csv_import(outputDiv, form) {
    var openString = "index.php";
		
	$.ajax({type:"post", url:"index.php?p=dataset&q=csv_import", datatype:"html", data:$(form).formSerialize(), 
		success:function(response) {
			$('#'+outputDiv).html(response);
			
			//confirm on all negative button clicks
			$("a.negative",'#'+outputDiv).click(function(){
				return confirm("Are you sure?");
			});
			
			$('form.csv_nav').submit(function(){
				csv_import('csv_import', this);
				
				return false;
			});
		}
	});

	return false; //so that we dont submit forms
}

function send_form(form) {
	
	//do some datatype checking first
	var required_datatypeid = $("input[@name=datatypeid]",form).fieldValue();
	
	var entered_value = $("input[@name=value]",form).fieldValue();
	
	if (entered_value == "") entered_value = $("select[@name=value]",form).fieldValue();
	
	if (entered_value != "") {
		//check for the correct datatype
		if (isNaN(entered_value[0]) && required_datatypeid[0] == 2) {
			//if entered is String and required is Number then 
			alert("This column is set to require Numbers ["+entered_value[0]+"]");
			$("input[@name=value]",form)[0].focus();
			return false;
		}
	}

	var url_loc = $("input[@name=location]",form).fieldValue();

	$.ajax({type:"post", url:url_loc[0], datatype:"html", data:$(form).formSerialize(), 
		success:function(response) {
			
			if (response == '1') {
				$("input[@name=location]",form).attr("value",'index.php?p=dataset&q=edit_data');
			}
			else if (response == '2') {
				$("input[@name=location]",form).attr("value",'index.php?p=dataset&q=add_data');
			}
			else {
				$('#system_working').html('Failure');
				$('#system_working').show();
			}
		}
	});

	return false;
}