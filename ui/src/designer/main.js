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

// Create layout designer namespace (lD)
window.lD = {
    // Navigator
    navigator: {},
    navigatorEdit: {},

    // Layout
    layout: {},

    // Timeline
    timeline: {},

    // Manager
    manager: {},

// Designer DOM div
    designerDiv: $('#layout-editor'),

// Selected object
    selectedObject: {}
};

// Load Layout and build app structure
$(document).ready(function() {
    // Get layout id
    var layoutId = lD.designerDiv.attr("data-layout-id");

    // Append layout html to the main div
    lD.designerDiv.html(designerMainTemplate());

    // Load layout through an ajax request
    $.get("/layout?layoutId=" + layoutId + "&embed=regions,playlists,widgets")
        .done(function(res) {

            if(res.data.length > 0) {

                // Create layout
                lD.layout = new Layout(layoutId, res.data[0]);

                // Initialize navigator
                lD.navigator = new Navigator(
                    // Small container
                    lD.designerDiv.find('#layout-navigator'),
                );

                // Initialize timeline
                lD.timeline = new Timeline(
                    lD.designerDiv.find('#layout-timeline')
                );

                // Default selected object is the layout ( that will render the containers )
                lD.selectObject(lD.designerDiv.find('#layout_' + layoutId));
            }
        })
        .fail(function(data) {
            // Output error on screen
            var htmlError = messageTemplate({
                messageType: 'danger',
                messageTitle: 'ERROR',
                messageDescription: 'There was a problem loading the layout!'
            });

            lD.designerDiv.html(htmlError);

            return -1;
        });

    // Button actions
    lD.designerDiv.find('#refreshDesigner').click(function() {
        lD.refreshDesigner();
    });

    lD.designerDiv.find('#layout-navigator-edit-navbar .close-button').click(function() {
        lD.toggleNavigatorEditing(false);
    });

    lD.designerDiv.find('#enableNavigatorEditMode').click(function() {
        lD.toggleNavigatorEditing(true);
    });

    lD.designerDiv.find('#layout-navigator-edit').click(function(event) {
        if(event.target.id === 'layout-navigator-edit') {
            lD.toggleNavigatorEditing(false);
        }
    });

    // Refresh the designer render on window resize
    $(window).resize($.debounce(500, function(e) {
        if(e.target === window) {
        refreshDesigner();
        }
    }));
});

/**
 * Select a layout object (layout/region/widget)
 * @param  {object} obj - Object to be selected
 */
lD.selectObject = function(obj) {

    var newSelectedId = obj.attr('id');
    var newSelectedType = obj.data('type');

    var oldSelectedId = this.selectedObject.id;
    var oldSelectedType = this.selectedObject.type;
    
    // Unselect the previous selected object
    switch(this.selectedObject.type) {
        case 'region':
            this.layout.regions[this.selectedObject.id].selected = false;
            break;

        case 'widget':
            this.layout.regions[this.selectedObject.regionId].widgets[this.selectedObject.id].selected = false;
            break;

        default:
            break;
    }
    
    // Set to the default object
    this.selectedObject = this.layout;
    this.selectedObject.type = 'layout';

    // If the selected object was different from the previous, select a new one
    if(oldSelectedId != newSelectedId) {

        // Save the new selected object
        if(newSelectedType === 'region') {
            this.layout.regions[newSelectedId].selected = true;
            this.selectedObject = this.layout.regions[newSelectedId];
        } else if(newSelectedType === 'widget') {
            this.layout.regions[obj.data('widgetRegion')].widgets[newSelectedId].selected = true;
            this.selectedObject = this.layout.regions[obj.data('widgetRegion')].widgets[newSelectedId];
        }

        this.selectedObject.type = newSelectedType;
    }

    //TODO: Output selected object properties on PROPERTIES container
    this.designerDiv.find('#layout-property-panel').html(
        '<h2>Property Panel</h1>' +
        '<p>id: ' + this.selectedObject.id + '</p>' +
        '<p>type: ' + this.selectedObject.type + '</p>'
    );

    // Refresh the designer containers
    this.refreshDesigner();
};

/**
 * Refresh designer
 */
lD.refreshDesigner = function() {
    this.renderContainer(this.navigator);
    this.renderContainer(this.navigatorEdit);
    this.renderContainer(this.timeline);
};

/**
 * Render layout structure to container, if it exists
 * @param {object} container - Container for the layout to be rendered
 */
lD.renderContainer = function(container) {
    if(!jQuery.isEmptyObject(container)) {
        container.render(this.layout);
    }
};

/**
 * Toggle editing functionality on Navigator
 * @param {boolean} enable - flag to toggle the editing
 */
lD.toggleNavigatorEditing = function(enable) {
    if(enable) {
        // Create a new navigator instance
        this.navigatorEdit = new Navigator(
            this.designerDiv.find('#layout-navigator-edit-content'),
            {
                edit: true,
                padding: 0.05
            }
        );

        this.designerDiv.find('#layout-navigator-edit').css('display', 'block');

        // Render navigator
        this.renderContainer(this.navigatorEdit);

    } else {

        // Refresh designer
        this.refreshDesigner();

        // Clean variable
        this.navigatorEdit = {};

        // Clean object HTML
        this.designerDiv.find('#layout-navigator-edit-content').empty();
        this.designerDiv.find('#layout-navigator-edit').css('display', 'none');

    }
};
