// CANVAS Module

/**
 * Canvas contructor
 * @param {number} id - region id
 * @param {object} data - data from the API request
 */
const Canvas = function(id, data) {
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
    width: 0,
    height: 0,
    top: 0,
    left: 0,
  };

  this.zIndex = data.zIndex;
};

module.exports = Canvas;
