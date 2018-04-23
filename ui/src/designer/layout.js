// LAYOUT Module
const Region = require('./region.js');
const Widget = require('./widget.js');

// Default dimensions to be used when creating a new region
const NewRegionDefaultDimensions = {
    width: 100,
    height: 100,
    top: 10,
    left: 10
};

/**
 * Layout contructor
 * @param  {number} id - layout id
 * @param  {object} data - data from the API request
 */
let Layout = function(id, data) {

    // Layout properties
    this.id = 'layout_' + id;
    this.layoutId = id;

    //this.data = data; //TODO: check if we need to maintain the "pure" data object
    this.regions = {};
    this.duration = null;

    this.width = data.width;
    this.height = data.height;

    this.backgroundImage = data.backgroundImageId;
    this.backgroundColor = data.backgroundColor;

    this.containerProperties = {
        width: data.width,
        height: data.height,
        top: 0,
        left: 0,
        scaleToTheOriginal: 1
    };

    // incremental index to use as the id for the new created regions
    this.createdRegionsIndex = 0;

    this.backgroundCss = function() {
        if(this.backgroundImage === null) {
            return this.backgroundColor;
        } else {
            return "url('/layout/background/" + this.layoutId + "?preview=1&width=" + this.containerProperties.width + "&height=" + this.containerProperties.height + "&proportional=0&layoutBackgroundId=" + this.backgroundImage + "') top center no-repeat; background-color: " + this.backgroundColor;
        }
    };

    // Create data structure based on the BE response ( data )
    this.createDataStructure(data);

    // Calculate duration, looping, and all properties related to time
    this.calculateTimeValues();
};

/**
 * Create data structure
 */
Layout.prototype.createDataStructure = function(data) {

    // layout duration calculated based on the longest region duration
    let layoutDuration = 0;

    // Create regions and add them to the layout
    for(let region in data.regions) {
        let regionDuration = 0;

        let newRegion = new Region(
            data.regions[region].regionId,
            data.regions[region]
        );

        // Widgets
        const widgets = newRegion.playlists.widgets;

        // Create widgets for this region
        for(let widget in widgets) {

            const newWidget = new Widget(
                widgets[widget].widgetId,
                data.regions[region].regionId,
                widgets[widget]
            );

            // Add newWidget to the Region widget object
            newRegion.widgets[newWidget.id] = newWidget;

            // increase region Duration
            regionDuration += newWidget.getDuration();
        }

        // Set region duration
        newRegion.duration = regionDuration;

        // Push Region to the Layout region array
        this.regions[newRegion.id] = newRegion;

        // update layoutDuration if the current regions is the longest one
        if(regionDuration > layoutDuration) {
            layoutDuration = regionDuration;
        }
    }

    // Set layout duration
    this.duration = layoutDuration;
};


/**
 * Calculate timeline values ( duration, loops ) based on widget and region duration
 */
Layout.prototype.calculateTimeValues = function() {
    
    for(let region in this.regions) {
        let currRegion = this.regions[region];

        // Widgets
        const widgets = currRegion.widgets;
        let loopSingleWidget = false;
        let singleWidget = false;

        // If there is only one widget in the playlist, check the loop option for that region
        if(widgets.length === 1) {

            singleWidget = true;
            // Check the loop option
            for(let option in currRegion.options) {
                if(currRegion.options[option].option === 'loop' && currRegion.options[option].value === '1') {
                    currRegion.loop = true;
                    loopSingleWidget = true;
                    break;
                }
            }
        } else if(parseFloat(currRegion.duration) < parseFloat(this.duration)) {
            // if the region duration is less than the layout duration enable loop
            currRegion.loop = true;
        }

        for(let widget in widgets) {
            let currWidget = widgets[widget];

            // If the widget needs to be extended
            currWidget.singleWidget = singleWidget;
            currWidget.loop = loopSingleWidget;
        }
    }
};


/**
 * Add a new empty element to the layout
 * @param {string} elementType - element type (widget, region, ...)
 */
Layout.prototype.addElement = function(elementType) {

    let elementId;
    
    // Add element to the layout by type
    if(elementType === 'region') {

        let newRegion = new Region(
            this.createdRegionsIndex, // TODO: Find a way to create a new ID
            NewRegionDefaultDimensions
        );

        // Change the new element values to show that is temporary
        newRegion.createdRegion = true;
        newRegion.id = 'temp_' + newRegion.id;
        newRegion.regionId = 't_' + newRegion.regionId;

        elementId = newRegion.id;
        elementCopy = newRegion;

        // Increase new region index
        this.createdRegionsIndex++;

        // Push Region to the Layout region array
        this.regions[newRegion.id] = newRegion;

    } else if(elementType === 'widget') {
        console.log('TODO: Widget create');
    }

    if(!jQuery.isEmptyObject(elementCopy)) {
        lD.manager.addChange(
            "create",
            elementType,
            elementId,
            elementCopy,
            null
        );
    }

    // Refresh the designer to update the changes
    lD.refreshDesigner();

    lD.selectObject($('#' + elementId));
};

/**
 * Delete an element in the layout, by ID
 * @param {string} elementType - element type (widget, region, ...)
 * @param {number} elementId - element id
 * @param {object=} options - additional options
 */
Layout.prototype.deleteElement = function(elementId, elementType, options = {}) {

    const self = this;
    
    let deleteResult = false;
    let requestURL = '';

        // Delete the region
        delete this.regions[elementId];

        deleteResult = true;
        
    } else if(elementType === 'widget') {
        console.log('TODO: Widget delete');
    }

    // Check if element exists and add the change to the history
    if(!jQuery.isEmptyObject(elementCopy) && options.saveToHistory){

        // Save element without select flag
        elementCopy.selected = false;

        // save delete regions to history changes
        lD.manager.addChange(
            "delete",
            elementType,
            elementId,
            elementCopy,
            null
        );
    }

    // If delete was successful, unselect all objects and refresh the designer
    if(deleteResult) {
        // Unselect all objects
        lD.selectObject();

        // Refresh the designer to update the changes
        lD.refreshDesigner();
    }

    return deleteResult;
};

/**
 * Restore element to the layout
 * @param {number} elementId - element id
 * @param {string} elementType - element type (widget, region, ...)
 * @param {object} elementData - element data to restore
 */
Layout.prototype.restoreElement = function(elementId, elementType, elementData) {
    let restoreResult = false;

    if(elementType === 'widget') {
        console.log('  TODO: Restore widget');
        this.regions[elementData.regionId].widgets[elementId] = elementData;

        restoreResult = true;
    } else if(elementType === 'region') {
        this.regions[elementId] = elementData;
        
        restoreResult = true;
    }

    return restoreResult;
};

module.exports = Layout;