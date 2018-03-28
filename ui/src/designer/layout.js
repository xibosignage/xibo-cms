// LAYOUT Module
const Region = require('./region.js');
const Widget = require('./widget.js');


/**
 * Layout contructor
 * @param  {number} id - layout id
 * @param  {object} data - data from the API request
 */
var Layout = function(id, data) {

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
    var layoutDuration = 0;

    // Create regions and add them to the layout
    for(var region in data.regions) {
        var regionDuration = 0;

        var newRegion = new Region(
            data.regions[region].regionId,
            data.regions[region]
        );

        // Widgets
        var widgets = newRegion.playlists.widgets;

        // Create widgets for this region
        for(var widget in widgets) {

            var newWidget = new Widget(
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
    
    for(var region in this.regions) {
        var currRegion = this.regions[region];

        // Widgets
        var widgets = currRegion.widgets;
        var loopSingleWidget = false;
        var singleWidget = false;

        // If there is only one widget in the playlist, check the loop option for that region
        if(widgets.length === 1) {

            singleWidget = true;
            // Check the loop option
            for(var option in currRegion.options) {
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

        for(var widget in widgets) {
            var currWidget = widgets[widget];

            // If the widget needs to be extended
            currWidget.singleWidget = singleWidget;
            currWidget.loop = loopSingleWidget;
        }
    }
};

/**
 * Calculate layout values for the layout based on the scale of the container
 * @param {object} container - object to use as base to scale to
 */
Layout.prototype.scaleTo = function(container) {

    var layoutSizeRatio = this.width / this.height;
    var containerWidth = container.DOMObject.width();
    var containerHeight = container.DOMObject.height();
    var containerSizeRatio = containerWidth / containerHeight;
    var containerPadding = Math.min(containerWidth, containerHeight) * container.paddingPercentage;

    if(layoutSizeRatio > containerSizeRatio) { // If the layout W/H is bigger than the container
        // Calculate width and height 
        this.containerProperties.width = Math.floor(containerWidth - (containerPadding * 2));
        this.containerProperties.height = Math.floor(this.containerProperties.width / layoutSizeRatio);

        // Calculate position of the layout
        this.containerProperties.left = Math.floor(containerPadding);
        this.containerProperties.top = Math.floor(containerHeight / 2 - this.containerProperties.height / 2);

    } else { // If the layout W/H is smaller than the container
        // Calculate width and height 
        this.containerProperties.height = Math.floor(containerHeight - (containerPadding * 2));
        this.containerProperties.width = Math.floor(this.containerProperties.height * layoutSizeRatio);

        // Calculate position of the layout
        this.containerProperties.top = Math.floor(containerPadding);
        this.containerProperties.left = Math.floor(containerWidth / 2 - this.containerProperties.width / 2);
    }

    // Calculate scale from the original
    this.containerProperties.scaleToTheOriginal = this.containerProperties.width / this.width;
};

module.exports = Layout;