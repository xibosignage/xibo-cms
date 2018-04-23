// WIDGET Module

/**
 * Widget contructor
 * @param {number} id - widget id
 * @param {number} regionId - region where the widget belongs
 * @param {object} data - data from the API request
 */
let Widget = function(id, regionId, data) {
    this.id = 'widget_' + regionId + '_' + id; // widget_regionID_widgetID
    this.widgetId = id;
    this.regionId = 'region_' + regionId;
    
    // widget type
    this.subType = data.type;

    this.selected = false;

    this.singleWidget = false;
    this.loop = false;
    this.extend = false;

    // by default, the widget duration is null ( to be calculated )
    this.duration = null;

    this.widgetDurationNotSet = false;
    this.widgetDefaultDuration = 10; // in the case of the duration has not being calculated

    //TODO: check if we need to maintain the "pure" data object
    this.data = data; 

    /**
     * Return the percentage for the widget on the timeline
     * @returns {number} - Widget duration percentage related to the layout duration
     */
    this.durationPercentage = function() {

        // Get duration percentage based on the layout
        const duration = (this.getDuration() / lD.layout.duration) * 100;
        
        // If the widget doesn't have the loop flag and is a single widget, extend it
        if(!this.loop && this.singleWidget){
            
            // Verify if the widget duration is less than the layout duration 
            if(parseFloat(this.getDuration()) < parseFloat(lD.layout.duration)) {
                this.extend = true;
                this.extendSize = 100 - duration; // Extend size is the rest of the region width
            }
        }

        return duration;
    };


    /**
     * Get an object containing options returned from the back end
     * @returns {object} - Options object
     */
    this.getOptions = function() {

        let options = {};

        for(option in this.data.widgetOptions) {
            const currOption = this.data.widgetOptions[option];

            if(currOption.type === 'attrib'){
                options[currOption.option] = currOption.value;
            }
        }
        
        return options;
    };

    /**
     * Return the value if the widget is selected or not for the CSS
     * @returns {string} - Selected flag, to change widget selection on templates
     */
    this.selectedFlag = function() {
        return (this.selected) ? 'selected-widget' : '';
    };

    /**
     * Get widget calculated duration ( could be differente for some widgets )
     * @param {boolean=} [recalculate = false] - Force the duration to be recalculated
     * @returns {number} - Widget duration in seconds
     * @
     */
    this.getDuration = function(recalculate = false) {

        if(recalculate || this.duration === null){

            let calculatedDuration = parseFloat(this.data.calculatedDuration);
            const options = this.getOptions();

            // if calculated duration is not calculated, see it to the default duration 
            if(calculatedDuration === 0) {
                calculatedDuration = this.widgetDefaultDuration;
            }
            
            // set the duration to the widget
            this.duration = calculatedDuration
        }

        return this.duration;
    };
};

/**
 * Create clone from widget
 */
Widget.prototype.createClone = function() {
    
    const widgetClone = {
        id: 'ghost_' + this.id,
        subType: this.subType,
        duration: this.getDuration(),
        regionId: this.regionId,
        durationPercentage: function() { // so that can be calculated on template rendering time
            return (this.duration / lD.layout.duration) * 100;
        }
    };

    return widgetClone;
};

module.exports = Widget;
