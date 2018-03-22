/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2018 Daniel Garner
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

// Include handlebars templates
const designerMainTemplate = require('../templates/designer.hbs');
const messageTemplate = require('../templates/message.hbs');

// Include modules
const Region = require('./region.js');
const Layout = require('./layout.js');
const Widget = require('./widget.js');
const Navigator = require('./navigator.js');
const Timeline = require('./timeline.js');

// Include CSS
require('../css/designer.css');

// Navigator structure
var navigator = {};
var navigatorEdit = {};

// Layout structure
var layout = {};

// Timeline structure
var timeline = {};

// Designer DOM div
var designerDiv = $('#layout-editor');

// Selected object
var selectedObject = {};

// Load Layout and build app structure
$(document).ready(function() {
    // Get layout id
    var layoutId = designerDiv.attr("data-layout-id");

    // Append layout html to the main div
    designerDiv.html(designerMainTemplate());

    // Load layout through an ajax request
    $.get("/layout?layoutId=" + layoutId + "&embed=regions,playlists,widgets")
        .done(function(res) {

            if(res.data.length > 0) {

                // Create layout
                layout = new Layout(layoutId, res.data[0]);

                // Initialize navigator
                navigator = new Navigator(
                    // Small container
                    designerDiv.find('#layout-navigator'),
                );

                // Initialize timeline
                timeline = new Timeline(
                    designerDiv.find('#layout-timeline'),
                    layout.duration
                );

                // Default selected object is the layout ( that will render the containers )
                selectObject(designerDiv.find('#layout_' + layoutId));
            }
        })
        .fail(function(data) {
            // Output error on screen
            var htmlError = messageTemplate({
                messageType: 'danger',
                messageTitle: 'ERROR',
                messageDescription: 'There was a problem loading the layout!'
            });

            designerDiv.html(htmlError);

            return -1;
        });

    // Button actions
    designerDiv.find('#refreshDesigner').click(function() {
        refreshDesigner();
    });

    designerDiv.find('#layout-navigator-edit-navbar .close-button').click(function() {
        toggleNavigatorEditing(false);
    });

    designerDiv.find('#enableNavigatorEditMode').click(function() {
        toggleNavigatorEditing(true);
    });

    designerDiv.find('#layout-navigator-edit').click(function(event) {
        if(event.target.id == 'layout-navigator-edit') {
            toggleNavigatorEditing(false);
        }
    });

    // Refresh the designer render on window resize
    $(window).resize($.debounce(500, function() {
        refreshDesigner();
    }));
});

/**
 * Select a layout object (layout/region/widget)
 * @param  {object} obj - Object to be selected
 */
window.selectObject = function(obj) {

    var newSelectedId = obj.attr('id');
    var newSelectedType = obj.data('type');

    var oldSelectedId = selectedObject.id;
    var oldSelectedType = selectedObject.type;
    
    // Unselect the previous selected object
    switch(selectedObject.type) {
        case 'region':
            layout.regions[selectedObject.id].selected = false;
            break;

        case 'widget':
            layout.regions[selectedObject.regionId].widgets[selectedObject.id].selected = false;
            break;

        default:
            break;
    }
    
    // Set to the default object
    selectedObject = layout;
    selectedObject.type = 'layout';

    // If the selected object was different from the previous, select a new one
    if(oldSelectedId != newSelectedId) {

        // Save the new selected object
        if(newSelectedType === 'region') {
            layout.regions[newSelectedId].selected = true;
            selectedObject = layout.regions[newSelectedId];
        } else if(newSelectedType === 'widget') {
            layout.regions[obj.data('widgetRegion')].widgets[newSelectedId].selected = true;
            selectedObject = layout.regions[obj.data('widgetRegion')].widgets[newSelectedId];
            console.log(selectedObject.getDuration(true));
        }

        selectedObject.type = newSelectedType;
    }

    //TODO: Output selected object properties on PROPERTIES container
    designerDiv.find('#layout-property-panel').html(
        '<h2>id: ' + selectedObject.id + '</h2>' +
        '<h2>type: ' + selectedObject.type + '</h2>'
    );

    // Refresh the designer containers
    this.refreshDesigner();
};

/**
 * Refresh designer
 */
window.refreshDesigner = function() {
    this.renderContainer(navigator);
    this.renderContainer(navigatorEdit);
    this.renderContainer(timeline);
};

/**
 * Render layout structure to container, if it exists
 * @param {object} container - Container for the layout to be rendered
 */
window.renderContainer = function(container) {
    if(!jQuery.isEmptyObject(container)) {
        container.render(layout);
    }
};

/**
 * Toggle editing functionality on Navigator
 * @param {boolean} enable - flag to toggle the editing
 */
window.toggleNavigatorEditing = function(enable) {
    if(enable) {
        // Create a new navigator instance
        navigatorEdit = new Navigator(
            designerDiv.find('#layout-navigator-edit-content'),
            {
                edit: true,
                padding: 0.05
            }
        );

        designerDiv.find('#layout-navigator-edit').css('display', 'block');

        // Render navigator
        renderContainer(navigatorEdit);

    } else {

        // Refresh designer
        refreshDesigner();

        // Clean variable
        navigatorEdit = {};

        // Clean object HTML
        designerDiv.find('#layout-navigator-edit-content').empty();
        designerDiv.find('#layout-navigator-edit').css('display', 'none');

    }
};
