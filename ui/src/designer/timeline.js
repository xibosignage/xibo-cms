// TIMELINE Module

// Load templates
const timelineTemplate = require('../templates/timeline.hbs');

/**
 * Timeline contructor
 * @param {object} container - the container to render the timeline to
 * @param {object =} [options] - Timeline options
 * @param {number} layoutDuration - total duration of the layout
 */
var Timeline = function(container, layoutDuration) {
    this.DOMObject = container;

    this.layoutDuration = layoutDuration;

    this.scrollPercent = {
        left: 0,
        right: 0
    };

    // Properties to be used for the template
    this.properties = {
        zoom: 100,
        minTime: 0,
        maxTime: layoutDuration,
        zoomInDisable: '',
        zoomOutDisable: 'disabled'
    };
};

/**
 * Change timeline zoom
 * @param {number} zoom - the change to be applied to zoom ( -1:zoomOut, 0: default, 1: zoomIn )
 */
Timeline.prototype.changeZoom = function(zoom) {

    // Calculate new zoom value
    var newZoom = Math.round(this.properties.zoom + (10 * zoom));
    
    // Reset zoom enable flags
    this.properties.zoomOutDisable = this.properties.zoomInDisable = '';

    // If zoom out is 100% or less ( or zoom has been defaulted ) disable button limit it to 100%
    if( newZoom <= 100 || zoom == 0){
        newZoom = 100;
        this.properties.zoomOutDisable = 'disabled';
    }

    // Set the zoom and calculate the max time for the ruler
    this.properties.zoom = newZoom;

    // Set labels
    var rightPercentage = Math.round((100 / newZoom) * 100);
    this.calculateLabels(0, rightPercentage);
};

/**
 * Calculate timeline labels
 * @param {percLeft} zoom - Percentage related to the zoom and current scroll, on the left
 * @param {percRight} zoom - Percentage related to the zoom and current scroll, on the right
 */
Timeline.prototype.calculateLabels = function (percLeft, percRight) {
    this.properties.minTime = Math.round( 10 * ((percLeft / 100) * (this.layoutDuration / (this.properties.zoom / 100))) ) / 10;
    this.properties.maxTime = Math.round( 10 * ((percRight / 100) * this.layoutDuration) ) / 10;

    this.DOMObject.find('#minTime').html(this.properties.minTime + 's');
    this.DOMObject.find('#maxTime').html(this.properties.maxTime + 's');
};


/**
 * Render Timeline and the layout
 * @param {Object} layout - the layout object to be rendered
 */
Timeline.prototype.render = function(layout) {

    console.log('Timeline -> Render');
    console.log(layout.regions);
    
    var html = timelineTemplate({layout: layout, properties: this.properties});
    var self = this;

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Enable hover and select for each layout/region
    this.DOMObject.find('.selectable').click(function(e) {
        e.stopPropagation();
        selectObject($(this));
    });

    // Button actions
    this.DOMObject.find('#zoomIn').click(function() {
        self.changeZoom(1);
        self.render(layout);
    });

    this.DOMObject.find('#zoomOut').click(function() {
        self.changeZoom(-1);
        self.render(layout);
    });

    this.DOMObject.find('#zoom').click(function() {
        self.changeZoom(0);
        self.render(layout);
    });

    this.DOMObject.find('#regions-container').scroll(function() {
        var currLeft = $(this).scrollLeft();
        var postWidth = $(this).width();
        var scrollWidth = $(this).find('#regions').width();

        // Calculate left and right percentage
        var scrollPercentLeft = (currLeft / postWidth) * 100;
        var scrollPercentRight = ((postWidth + currLeft)/scrollWidth)*100;

        self.calculateLabels(scrollPercentLeft, scrollPercentRight);
    });
    
};

module.exports = Timeline;
