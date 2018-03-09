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

    this.durationPercentage = function() {

        var duration = Math.round((this.data.duration / this.layoutDuration) * 100);

        if(!this.loop && this.singleWidget){
            if(this.data.useDuration == '0') {
                duration = 100;
            } else {
                this.extend = true;
                this.extendSize = 100 - duration;
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
};

module.exports = Widget;
