// LAYOUT Module
const Region = require('../designer/region.js');
const Widget = require('../designer/widget.js');

/**
 * Layout contructor
 * @param  {number} id - layout id
 * @param  {object} data - data from the API request
 */
let Layout = function(id, data) {

    // Is it editable? ( checkif status is draft )
    this.editable = (data.publishedStatusId == 2);

    // Does user have permission to schedule now page?
    this.scheduleNowPermission = (data.scheduleNowPermission);

    // Parent Id
    this.parentLayoutId = data.parentId;

    // Layout name
    this.name = data.layout;
    
    // Layout properties
    this.id = 'layout_' + id;
    this.layoutId = id;

    // Campaing ID
    this.campaignId = data.campaignId;

    this.regions = {};
    this.duration = null;

    this.width = data.width;
    this.height = data.height;

    this.backgroundImage = data.backgroundImageId;
    this.backgroundColor = data.backgroundColor;

    // Get background image if exists, if not, get the background color
    this.backgroundCss = function(width = this.width, height = this.height) {       
        if(this.backgroundImage === null) {
            return this.backgroundColor;
        } else {
            // Get API link
            let linkToAPI = urlsForApi['layout']['downloadBackground'].url;
            // Replace ID in the link
            linkToAPI = linkToAPI.replace(':id', this.layoutId);

            return "url('" + linkToAPI + "?preview=1&width=" + width + "&height=" + height + "&proportional=0&layoutBackgroundId=" + this.backgroundImage + "') top center no-repeat; background-color: " + this.backgroundColor;
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

    this.numRegions = data.regions.length;

    // Create regions and add them to the layout
    for(let region in data.regions) {
        let regionDuration = 0;

        let newRegion = new Region(
            data.regions[region].regionId,
            data.regions[region]
        );

        // Save index
        newRegion.index = parseInt(region) + 1;

        // Widgets
        const widgets = newRegion.playlists.widgets;

        newRegion.numWidgets = widgets.length;

        // Create widgets for this region
        for(let widget in widgets) {

            const newWidget = new Widget(
                widgets[widget].widgetId,
                widgets[widget],
                data.regions[region].regionId,
                this
            );

            // Save index
            newWidget.index = parseInt(widget) + 1;

            // Mark the widget as sortable if region can be sorted/edited
            newWidget.isSortable = newRegion.isEditable;

            // Add newWidget to the Region widget object
            newRegion.widgets[newWidget.id] = newWidget;

            // Mark the region as not empty
            newRegion.isEmpty = false;
            
            // increase region Duration with widget base duration
            regionDuration += newWidget.getTotalDuration();

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
        if(Object.keys(widgets).length === 1) {

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
 * @param {object =} [positionToAdd] - Position to add the element to
 */
Layout.prototype.addElement = function(elementType, positionToAdd = null) {

    let newValues = null;
    
    /// Get position values if they exist
    if(positionToAdd !== null) {
        newValues = positionToAdd;
    }
    
    // Add a create change to the history array, and a option to update the Id on the change to the newly created object
    return lD.manager.addChange(
        "create",
        elementType, // targetType
        null, // targetId
        null, // oldValues
        newValues, // newValues
        {
            updateTargetId: true // options.updateTargetId
        }
    );
};

/**
 * Delete an element in the layout, by ID
 * @param {number} elementId - element id
 * @param {string} elementType - element type (widget, region, ...)
 * @param {object =} [options] - Delete submit params/options
 */
Layout.prototype.deleteElement = function(elementType, elementId, options = null) {
    
    lD.common.showLoadingScreen('deleteElement'); 

    // Save all changes first
    return lD.manager.saveAllChanges().then((res) =>  {

        // Remove changes from the history array
        return lD.manager.removeAllChanges(lD.selectedObject.type, lD.selectedObject[lD.selectedObject.type + 'Id']).then((res) =>  {

            // Unselect selected object before deleting
            lD.selectObject();

            lD.common.hideLoadingScreen('deleteElement'); 

            // Create a delete type change, upload it but don't add it to the history array
            return lD.manager.addChange(
                "delete",
                elementType, // targetType
                elementId, // targetId
                null, // oldValues
                options, // newValues
                {
                    addToHistory: false // options.addToHistory
                }
            );

        }).catch(function() {

            lD.common.hideLoadingScreen('deleteElement'); 

            toastr.error(errorMessagesTrans.removeAllChangesFailed);
        });
    }).catch(function() {

        lD.common.hideLoadingScreen('deleteElement'); 
        
        toastr.error(errorMessagesTrans.saveAllChangesFailed);
    });
    
};

/**
 * Save playlist order
 * @param {object} playlist - playlist
 * @param {object} widgets - Widgets DOM objects array
 */
Layout.prototype.savePlaylistOrder = function(playlist, widgets) {

    // Get playlist's widgets previous order
    let oldOrder = {};
    let orderIndex = 1;
    for(var element in playlist.widgets) {
        oldOrder[playlist.widgets[element].widgetId] = orderIndex;
        orderIndex++;
    }

    // Get new order
    let newOrder = {};

    for(let index = 0;index < widgets.length;index++) {
        const widget = lD.getElementByTypeAndId('widget', $(widgets[index]).attr('id'), 'region_' + playlist.regionId);

        newOrder[widget.widgetId] = index + 1;
    }

    if(JSON.stringify(newOrder) === JSON.stringify(oldOrder)) {
        return Promise.resolve({
            message: errorMessagesTrans.listOrderNotChanged
        });
    }

    return lD.manager.addChange(
        "order",
        "playlist",
        playlist.playlistId,
        {
            widgets: oldOrder
        },
        {
            widgets: newOrder
        }
    ).catch((error) => {
        toastr.error(errorMessagesTrans.playlistOrderSave);
        console.log(error);
    });
};

/**
 * Update layout status fields
 * @param {int} status - Status code
 * @param {string} statusFeedback - Status feedback message
 * @param {string[]} statusMessages - Status messages array
 */
Layout.prototype.updateStatus = function(status, statusFeedback, statusMessages) {
    // Update status property
    this.status = {
        code: status,
        description: statusFeedback,
        messages: statusMessages
    };

    // Update layout status
    lD.topbar.updateLayoutStatus();
};


/**
 * Calculate layout values for the layout based on the scale of this container
 * @returns {object} Clone Object containing dimensions for the object
 */
Layout.prototype.scale = function(container) {

    let layoutClone = Object.assign({}, this);

    // Get container dimensions
    const containerDimensions = {
        width: container.width(),
        height: container.height()
    };

    // Calculate ratio
    const elementRatio = layoutClone.width / layoutClone.height;
    const containerRatio = containerDimensions.width / containerDimensions.height;

    // Create container properties object
    layoutClone.scaledDimensions = {};

    // Calculate scale factor
    if(elementRatio > containerRatio) { // element is more "landscapish" than the container
        // Scale is calculated using width
        layoutClone.scaledDimensions.scale = containerDimensions.width / layoutClone.width;
    } else { // Same ratio or the container is the most "landscapish"
        // Scale is calculated using height
        layoutClone.scaledDimensions.scale = containerDimensions.height / layoutClone.height;
    }

    // Calculate new values for the element using the scale factor
    layoutClone.scaledDimensions.width = layoutClone.width * layoutClone.scaledDimensions.scale;
    layoutClone.scaledDimensions.height = layoutClone.height * layoutClone.scaledDimensions.scale;

    // Calculate top and left values to centre the element in the container
    layoutClone.scaledDimensions.top = containerDimensions.height / 2 - layoutClone.scaledDimensions.height / 2;
    layoutClone.scaledDimensions.left = containerDimensions.width / 2 - layoutClone.scaledDimensions.width / 2;

    // Get scaled background
    layoutClone.calculatedBackground = layoutClone.backgroundCss(layoutClone.scaledDimensions.width, layoutClone.scaledDimensions.height);

    // Regions Scalling
    for(let region in layoutClone.regions) {

        layoutClone.regions[region].scaledDimensions = {};

        // Loop through the container properties and scale them according to the layout scale from the original
        for(let property in layoutClone.regions[region].dimensions) {
            if(layoutClone.regions[region].dimensions.hasOwnProperty(property)) {
                layoutClone.regions[region].scaledDimensions[property] = layoutClone.regions[region].dimensions[property] * layoutClone.scaledDimensions.scale;
            }
        }

    }

    return layoutClone;
};

module.exports = Layout;