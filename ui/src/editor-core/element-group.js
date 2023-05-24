/* eslint-disable new-cap */
// ELEMENT Module

/**
 * Element contructor
 * @param {object} data - data from the API request
 * @param {number} widgetId - widget id
 * @param {number} regionId - region id
 */
const ElementGroup = function(data, widgetId, regionId) {
  this.widgetId = widgetId;
  this.regionId = regionId;
  this.type = 'element-group';

  this.id = data.id;
  this.left = data.left;
  this.top = data.top;
  this.width = data.width;
  this.height = data.height;
  this.rotation = data.rotation;
  this.layer = data.layer;
  this.elements = {};

  // Group data source ( applies to all group elements )
  this.source = data.source || null;

  // Set element to always be deletable
  this.isDeletable = true;
};

ElementGroup.prototype.updateSource = function(
  sourceIndex,
  forceUpdate = false,
) {
  const self = this;

  if (
    Number(sourceIndex) > this.source ||
    forceUpdate
  ) {
    this.source = Number(sourceIndex);
  }

  // All element in group use same source
  Object.values(this.elements).forEach((element) => {
    element.source = self.source;
  });
};

module.exports = ElementGroup;
