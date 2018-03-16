// REGION Module

/**
 * Region contructor
 * @param {number} id - region id
 * @param {object} data - data from the API request
 * @param {number} layoutDuration - total duration of the layout
 * @param {object=} [options] - Region options
 * @param {string} [options.backgroundColor="#555"] - Color for the background
 */
var Region = function(id, data, layoutDuration, {backgroundColor = '#555'} = {}) {
    this.id = 'region_' + id;
    this.data = data;
    this.backgroundColor = backgroundColor;
    this.selected = false;
    this.loop = false; // Loop region widgets
    this.layoutDuration = layoutDuration;
    this.widgets = {};

    this.containerProperties = {
        width: this.data.width,
        height: this.data.height,
        top: this.data.top,
        left: this.data.left
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
            this.containerProperties[property] = this.data[property] * layoutScale;
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
    console.log('Region - saveTransformation');

    // Change data structure properties
    this.data.width = width / layoutScale;
    this.data.height = height / layoutScale;
    this.data.top = top / layoutScale;
    this.data.left = left / layoutScale;
};

module.exports = Region;