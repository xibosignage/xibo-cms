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

  // Data slot index
  this.slot = data.slot;

  // Set element to always be deletable
  this.isDeletable = true;
};

ElementGroup.prototype.updateSlot = function(
  slotIndex,
  forceUpdate = false,
) {
  const self = this;

  if (
    !this.slot ||
    Number(slotIndex) > this.slot ||
    forceUpdate
  ) {
    this.slot = Number(slotIndex);
  }

  // All element in group use same slot
  Object.values(this.elements).forEach((element) => {
    element.slot = self.slot;
  });
};

module.exports = ElementGroup;
