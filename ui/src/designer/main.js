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
const loadingTemplate = require('../templates/loading.hbs');

// Include modules
const Region = require('./region.js');
const Layout = require('./layout.js');
const Widget = require('./widget.js');
const Navigator = require('./navigator.js');
const Timeline = require('./timeline.js');
const Manager = require('./manager.js');
const Viewer = require('./viewer.js');
const toolbar = require('./toolbar.js');
const PropertiesPanel = require('./properties-panel.js');

// Include helpers
//require('../helpers/operators.js');

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

    // Viewer
    viewer: {},

    // Designer DOM div
    designerDiv: $('#layout-editor'),

    // Selected object
    selectedObject: {},

    // Bottom toolbar
    toolbar: {},

    // Properties Panel
    propertiesPanel: {}
};

// Load Layout and build app structure
$(document).ready(function() {
    // Get layout id
    const layoutId = lD.designerDiv.attr("data-layout-id");
    
    // Append loading html to the main div
    lD.designerDiv.html(loadingTemplate());

    // Load layout through an ajax request
    $.get(urlsForApi['layout']['get'].url + '?layoutId=' + layoutId + '&embed=regions,playlists,widgets')
        .done(function(res) {

            if(res.data.length > 0) {

                // Append layout html to the main div
                lD.designerDiv.html(designerMainTemplate());

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

                // Initialize manager
                lD.manager = new Manager(
                    lD.designerDiv.find('#layout-manager')
                );

                // Initialize viewer
                lD.viewer = new Viewer(
                    lD.designerDiv.find('#layout-viewer'),
                    lD.designerDiv.find('#layout-viewer-navbar')
                );

                // Initialize bottom toolbar
                lD.toolbar = new toolbar(
                    lD.designerDiv.find('#layout-editor-toolbar')
                );

                // Initialize properties panel
                lD.propertiesPanel = new PropertiesPanel(
                    lD.designerDiv.find('#properties-panel')
                );

                // Default selected object is the layout
                lD.selectObject();
            } else {
                lD.showErrorMessage();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {

            // Output error to console
            console.error(jqXHR, textStatus, errorThrown);

            lD.showErrorMessage();
        }
    );

    // When in edit mode, enable click on background to close navigator
    lD.designerDiv.find('#layout-navigator-edit').click(function(event) {
        if(event.target.id === 'layout-navigator-edit') {
            lD.toggleNavigatorEditing(false);
        }
    });

    // Refresh some modules on window resize
    $(window).resize($.debounce(500, function(e) {
        if(e.target === window) {

            // Refresh navigators and viewer
            lD.renderContainer(lD.navigator);
            lD.renderContainer(lD.navigatorEdit);
            lD.renderContainer(lD.viewer, lD.selectedObject);
        }
    }));
});

/**
 * Select a layout object (layout/region/widget)
 * @param {object=} obj - Object to be selected
 */
lD.selectObject = function(obj = null) {

    // Get object properties from the DOM ( or set to layout if not defined )
    const newSelectedId = (obj === null) ? this.layout.id : obj.attr('id');
    const newSelectedType = (obj === null) ? 'layout' : obj.data('type');

    const oldSelectedId = this.selectedObject.id;
    const oldSelectedType = this.selectedObject.type;
    
    // Unselect the previous selectedObject object if still selected
    if( this.selectedObject.selected ) {

        switch(this.selectedObject.type) {
            case 'region':
                if(this.layout.regions[this.selectedObject.id]) {
                    this.layout.regions[this.selectedObject.id].selected = false;
                }
                break;

            case 'widget':
                if(this.layout.regions[this.selectedObject.regionId].widgets[this.selectedObject.id]) {
                    this.layout.regions[this.selectedObject.regionId].widgets[this.selectedObject.id].selected = false;
                }
                break;

            default:
                break;
        }
        
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

    // Refresh the designer containers
    this.refreshDesigner();
};

/**
 * Refresh designer
 */
lD.refreshDesigner = function() {

    // Render containers with layout ( default )
    this.renderContainer(this.navigator);
    this.renderContainer(this.navigatorEdit);
    this.renderContainer(this.timeline);
    this.renderContainer(this.toolbar);
    this.renderContainer(this.manager);

    // Render selected object in the following containers
    this.renderContainer(this.propertiesPanel, this.selectedObject);
    this.renderContainer(this.viewer, this.selectedObject);
};


/**
 * Reload API data and replace the layout structure with the new value
 * @param {object} layout - previous layout
 */
lD.reloadData = function(layout) {

    $.get(urlsForApi['layout']['get'].url + '?layoutId=' + layout.layoutId + "&embed=regions,playlists,widgets")
        .done(function(res) {
            
            if(res.data.length > 0) {
                lD.layout = new Layout(layout.layoutId, res.data[0]);
                lD.refreshDesigner();

                // Select the same object
                const selectObjectId = lD.selectedObject.id;
                lD.selectedObject = {};

                lD.selectObject($('#' + selectObjectId));
            } else {
                lD.showErrorMessage();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {

            // Output error to console
            console.error(jqXHR, textStatus, errorThrown);

            lD.showErrorMessage();
        }
    );
};


/**
 * Render layout structure to container, if it exists
 * @param {object} container - Container for the layout to be rendered
 * @param {object=} element - Element to be rendered, if not used, render layout
 */
lD.renderContainer = function(container, element = {}) {
    // Check container to prevent rendering to an empty container
    if(!jQuery.isEmptyObject(container)) {

        // Render element if defined, layout otherwise
        if(!jQuery.isEmptyObject(element)) {
            container.render(element, this.layout);
        } else {
            container.render(this.layout);
        }
    }
};

/**
 * Toggle editing functionality on Navigator
 * @param {boolean} enable - flag to toggle the editing
 */
lD.toggleNavigatorEditing = function(enable) {

    // Unselect objects ( select layout )
    this.selectObject();

    if(enable) {
        // Create a new navigator instance
        this.navigatorEdit = new Navigator(
            this.designerDiv.find('#layout-navigator-edit-content'),
            {
                edit: true,
                editNavbar: this.designerDiv.find('#layout-navigator-edit-navbar')
            }
        );

        // Show navigator edit div
        this.designerDiv.find('#layout-navigator-edit').css('display', 'block');

        // Render navigator
        this.renderContainer(this.navigatorEdit);

    } else {

        // Refresh designer
        this.refreshDesigner();

        // Clean variable
        this.navigatorEdit = {};

        // Clean object HTML and hide div
        this.designerDiv.find('#layout-navigator-edit-content').empty();
        this.designerDiv.find('#layout-navigator-edit').css('display', 'none');

    }
};

/**
 * Layout loading error message
 */
lD.showErrorMessage = function() {
    // Output error on screen
    const htmlError = messageTemplate({
        messageType: 'danger',
        messageTitle: 'ERROR',
        messageDescription: 'There was a problem loading the layout!'
    });

    lD.designerDiv.html(htmlError);
};
