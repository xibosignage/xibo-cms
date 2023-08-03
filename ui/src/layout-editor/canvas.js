// CANVAS Module

/**
 * Canvas contructor
 * @param {number} id - region id
 * @param {object} data - data from the API request
 * @param {object} layoutDimensions - layout dimensions
 */
const Canvas = function(id, data, layoutDimensions) {
  this.id = 'region_' + id;
  this.regionId = id;

  this.type = 'region';
  this.subType = 'canvas';

  this.name = data.name;
  this.playlists = data.regionPlaylist;
  this.loop = false; // Loop region widgets

  // Widgets
  this.widgets = {};

  this.options = data.regionOptions;

  // Permissions
  this.isEditable = data.isEditable;
  this.isDeletable = data.isDeletable;
  this.isPermissionsModifiable = data.isPermissionsModifiable;

  // Interactive actions
  this.actions = data.actions;

  // set dimentions
  this.dimensions = {
    width: layoutDimensions.width,
    height: layoutDimensions.height,
    top: 0,
    left: 0,
  };

  this.zIndex = data.zIndex;
};


/**
 * Change canvas layer
 * @param {number} [newLayer] - New left position (for move tranformation)
 * @param {bool=} saveToHistory - Flag to save or not to the change history
 */
Canvas.prototype.changeLayer = function(newLayer, saveToHistory = true) {
  // add transform change to history manager
  if (saveToHistory) {
    // save old/previous values
    const oldValues = [{
      width: this.dimensions.width,
      height: this.dimensions.height,
      top: this.dimensions.top,
      left: this.dimensions.left,
      zIndex: this.zIndex,
      regionid: this.regionId,
    }];

    // Update new values if they are provided
    const newValues = [{
      width: this.dimensions.width,
      height: this.dimensions.height,
      top: this.dimensions.top,
      left: this.dimensions.left,
      zIndex: (newLayer != undefined) ?
        newLayer : this.zIndex,
      regionid: this.regionId,
    }];

    // Add a tranform change to the history array
    lD.historyManager.addChange(
      'transform',
      'region',
      this.regionId,
      {
        regions: JSON.stringify(oldValues),
      },
      {
        regions: JSON.stringify(newValues),
      },
      {
        upload: true, // options.upload
      },
    ).catch((error) => {
      toastr.error(errorMessagesTrans.transformRegionFailed);
      console.log(error);
    });
  }

  // Apply changes to the canvas ( updating values )
  this.zIndex = (newLayer != undefined) ?
    newLayer : this.zIndex;
};

module.exports = Canvas;
