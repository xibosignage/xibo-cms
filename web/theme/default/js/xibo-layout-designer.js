/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2008-2018 Spring Signage Ltd
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
 *
 */
var layout;
var lockPosition;
var hideControls;
var lowDesignerScale;
var $region;
var regionHoverInterval;
var regionHoverIntervalReset = false;

$(document).ready(function(){
    
    // Set the height of the grid to be something sensible for the current screen resolution
    var jumpList = $("#layoutJumpList");

    jumpList.select2({
        ajax: {
            url: jumpList.data().url,
            dataType: "json",
            data: function(params) {
                console.log(params.term);

                var query = {
                    layout: params.term,
                    start: 0,
                    length: 10
                };

                // Set the start parameter based on the page number
                if (params.page != null) {
                    query.start = (params.page - 1) * 10;
                }

                // Find out what is inside the search box for this list, and save it (so we can replay it when the list
                // is opened again)
                if (params.term !== undefined) {
                    localStorage.liveSearchPlaceholder = params.term;
                }

                return query;
            },
            processResults: function(data, params) {
                var results = [];

                $.each(data.data, function(index, element) {
                    results.push({
                        "id": element.layoutId,
                        "text": element.layout
                    });
                });

                var page = params.page || 1;
                page = (page > 1) ? page - 1 : page;

                return {
                    results: results,
                    pagination: {
                        more: (page * 10 < data.recordsTotal)
                    }
                }
            },
            delay: 250
        }
    });

    jumpList.on("select2:select", function(e) {
        // Go to the Layout we've selected.
        window.location = jumpList.data().designerUrl.replace(":id", e.params.data.id);
    }).on("select2:opening", function(e) {
        // Set the search box according to the saved value (if we have one)
        console.log(localStorage.liveSearchPlaceholder);

        if (localStorage.liveSearchPlaceholder != null && localStorage.liveSearchPlaceholder !== "") {
            var $search = jumpList.data("select2").dropdown.$search;
            $search.val(localStorage.liveSearchPlaceholder);

            setTimeout(function() { $search.trigger("input"); }, 100);
        }
    });

    // Load Layout
    layout = $("#layout");

    // Read in the values of lockPosition and hideControls
    lockPosition = ($("input[name=lockPosition]").length > 0) ? $("input[name=lockPosition]")[0].checked : false;
    hideControls = ($("input[name=hideControls]").length > 0) ? $("input[name=hideControls]")[0].checked : false;
    lowDesignerScale = (layout.attr("designer_scale") < 0.41);

    if (lowDesignerScale)
        $("input[name=lockPosition]").attr("disabled", true);

    // Hover functions for previews/info
    layout.find(".region")
        .hover(function() {
            $region = $(this);

            //console.log("Hover ON: region " + $region.attr("regionId"));

            if (regionHoverInterval === null || regionHoverInterval === undefined) {
                regionHoverIntervalReset = false;
                regionHoverInterval = setTimeout(function () {
                        //console.log("zIndex adjustment: region " + $region.attr("regionId"));

                        $region.css("zIndex", 900);
                        regionHoverInterval = null;
                        regionHoverIntervalReset = true;
                    }, 500
                );
            }

            if (!hideControls) {
                layout.find(".regionInfo").show();
                layout.find(".previewNav").show();
            }
        }, function() {


            //console.log("Hover OFF: Interval Reset is " + regionHoverIntervalReset);

            if (regionHoverIntervalReset) {
                // Reset each region
                layout.find('.region').each(function () {
                    var $resetRegion = $(this);

                    // Reset to the original z-index
                    $resetRegion.css("zIndex", $resetRegion.attr("zindex"));
                });
            }

            layout.find(".regionInfo").hide();
            layout.find(".previewNav").hide();
        })
        .draggable({
            containment: layout,
            stop: regionPositionUpdate,
            drag: updateRegionInfo
        })
        .resizable({
            containment: layout,
            minWidth: 25,
            minHeight: 25,
            stop: regionPositionUpdate,
            resize: updateRegionInfo
        });

    // Initial Drag and Drop configuration
    configureDragAndDrop();

    // Preview
    $('.regionPreview', layout).each(function(){
        new Preview(this);
    });

    // Set an interval
    if ($("#layout-status").length > 0) {
        layoutStatus(layout.data('statusUrl'));
        setInterval("layoutStatus('" + layout.data('statusUrl') + "')", 1000 * 60); // Every minute
    }

    // Bind to the switches
    $(".switch-check-box").bootstrapSwitch().on('switchChange.bootstrapSwitch', function(event, state) {

        var propertyName = $(this).prop("name");

        if (propertyName == "lockPosition") {
            lockPosition = state;
            configureDragAndDrop();
        }
        else if (propertyName == "hideControls") {
            hideControls = state;

            if (hideControls) {
                $(".region .regionInfo").hide();
                $(".region .previewNav").hide();
            } else {
                $(".region .regionInfo").show();
                $(".region .previewNav").show();
            }
        }

        // Update the user preference
        updateUserPref([{
            option: propertyName,
            value: state
        }]);

    });

    // Hide region previews/info
    setTimeout(function() {
        $(".region .regionInfo").hide("200");
        $(".region .previewNav").hide("200");
    }, 500);

    // Bind to the region options menu
    $('.RegionOptionsMenuItem').click(function(e) {
        e.preventDefault();

        // If any regions have been moved, then save them.
        if (!$("#layout-save-all").hasClass("disabled")) {
            SystemMessage(translation.savePositionsFirst, true);
            return;
        }

        var data = {
            layoutid: $(this).closest('.region').attr("layoutid"),
            regionid: $(this).closest('.region').attr("regionid"),
            scale: $(this).closest('.region').attr("tip_scale"),
            zoom: $(this).closest('.layout').attr("zoom")
        };

        var url = $(this).prop("href");
        XiboFormRender($(this), data);
    });

    // Bind to the save/revert buttons
    $("#layout-save-all").on("click", function () {
        // Save positions for all layouts / regions
        savePositions();
        return false;
    });

    $("#layout-revert").on("click", function () {
        // Reload
        location.reload();
        return false;
    });

    // Bind to the save size button
    $("#saveDesignerSize").on("click", function () {
        // Update the user preference
        updateUserPref([{
            option: "defaultDesignerZoom",
            value: $(this).data().designerSize
        }]);
    });

    // Hook up toggle
    $('[data-toggle="tooltip"]').tooltip();
});

function configureDragAndDrop() {

    // Do we want to bind?
    if (lockPosition || lowDesignerScale) {
        layout.find(".region").draggable("disable").resizable("disable");
    } else {
        layout.find(".region").draggable("enable").resizable("enable");
    }
}

/**
 * Update Region Information with Latest Width/Position
 * @param  {[type]} e  [description]
 * @param  {[type]} ui [description]
 * @return {[type]}    [description]
 */
function updateRegionInfo(e, ui) {

    var pos = $(this).position();
    var scale = ($(this).closest('.layout').attr("version") == 1) ? (1 / $(this).attr("tip_scale")) : $(this).attr("designer_scale");
    $('.region-tip', this).html(Math.round($(this).width() / scale, 0) + " x " + Math.round($(this).height() / scale, 0) + " (" + Math.round(pos.left / scale, 0) + "," + Math.round(pos.top / scale, 0) + ")");
}

/**
 * Update preview for region position changes
 * @param e
 * @param ui
 */
function regionPositionUpdate(e, ui) {
    // Update the Preview for the new sizing
    var preview = Preview.instances[$(this).attr("regionid")];
    preview.SetSequence(preview.seq);

    // Expose a new button to save the positions
    $("#layout-save-all").removeClass("disabled");
    $("#layout-revert").removeClass("disabled");
}

function savePositions() {

    // Update all layouts
    layout.each(function(){

        $("#layout-save-all").addClass("disabled");
        $("#layout-revert").addClass("disabled");

        // Store the Layout ID
        var url = $(this).data().positionAllUrl;

        // Build an array of
        var regions = new Array();

        $(this).find(".region").each(function(){
            var designer_scale = $(this).attr("designer_scale");
            var position = $(this).position();
            var region = {
                width: $(this).width() / designer_scale,
                height: $(this).height() / designer_scale,
                top: position.top / designer_scale,
                left: position.left / designer_scale,
                regionid: $(this).attr("regionid")
            };

            // Update the region width / height attributes
            $(this).attr("width", region.width).attr("height", region.height);

            // Add to the array
            regions.push(region);
        });

        $.ajax({
                type: "put",
                url: url,
                cache: false,
                dataType: "json",
                data: {regions : JSON.stringify(regions) },
                success: XiboSubmitResponse
            });
    });
}

/**
 * Sets the layout to full screen
 */
function setFullScreenLayout(width, height) {
    $('#width', '.XiboForm').val(width);
    $('#height', '.XiboForm').val(height);
    $('#top', '.XiboForm').val('0');
    $('#left', '.XiboForm').val('0');
}

function refreshPreview(regionId) {
    // Refresh the preview
    var preview = Preview.instances[regionId];
    preview.SetSequence(preview.seq);

    // Clear the layout status
    $("#layout-status").removeClass("alert-success alert-danger").addClass("alert-info").html("<span class='fa fa-cog fa-spin'></span> " + translations.statusPending);
}

var loadTimeLineCallback = function(dialog) {
    // Make this a big modal
    dialog.addClass("modal-big");

    dialog.on("hidden.bs.modal", function () {
        refreshPreview($("#layout").data("currentRegionId"));
    });

    // Each time we open, we want to set a "current region id" in the designer
    $("#layout").data("currentRegionId", $('#timelineControl').attr('regionid'));

    // Bind to hover event on the media list
    $("li.timelineMediaListItem").hover(function() {

        var position = $(this).position();

        // Change the hidden div's content
        $("div#timelinePreview")
            .html($("div.timelineMediaPreview", this).html())
            .css({
                "margin-top": position.top + $('#timelineControl').closest('.modal-body').scrollTop()
            })
            .show();

        $hoverPreview = $("#timelinePreview .hoverPreview");

        // Apply a background to the hover preview area
        $hoverPreview.css("background", $('#layout').css('background-color'));

        // Scale if necessary
        if ($hoverPreview.data() !== undefined && $hoverPreview.data().scale) {
            // Adjust the scale again, to drop down to 180 (hover preview width)
            var regionWidth = $("#region_" + $('#timelineControl').attr('regionid')).attr("width");
            var scale = 180 / regionWidth;

            $("#timelinePreview .hoverPreview").css({
                width: regionWidth,
                transform: "scale(" + scale + ")",
                "transform-origin": "0 0 "
            });
        }

    }, function() {
        return false;
    });

    $(".timelineSortableListOfMedia").sortable();

    // Hook up the library Upload Buttons
    $(".libraryUploadForm").click(libraryUploadClick);
};

var XiboTimelineSaveOrder = function(timelineDiv) {

    var url = $("#" + timelineDiv).data().orderUrl;
    var i = 0;
    var widgets = {};

    $('#' + timelineDiv + ' li.timelineMediaListItem').each(function() {
        i++;
        widgets[$(this).attr("widgetid")] = i;
    });

    console.log(widgets);


    // Call the server to do the reorder
    $.ajax({
        type:"post",
        url: url,
        cache:false,
        dataType:"json",
        data:{
            "widgets": widgets
        },
        success: [
            XiboSubmitResponse,
            afterDesignerSave
        ]
    });
};

function afterDesignerSave() {
    // Region Preview Refresh
    $('.regionPreview').each(function(idx, el) {
        refreshPreview($(el).attr("regionid"));
    });

    // Layout Status
    layoutStatus(layout.data('statusUrl'));
}

var LibraryAssignSubmit = function() {
    // Collect our media
    var media = [];
    $("#LibraryAssignSortable > li").each(function() {
        media.push($(this).data().mediaId);
    });

    assignMediaToPlaylist($("#LibraryAssign").data().url, media);
};

var assignMediaToPlaylist = function(url, media) {
    toastr.info(media, "Assign Media to Playlist");

    $.ajax({
        type: "post",
        url: url,
        cache: false,
        dataType: "json",
        data: {media: media, useDuration: $("#useDuration").is(":checked")},
        success: XiboSubmitResponse
    });
};

function layoutStatus(url) {

    // Call with AJAX
    $.ajax({
        type: "get",
        url: url,
        cache: false,
        dataType: "json",
        success: function(response){

            var status = $("#layout-status");

            // Was the Call successful
            if (response.success) {

                // Expect the response to have a message (response.html)
                //  a status (1 to 4)
                //  a duration
                var element = $("<span>").addClass("fa");

                if (response.extra.status == 1) {
                    status.removeClass("alert-warning alert-info alert-danger").addClass("alert-success");
                    element.removeClass("fa-question fa-cogs fa-times").addClass("fa-check");
                }
                else if (response.extra.status == 2) {
                    status.removeClass("alert-success alert-info alert-danger").addClass("alert-warning");
                    element.removeClass("fa-check fa-cogs fa-times").addClass("fa-question");
                }
                else if (response.extra.status == 3) {
                    status.removeClass("alert-success alert-warning alert-danger").addClass("alert-info");
                    element.removeClass("fa-question fa-check fa-times").addClass("fa-cogs");
                }
                else {
                    status.removeClass("alert-success alert-info alert-warning").addClass("alert-danger");
                    element.removeClass("fa-question fa-cogs fa-check").addClass("fa-times");
                }

                if (response.extra.status == 1) {
                    $("#action-tab").find("i").removeClass('fa-bell fa-check fa-times').addClass('fa-check');
                }
                else if (response.extra.status == 2) {
                    $("#action-tab").find("i").removeClass('fa-check fa-times').addClass('fa-bell');
                }
                else if (response.extra.status == 3) {
                    $("#action-tab").find("i").removeClass('fa-check fa-times').addClass('fa-bell');
                }
                else  {
                    $("#action-tab").find("i").removeClass('fa-bell fa-check fa-times').addClass('fa-times');
                }


                if (response.extra.status == 1) {
                    $("#schedule-btn").find("i").removeClass('fa-times').addClass('fa-clock-o');
                }
                else if (response.extra.status == 2) {
                    $("#schedule-btn").find("i").removeClass('fa-times').addClass('fa-clock-o');
                }
                else if (response.extra.status == 3) {
                    $("#schedule-btn").find("i").removeClass('fa-times').addClass('fa-clock-o');
                }
                else  {
                    $("#schedule-btn").find("i").removeClass('fa-clock-o').addClass('fa-times');
                }

                var html = response.html;

                if (response.extra.statusMessage != undefined) {
                    $.each(response.extra.statusMessage, function (index, value) {
                        html += '<br/>' + value;
                    });
                }

                status.html(" " + html).prepend(element);

                // Duration
                $("#layout-duration").html(moment().startOf("day").seconds(response.extra.duration).format("HH:mm:ss"));
            }
            else {
                // Login Form needed?
                if (response.login) {

                    LoginBox(response.message);

                    return false;
                }
            }

            return false;
        }
    });
}
