// LAYOUT Module

/**
 * Layout contructor
 * @param  {number} id - layout id
 * @param  {object} data - data from the API request
 */
var Layout = function(id, data) {

    this.id = 'layout_' + id;
    this.data = data;
    this.regions = {};

    this.containerProperties = {
        width: data.width,
        height: data.height,
        top: 0,
        left: 0,
        scaleToTheOriginal: 1
    };
};

/**
 * Calculate layout values for the layout based on the scale of the container
 * @param {object} container - object to use as base to scale to
 */
Layout.prototype.scaleTo = function(container) {

    var layoutSizeRatio = this.data.width / this.data.height;
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
    this.containerProperties.scaleToTheOriginal = this.containerProperties.width / this.data.width;
}

module.exports = Layout;