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
	$('.previewNav', this.previewElement)
		.append("<div class='prevSeq glyphicon glyphicon-arrow-left'></div>")
		.append("<div class='nextSeq glyphicon glyphicon-arrow-right'></div>")
		.append("<div class='preview-media-information'></div>");

	$('.prevSeq', $(this.previewElement)).click(function() {
		var preview = Preview.instances[regionid];
		var maxSeq 	= $('.preview-media-information', preview.previewElement).data("maxSeq");
				
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
		var maxSeq 	= $('.preview-media-information', preview.previewElement).data("maxSeq");
		
		var currentSeq = preview.seq;
		currentSeq++;
		
		if (currentSeq > maxSeq)
			currentSeq = 1;
		
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
	var previewElement = this.previewElement;

	this.width	= $(this.regionElement).width();
	this.height = $(this.regionElement).height();
	
	// Get the sequence via AJAX
	$.ajax({type:"post", 
		url: "index.php?p=timeline&q=RegionPreview&ajax=true", 
		cache: false, 
		dataType: "json", 
		data:{
			"layoutid": layoutid,
			"seq": seq,
			"regionid": regionid,
			"width": this.width, 
			"height": this.height,
			"scale_override": $(this.regionElement).attr("designer_scale")
		},
		success: function(response) {
		
			if (response.success) {
				// Success - what do we do now?
				$(previewContent).html(response.html);

				// Get the extra
				$('.preview-media-information', previewElement)
					.html(response.extra.text)
					.data("maxSeq", response.extra.number_items);
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