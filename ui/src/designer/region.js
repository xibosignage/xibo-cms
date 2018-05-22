// REGION Module

/**
 * Region contructor
 * @param {number} id - region id
 * @param {object} data - data from the API request
 * @param {object=} [options] - Region options
 * @param {string} [options.backgroundColor="#555555ed"] - Color for the background
 */
let Region = function(id, data, {backgroundColor = '#555555ed'} = {}) {
    this.id = 'region_' + id;
    this.regionId = id;
    this.type = 'region';

    this.playlists = data.regionPlaylist;

    this.backgroundColor = backgroundColor;
    this.selected = false;
    this.loop = false; // Loop region widgets

    this.isEmpty = true; // If the region has widgets or not

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
 * Transform a region using the new values and the layout's scaling and save the values to the structure
 * @param {object=} [transform] - Transformation values
 * @param {number} [transform.width] - New width ( for resize tranformation )
 * @param {number} [transform.height] - New height ( for resize tranformation )
 * @param {number} [transform.top] - New top position ( for move tranformation )
 * @param {number} [transform.left] - New left position ( for move tranformation )
 * @param {bool=} saveToHistory - Flag to save or not to the change history
 */
Region.prototype.transform = function(transform, saveToHistory = true) {
    
    // add transform change to history manager
    if(saveToHistory) {

        // save old/previous values
        const oldValues = [{
            'width': this.dimensions.width,
            'height': this.dimensions.height,
            'top': this.dimensions.top,
            'left': this.dimensions.left,
            'regionid': this.regionId
        }];

        const newValues = [{
            'width': transform.width,
            'height': transform.height,
            'top': transform.top,
            'left': transform.left,
            'regionid': this.regionId
        }];

        // Add a tranform change to the history array, but send the upload flag as false, so that the change is temporarily local
        lD.manager.addChange(
            "transform",
            "region",
            this.regionId,
            {
                regions: JSON.stringify(oldValues)
            },
            {
                regions: JSON.stringify(newValues)
            },
            false
        );
    }

    // Apply changes to the region ( updating values )
    this.dimensions.width = transform.width;
    this.dimensions.height = transform.height;
    this.dimensions.top = transform.top;
    this.dimensions.left = transform.left;
};

module.exports = Region;