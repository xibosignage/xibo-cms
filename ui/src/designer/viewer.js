// VIEWER Module

// Load templates
const viewerTemplate = require('../templates/viewer.hbs');
const viewerNavbarTemplate = require('../templates/viewer-navbar.hbs');
const loadingTemplate = require('../templates/loading.hbs');

/**
 * Viewer contructor
 * @param {object} container - the container to render the viewer to
 * @param {object} navbarContainer - the container to render the navbar to
 */
let Viewer = function(container, navbarContainer) {
    this.DOMObject = container;
    this.navbarContainer = navbarContainer;
    
    // Item number in the sequence, defaulted to 1
    this.seqItem = 1;
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
 */
Viewer.prototype.render = function(element) {

    this.DOMObject.html(loadingTemplate());

    const self = this;
    let requestPath = urlsForApi[element.type]['preview'].url;

    requestPath = requestPath.replace(':id', element[element.type + 'Id']);

    // Apply viewer scale to the layout
    const containerDimensions = self.scaleElement(element, this.DOMObject);

    // Get HTML for the given element from the API
    // FIXME: For now, call the API without values and replace the width and height if its an iframe
    // + containerDimensions.width + '&height=' + containerDimensions.height + '&scale_override=' + containerDimensions.scale
    $.get(requestPath + '?seq=' + this.seqItem).done(function(res) { 
        
        // Prevent rendering null html
        if(!res.success) {
            toastr.error(res.message);
            self.DOMObject.html(res.message);
            return;
        }

        // FIXME: Remove this if the API starts returning the width and height for the iframes
        if(res.html.search('<iframe') >= 0) {
            res.html = res.html.replace('width="0px"', 'width="' + containerDimensions.width + 'px"')
            res.html = res.html.replace('height="0px"', 'height="' + containerDimensions.height + 'px"')
        }

        // Compile layout template with data
        const html = viewerTemplate({
            res: res,
            dimensions: containerDimensions
        });

        // Append layout html to the main div
        self.DOMObject.html(html);

        // Render navbar
        self.renderNavbar(res);

    }).fail(function(res) {
        toastr.error('Preview failed!');
        self.DOMObject.html('Preview failed');
    });
};

/**
 * Render Navbar
 */
Viewer.prototype.renderNavbar = function(data) {

    // Return if navbar does not exist
    if(this.navbarContainer === null || this.navbarContainer === undefined || !data.extra || data.extra.number_items <= 1) {
        return;
    }

    this.navbarContainer.html(viewerNavbarTemplate(data));

    // Navbar buttons
    this.navbarContainer.find('#left-btn').prop('disabled', (data.extra.current_item <= 1)).click(function() {
        this.seqItem--;
        this.render(lD.selectedObject)
    }.bind(this));

    this.navbarContainer.find('#right-btn').prop('disabled', (data.extra.current_item >= data.extra.number_items)).click(function() {
        this.seqItem++;
        this.render(lD.selectedObject)
    }.bind(this));
};

module.exports = Viewer;
