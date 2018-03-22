// REGION Module

/**
 * Region contructor
 * @param {number} id - region id
 * @param {object} data - data from the API request
 * @param {object=} [options] - Region options
 * @param {string} [options.backgroundColor="#555"] - Color for the background
 */
var Region = function(id, data, {backgroundColor = '#555'} = {}) {
    this.id = 'region_' + id;
    this.regionId = id;

    //this.data = data; //TODO: check if we need to maintain the "pure" data object

    this.playlists = data.playlists[0]; //TODO: Change the way to get the data from the API

    this.backgroundColor = backgroundColor;
    this.selected = false;
    this.loop = false; // Loop region widgets

    // widget structure
    this.widgets = {};

    this.options = data.regionOptions;

    // set default dimentions
    this.dimensions = {
        width: data.width,
        height: data.height,
        top: data.top,
        left: data.left
    };

    // container properties
    this.containerProperties = {
        width: data.width,
        height: data.height,
        top: data.top,
        left: data.left
    };

    /**
     * Return the value if the region is selectd or not for the CSS
     */
    this.selectedFlag = function() {
        for(widget in this.widgets) {
            if (this.widgets[widget].selected == true) {
                return 'selected-widget';
            }
        }

        return (this.selected) ? 'selected-region' : '';
    };

};

/**
 * Calculate region values for a region based on the scale of the container/layout
 * @param  {number} layoutScale Layout scaling from the container to the actual layout dimensions
 */
Region.prototype.scaleTo = function(layoutScale) {
    // Loop through the container properties and scale them according to the layout scale from the original
    for(var property in this.containerProperties) {
        if(this.containerProperties.hasOwnProperty(property)) {
            this.containerProperties[property] = this.dimensions[property] * layoutScale;
        }
    }
};

/**
 * Transform a region using the new values and the layout's scaling and save the values to the structure
 * @param  {number} width Region new width
 * @param  {number} height Region new height
 * @param  {number} top Region new top
 * @param  {number} left Region new left
 * @param  {number} layoutScale Layout scaling from the container to the actual layout dimensions
 */
Region.prototype.saveTransformation = function(width, height, top, left, layoutScale) {

    // Change data structure properties
    this.width = width / layoutScale;
    this.height = height / layoutScale;
    this.top = top / layoutScale;
    this.left = left / layoutScale;
};

module.exports = Region;