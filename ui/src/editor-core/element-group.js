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

ElementGroup.prototype.updateGroupDimensions = function(
  reload = false,
) {
  const self = this;
  const groupElements = Object.values(this.elements);

  const getRotatedDimensions = function(element, angle) {
    // Calculate the sine and cosine of the rotation angle.
    const sinA = Math.sin(angle * Math.PI / 180);
    const cosA = Math.cos(angle * Math.PI / 180);

    // Calculate the new width and height of the rectangle.
    const newWidth = element.width * cosA + element.height * sinA;
    const newHeight = element.width * sinA + element.height * cosA;

    // Calculate the new left and top positions of the rectangle.
    const newLeft = element.left + ((element.width - newWidth) / 2);
    const newTop = element.top + ((element.height - newHeight) / 2);

    return {
      width: Math.round(newWidth),
      height: Math.round(newHeight),
      top: Math.round(newTop),
      left: Math.round(newLeft),
    };
  };

  // Reset group properties
  self.left = null;
  self.top = null;
  self.width = null;
  self.height = null;

  // Update group dimensions based on elements
  groupElements.forEach(function(el) {
    let elTempProperties = {
      left: el.left,
      top: el.top,
      width: el.width,
      height: el.height,
    };

    // If the element has rotation, get new temporary properties
    if (el.rotation && el.rotation != 0) {
      elTempProperties = getRotatedDimensions(el, el.rotation);
    }

    // First we need to find the top/left position
    // left needs to adjust to the elements more to the left of the group
    if (
      !self.left ||
      elTempProperties.left < self.left
    ) {
      self.left = elTempProperties.left;
    }

    // top needs to adjust to the element more to the top
    if (
      !self.top ||
      elTempProperties.top < self.top
    ) {
      self.top = elTempProperties.top;
    }
  });

  // Now we need to calculate the width and height
  groupElements.forEach(function(el) {
    let elTempProperties = {
      left: el.left,
      top: el.top,
      width: el.width,
      height: el.height,
    };

    // If the element has rotation, get new temporary properties
    if (el.rotation && el.rotation != 0) {
      elTempProperties = getRotatedDimensions(el, el.rotation);
    }

    if (
      !self.width ||
      elTempProperties.left + elTempProperties.width >
      self.left + self.width
    ) {
      self.width =
        Math.round(elTempProperties.left + elTempProperties.width - self.left);
    }

    if (
      !self.height ||
      elTempProperties.top + elTempProperties.height >
      self.top + self.height
    ) {
      self.height =
        Math.round(elTempProperties.top + elTempProperties.height - self.top);
    }
  });

  if (reload) {
    const widget =
      lD.getElementByTypeAndId(
        'widget',
        'widget_' + self.regionId + '_' + self.widgetId,
        'canvas',
      );

    // Save JSON with new element into the widget
    widget.saveElements().then((_res) => {
      // Reload data and select element when data reloads
      lD.reloadData(lD.layout,
        {
          refreshEditor: true,
        });
    });
  }
};

module.exports = ElementGroup;
