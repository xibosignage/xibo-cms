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

module.exports = Canvas;
