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

    this.playlists = data.regionPlaylist;

    this.backgroundColor = backgroundColor;
    this.selected = false;
    this.loop = false; // Loop region widgets

    // widget structure
    this.widgets = {};

    this.options = data.regionOptions;

    // set real dimentions
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
            if (this.widgets[widget].selected === true) {
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
 * @param {string} type Type of transformation
 * @param {object=} [values] - Transformation values
 * @param {number} [values.width] - New width ( for resize tranformation )
 * @param {number} [values.height] - New height ( for resize tranformation )
 * @param {number} [values.top] - New top position ( for move tranformation )
 * @param {number} [values.left] - New left position ( for move tranformation )
 * @param {bool=} saveToHistory - Flag to save or not to the changes history
 */
Region.prototype.saveTransformation = function(type, values, saveToHistory = true) {

    console.log('saveTransformation: ' + type);
    console.log(values);
    
    var currentValues = {
        width: this.dimensions.width,
        height: this.dimensions.height,
        top: this.dimensions.top,
        left: this.dimensions.left
    };

    // Apply changes to the region ( updating values )
    this.dimensions.width = values.width;
    this.dimensions.height = values.height;
    this.dimensions.top = values.top;
    this.dimensions.left = values.left;
};

module.exports = Region;