// LAYOUT Module
const Region = require('./region.js');
const Widget = require('./widget.js');

/**
 * Layout contructor
 * @param  {number} id - layout id
 * @param  {object} data - data from the API request
 */
let Layout = function(id, data) {
    
    // Layout properties
    this.id = 'layout_' + id;
    this.layoutId = id;

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
            return "url('" + urlsForApi['layout']['downloadBackground'].url + "?preview=1&width=" + width + "&height=" + height + "&proportional=0&layoutBackgroundId=" + this.backgroundImage + "') top center no-repeat; background-color: " + this.backgroundColor;
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

            // Mark the region as not empty
            newRegion.isEmpty = false;

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

    // Add a create change to the history array, and a option to update the Id on the change to the newly created object
    return lD.manager.addChange(
        "create",
        elementType, // targetType
        null, // targetId
        null, // oldValues
        null, // newValues
        {
            updateTargetId: true // options.updateTargetId
        }
    );
};

/**
 * Delete an element in the layout, by ID
 * @param {number} elementId - element id
 * @param {string} elementType - element type (widget, region, ...)
 */
Layout.prototype.deleteElement = function(elementType, elementId) {
    
    // Save all changes first
    return lD.manager.saveAllChanges().then((res) =>  {

        // Remove changes from the history array
        return lD.manager.removeAllChanges(lD.selectedObject.type, lD.selectedObject[lD.selectedObject.type + 'Id']).then((res) =>  {

            // Unselect selected object before deleting
            lD.selectObject();

            // Create a delete type change, upload it but don't add it to the history array
            return lD.manager.addChange(
                "delete",
                elementType, // targetType
                elementId, // targetId
                null, // oldValues
                null, // newValues
                {
                    addToHistory: false // options.addToHistory
                }
            );

        }).catch(function() {
            toastr.error('Remove all changes failed!');
        });
    }).catch(function() {
        toastr.error('Save all changes failed!');
    });
    
};

module.exports = Layout;