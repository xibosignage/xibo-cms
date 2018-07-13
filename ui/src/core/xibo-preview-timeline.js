/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
    this.url = $(regionElement).data().previewUrl;
    this.regionId = $(regionElement).attr("regionid");
	this.seq = 1;
	this.regionElement = regionElement;
	this.width	= $(regionElement).width();
	this.height = $(regionElement).height();
	
	this.previewElement = $('.preview',regionElement);
	this.previewContent = $('.previewContent', this.previewElement);

	// Setup global control tracking
    // Declare regionId here so that it is available in the click functions
    var regionId = this.regionId;

	Preview.instances[regionId] = this;
	
	// Create the Nav Buttons
	$('.previewNav', this.previewElement)
		.append("<div class='prevSeq glyphicon glyphicon-arrow-left'></div>")
		.append("<div class='nextSeq glyphicon glyphicon-arrow-right'></div>")
		.append("<div class='preview-media-information'></div>");

	$('.prevSeq', $(this.previewElement)).click(function() {
		var preview = Preview.instances[regionId];
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
		var preview = Preview.instances[regionId];
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
	
	var previewContent 	= this.previewContent;
	var previewElement = this.previewElement;

	this.width	= $(this.regionElement).width();
	this.height = $(this.regionElement).height();
	
	// Get the sequence via AJAX
	$.ajax({
        type:"get",
		url: this.url,
		cache: false, 
		dataType: "json", 
		data:{
			"seq": seq,
			"width": this.width,
			"height": this.height,
			"scale_override": $(this.regionElement).attr("designer_scale")
		},
		success: function(response) {
		
			if (response.success) {

                if (response.extra.empty) {
                    $('.preview-media-information', previewElement).html(response.extra.text);
                    return;
                }

				// Success - what do we do now?
				$(previewContent).html("<div class=\"regionPreviewOverlay\"></div>" + ((response.html == null) ? "" : response.html));

				var infoText = "";

				if (response.extra.zIndex != 0)
					infoText = "[" + response.extra.zIndex + "] ";

				infoText += response.extra.current_item + " / " + response.extra.number_items + " "
                    + response.extra.moduleName;

				if (response.extra.duration > 0 && response.extra.useDuration != 0)
                    infoText += " (" + moment().startOf("day").seconds(response.extra.duration).format("H:mm:ss") + " / " + moment().startOf("day").seconds(response.extra.regionDuration).format("H:mm:ss") + ")";

				// Get the extra
				$('.preview-media-information', previewElement)
					.html(infoText)
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
		            $(previewContent).html("<div class=\"regionPreviewOverlay\"></div>" + response.html);
		        }
			}
			return false;
		}
	});
}