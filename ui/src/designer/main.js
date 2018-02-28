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
const designerMainTemplate = require('../templates/designer-template.hbs');
const layoutTemplate = require('../templates/layout-template.hbs');
const regionTemplate = require('../templates/region-template.hbs');
const messageTemplate = require('../templates/message-template.hbs');

// Include modules
const Navigator = require('./navigator.js');
const Region = require('./region.js');
const Layout = require('./layout.js');
const Widget = require('./widget.js');

// Include CSS
require('../css/designer.css');

// Navigator structure
var navigator = {};
var navigatorEdit = {};

// Layout structure
var layout = {};

// Designer DOM div
var designerDiv = $('#layout-editor');

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

                var data = res.data[0];

                // Succesful request: Create Layout
                layout = new Layout(layoutId, data);

                // Create regions and add them to the layout
                for(var region in data.regions) {
                    var newRegion = new Region(
                        data.regions[region].regionId, 
                        data.regions[region]
                    );
                    
                    // Push Region to the Layout region array
                    layout.regions[data.regions[region].regionId] = newRegion;
                }

                // Initialize navigator
                navigator = new Navigator(
                    // Small container
                    designerDiv.find('#layout-navigator'),
                );

                // Render navigator
                renderContainer(navigator);
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
    $('#refreshDesigner').click(function() {
        refreshDesigner();
    });

    $('#layout-navigator-edit-navbar .close-button').click(function() {
        toggleNavigatorEditing(false);
    });

    $('#enableNavigatorEditMode, #layout-navigator').click(function() {
        toggleNavigatorEditing(true);
    });

    $('#layout-navigator-edit').click(function(event) {
        if(event.target.id == 'layout-navigator-edit') {
            toggleNavigatorEditing(false);
        }
    });
    
    // Refresh the designer render on window resize
    $(window).resize($.debounce(500,function() {
        refreshDesigner();
    }));
});


/**
 * Refresh designer
 */
var refreshDesigner = function() {
    console.log('Main - refreshDesigner');

    renderContainer(navigator);
    renderContainer(navigatorEdit);
}

/**
 * Refresh container
 */
var renderContainer = function(container) {
    console.log('Main - renderContainer');

    if( !jQuery.isEmptyObject(container) ) {
        container.render(layout, layoutTemplate);
    }
}

/**
 * Toggle editing functionality on Navigator
 * @param {boolean} enable - flag to toggle the editing
 */
var toggleNavigatorEditing = function(enable) {
    console.log('Main - toggleNavigatorEditing: ' + enable);

    if( enable ) {
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
}
