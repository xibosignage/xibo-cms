// WIDGET Module

/**
 * Widget contructor
 * @param {number} id - widget id
 * @param {number} regionId - region where the widget belongs
 * @param {object} data - data from the API request
 * @param {number} layoutDuration - total duration of the layout
 */
var Widget = function(id, regionId, data, layoutDuration) {
    this.id = 'widget_' + regionId + '_' + id; // widget_regionID_widgetID
    this.regionId = 'region_' + regionId;
    this.data = data;
    this.layoutDuration = layoutDuration;
    this.selected = false;

    this.singleWidget = false;
    this.loop = false;
    this.extend = false;

    /**
     * Return the percentage for the widget on the timeline
     */
    this.durationPercentage = function() {

        // Get duration percentage based on the layout
        var duration = (this.getDuration() / this.layoutDuration) * 100;
        
        // If the widget doesn't have the loop flag and is a single widget, extend it
        if(!this.loop && this.singleWidget){
            
            // Verify if the widget duration is less than the layout duration 
            if(parseFloat(this.getDuration()) < parseFloat(this.layoutDuration)) {
                this.extend = true;
                this.extendSize = 100 - duration; // Extend size is the rest of the region width
            }
        }

        return duration;
    };

    /**
     * Return the value if the widget is selected or not for the CSS
     */
    this.selectedFlag = function() {
        return (this.selected) ? 'selected-widget' : '';
    };

    /**
     * Get widget duration ( could be differente for some widgets )
     */
    this.getDuration = function() {
        //TODO: Open create rules for special widgets ( Twitter, Video ) -> Read Document
        return this.data.duration;
    };
};

/**
 * Create clone from widget
 * @param  {number} layoutScale Layout scaling from the container to the actual layout dimensions
 */
Widget.prototype.createClone = function() {
    
    var widgetClone = {
        id: 'ghost_' + this.id,
        regionId: this.regionId,
        layoutDuration: this.layoutDuration,
        durationPercentage: function() { // so that can be calculated on template rendering time
            return (this.data.duration / this.layoutDuration) * 100;
        },
        data: {
            type: this.data.type,
            duration: this.data.duration
        }
    };

    return widgetClone;
};

module.exports = Widget;
