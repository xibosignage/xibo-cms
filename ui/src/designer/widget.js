// WIDGET Module

/**
 * Widget contructor
 * @param {number} id - widget id
 * @param {number} regionId - region where the widget belongs
 * @param {object} data - data from the API request
 */
var Widget = function(id, regionId, data) {
    this.id = 'widget_' + regionId + '_' + id; // widget_regionID_widgetID
    this.regionId = 'region_' + regionId;
    
    // widget type
    this.subType = data.type;

    this.layoutDuration = 0;
    this.selected = false;

    this.singleWidget = false;
    this.loop = false;
    this.extend = false;

    //TODO: check if we need to maintain the "pure" data object
    this.data = data; 

    /**
     * Return the percentage for the widget on the timeline
     * @returns {number} - Widget duration percentage related to the layout duration
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
     * Get an object containing options returned from the back end
     * @returns {object} - Options object
     */
    this.getOptions = function() {

        var options = {};

        for(option in this.data.widgetOptions) {
            let currOption = this.data.widgetOptions[option];

            if(currOption.type == 'attrib'){
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
        
        //TODO: Open create rules for special widgets ( Twitter, Video ) -> Read Document

        if(recalculate || this.duration == null){

            var calculatedDuration = 0;
            var options = this.getOptions();

            console.log(' ');
            console.log('0 Widget.getDuration ' + this.data.type + ' ' + this.id + '(' + this.regionId + ')');
            
            // Find item duration
            if(this.data.useDuration) {
                console.log('  0.1 use duration given');
                calculatedDuration = this.data.duration
            } else {
                console.log('  0.2 get default duration by module');
                console.log('    Type: ' + this.data.type);

                console.log('    Loop enabled: ' + this.loop);
                console.log('    Single widget:' + this.singleWidget);

                console.log(options);

                //TODO: calculate based on type
            }

            console.log('1 calculatedDuration: ' + calculatedDuration);

            // If duratons is per item, multiply it by the number of items ( or items divided by the items per page )
            if(options['durationIsPerItem'] == 1) {

                console.log('  1.1 Durations is per item...');
                if(options['numItems'] != null) {
                    if(options['itemsPerPage'] != null) {
                        var numPages = (options['numItems'] / options['itemsPerPage']);
                        console.log('    1.1.1 Multiply by the number of pages: ' + numPages + ' (' + options['numItems'] + '/' + options['itemsPerPage'] + ')');
                        calculatedDuration = calculatedDuration * numPages;
                    } else {
                        console.log('    1.1.2 Multiply by the number of items: ' + options['numItems']);
                        calculatedDuration = calculatedDuration * options['numItems'];
                    }

                } else {
                    console.log('    1.1.3 Item number is not defined!!!');
                    //TODO: use a best guess for this?
                }
            }

            console.log('2 Final calculatedDuration FE : ' + calculatedDuration + ' BE: ' + this.data.calculatedDuration);
            
            // set the duration to the widget
            this.duration = calculatedDuration
        }

        return this.duration;
    };
};

/**
 * Create clone from widget
 * @param  {number} layoutScale Layout scaling from the container to the actual layout dimensions
 */
Widget.prototype.createClone = function() {
    
    var widgetClone = {
        id: 'ghost_' + this.id,
        subType: this.subType,
        duration: this.getDuration(),
        regionId: this.regionId,
        layoutDuration: this.layoutDuration,
        durationPercentage: function() { // so that can be calculated on template rendering time
            return (this.duration / this.layoutDuration) * 100;
        }
    };

    return widgetClone;
};

module.exports = Widget;
