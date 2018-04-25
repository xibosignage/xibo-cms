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

    this.playlists = data.regionPlaylist;

    this.backgroundColor = backgroundColor;
    this.selected = false;
    this.loop = false; // Loop region widgets

    this.createdRegion = false; // user created region

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
 * Transform a region using the new values and the layout's scaling and save the values to the structure
 * @param {number} layoutId - the ID of the layout containing the region
 * @param {object=} [newValues] - Transformation values
 * @param {number} [newValues.width] - New width ( for resize tranformation )
 * @param {number} [newValues.height] - New height ( for resize tranformation )
 * @param {number} [newValues.top] - New top position ( for move tranformation )
 * @param {number} [newValues.left] - New left position ( for move tranformation )
 * @param {bool=} saveToHistory - Flag to save or not to the change history
 */
Region.prototype.transform = function(layoutId, newValues, saveToHistory = true) {

    // save old/previous values
    const oldValues = [{
        'width': this.dimensions.width,
        'height': this.dimensions.height,
        'top': this.dimensions.top,
        'left': this.dimensions.left,
        'regionid': this.regionId
    }];

    // add transform change to history manager
    if(saveToHistory) {
        lD.manager.addChange(
            "transform",
            "region",
            this.id,
            {
                url: '/region/position/all/' + layoutId,
                data: {
                    regions: JSON.stringify(oldValues)
                }
            },
            {
                url: '/region/position/all/' + layoutId,
                data: {
                    regions: JSON.stringify(newValues)
                }
            },
            false
        );
    }

    // Apply changes to the region ( updating values )
    this.dimensions.width = newValues.width;
    this.dimensions.height = newValues.height;
    this.dimensions.top = newValues.top;
    this.dimensions.left = newValues.left;

    return true;
};

module.exports = Region;