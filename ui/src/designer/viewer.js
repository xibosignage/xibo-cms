// VIEWER Module

// Load templates
const viewerTemplate = require('../templates/viewer.hbs');
const viewerNavbarTemplate = require('../templates/viewer-navbar.hbs');
const viewerLayoutPreview = require('../templates/viewer-layout-preview.hbs');
const loadingTemplate = require('../templates/loading.hbs');

/**
 * Viewer contructor
 * @param {object} container - the container to render the viewer to
 * @param {object} navbarContainer - the container to render the navbar to
 */
let Viewer = function(container, navbarContainer) {
    this.DOMObject = container;
    this.navbarContainer = navbarContainer;
};

/**
 * Calculate element scale to fit inside the container
 * @param {object} element - original object to be rendered
 * @returns {object} Object containing dimensions for the object
 */
Viewer.prototype.scaleElement = function(element, container) {

    // Get container dimensions
    const containerDimensions = {
        width: container.width(),
        height: container.height()
    };

    // Get element dimensions
    let elementDimensions = {
        width: parseFloat((element.dimensions) ? element.dimensions.width : element.width),
        height: parseFloat((element.dimensions) ? element.dimensions.height : element.height),
        scale: 1,
        top: 0,
        left: 0
    };

    // Calculate ratio
    const elementRatio = elementDimensions.width / elementDimensions.height;
    const containerRatio = containerDimensions.width / containerDimensions.height;
    
    // Calculate scale factor
    if(elementRatio > containerRatio) { // element is more "landscapish" than the container
        // Scale is calculated using width
        elementDimensions.scale = containerDimensions.width / elementDimensions.width;
    } else { // Same ratio or the container is the most "landscapish"
        // Scale is calculated using height
        elementDimensions.scale = containerDimensions.height / elementDimensions.height;
    }

    // Calculate new values for the element using the scale factor
    elementDimensions.width *= elementDimensions.scale;
    elementDimensions.height *= elementDimensions.scale;

    // Calculate top and left values to centre the element in the container
    elementDimensions.top = containerDimensions.height / 2 - elementDimensions.height / 2;
    elementDimensions.left = containerDimensions.width / 2 - elementDimensions.width / 2;

    return elementDimensions;
};

/**
 * Render Viewer
 * @param {Object} element - the object to be rendered
 * @param {number=} page - page to render on the viewer, default to 1
 */
Viewer.prototype.render = function(element, page = 1) {

    // Show loading template
    this.DOMObject.html(loadingTemplate());

    // Reset Navbar if exists
    if(this.navbarContainer != null && this.navbarContainer != undefined) {
        this.navbarContainer.html('');
    }
    
    let requestPath = urlsForApi[element.type]['preview'].url;

    // Get target element( get region if element is a Widget type )
    const targetElement = (element.type === 'widget') ? lD.layout.regions[element.regionId] : element;

    // Apply viewer scale to the layout
    const containerDimensions = this.scaleElement(targetElement, this.DOMObject);

    requestPath = requestPath.replace(':id', targetElement[targetElement.type + 'Id']);
    
    // Render layout
    if(element.type === 'layout') {

        const html = viewerTemplate({
            renderLayout: true,
            containerStyle: 'layout-player',
            dimensions: containerDimensions
        });

        this.DOMObject.html(html);

        // Handle play button
        this.DOMObject.find('#play-btn').click(function() {
            this.playPreview(requestPath, containerDimensions);
        }.bind(this));

        // Handle fullscreen button
        this.DOMObject.find('#fs-btn').click(function() {
            this.toggleFullscreen();
        }.bind(this));

    } else { // Render Widget or Region

        // Id the element is a region or widget, increase request information
        if(element.type === 'region') {
            requestPath += '?seq=' + page;
        } else if(element.type === 'widget'){
            requestPath += '?widgetId=' + element[element.type + 'Id'];
        }

        requestPath += '&width=' + containerDimensions.width + '&height=' + containerDimensions.height;

        // Get HTML for the given element from the API
        $.get(requestPath).done(function(res) { 
            
            // Prevent rendering null html
            if(!res.success) {
                toastr.error(res.message);
                this.DOMObject.html(res.message);
                return;
            }

            // Compile layout template with data
            const html = viewerTemplate({
                res: res,
                dimensions: containerDimensions
            });

            // Append layout html to the main div
            this.DOMObject.html(html);

            // Handle fullscreen button
            this.DOMObject.find('#fs-btn').click(function() {
                this.toggleFullscreen();
            }.bind(this));

            // Render navbar
            this.renderNavbar(res, element.type);

        }.bind(this)).fail(function(res) {
            toastr.error('Preview failed!');
            this.DOMObject.html('Preview failed');
        }.bind(this));
    }
};

/**
 * Render Navbar
 */
Viewer.prototype.renderNavbar = function(data, elementType) {

    // Stop if navbar container does not exist
    if(this.navbarContainer === null || this.navbarContainer === undefined || data.extra.empty) {
        return;
    }

    this.navbarContainer.html(viewerNavbarTemplate(
        {
            extra: data.extra,
            type: elementType,
            pagingEnable: (data.extra.number_items > 1)
        }
    ));

    // Paging controls
    if(data.extra && data.extra.number_items > 1) {
        this.navbarContainer.find('#left-btn').prop('disabled', (data.extra.current_item <= 1)).click(function() {
            this.render(lD.selectedObject, data.extra.current_item - 1)
        }.bind(this));

        this.navbarContainer.find('#right-btn').prop('disabled', (data.extra.current_item >= data.extra.number_items)).click(function() {
            this.render(lD.selectedObject, data.extra.current_item + 1)
        }.bind(this));
    }
};

/**
 * Play preview
 */
Viewer.prototype.playPreview = function(url, dimensions) {
    // Compile layout template with data
    const html = viewerLayoutPreview({
        url: url,
        width: dimensions.width,
        height: dimensions.height
    });

    // Append layout html to the main div
    this.DOMObject.find('.layout-player').html(html);
};

/**
 * Toggle fullscreen
 */
Viewer.prototype.toggleFullscreen = function() {
    this.DOMObject.toggleClass('fullscreen');
    this.render(lD.selectedObject);
};

module.exports = Viewer;
