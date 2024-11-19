// ELEMENT Module

/**
 * Element contructor
 * @param {object} data - data from the API request
 * @param {number} widgetId - widget id
 * @param {number} regionId - region id
 * @param {object} parentWidget - parent widget
 */
const ElementGroup = function(data, widgetId, regionId, parentWidget) {
  this.widgetId = widgetId;
  this.regionId = regionId;
  this.type = 'element-group';

  // Name
  this.elementGroupName = (data.elementGroupName) ? data.elementGroupName : '';

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
  this.pinSlot = (data.pinSlot) ? data.pinSlot : false;

  // Set element to have same properties for edit and delete as parent widget
  this.isEditable = (parentWidget) ? parentWidget.isEditable : true;
  this.isDeletable = (parentWidget) ? parentWidget.isDeletable : true;
  this.isViewable = (parentWidget) ? parentWidget.isViewable : true;
  this.effect = data.effect || 'noTransition';

  // Expanded on layer manager
  this.expanded = false;

  this.selected = false;
};

ElementGroup.prototype.updateSlot = function(
  slotIndex,
  forceUpdate = false,
) {
  const self = this;

  // If slotIndex is not defined, stop
  if (slotIndex === undefined) {
    return;
  }

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

ElementGroup.prototype.updatePinSlot = function(
  pinSlot,
) {
  const self = this;

  this.pinSlot = pinSlot;

  // All element in group use same slot
  Object.values(this.elements).forEach((element) => {
    element.pinSlot = self.pinSlot;
  });
};

ElementGroup.prototype.updateEffect = function(
  effect,
  forceUpdate = false,
) {
  if (
    !this.effect ||
    forceUpdate
  ) {
    this.effect = effect;
  }
};

ElementGroup.prototype.hasDataType = function() {
  const groupElements = Object.values(this.elements);
  let hasDataType = false;

  for (let index = 0; index < groupElements.length; index++) {
    const element = groupElements[index];
    if (element.hasDataType) {
      hasDataType = true;
      break;
    }
  }

  return hasDataType;
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
      self.left === null ||
      elTempProperties.left < self.left
    ) {
      self.left = elTempProperties.left;
    }

    // top needs to adjust to the element more to the top
    if (
      self.top === null ||
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
      self.width === null ||
      elTempProperties.left + elTempProperties.width >
      self.left + self.width
    ) {
      self.width =
        Math.round(elTempProperties.left + elTempProperties.width - self.left);
    }

    if (
      self.height === null ||
      elTempProperties.top + elTempProperties.height >
      self.top + self.height
    ) {
      self.height =
        Math.round(elTempProperties.top + elTempProperties.height - self.top);
    }
  });

  if (reload) {
    const widget =
      lD.getObjectByTypeAndId(
        'widget',
        'widget_' + self.regionId + '_' + self.widgetId,
        'canvas',
      );

    // Save JSON with new element into the widget
    return widget.saveElements().then((_res) => {
      // Reload data and select element when data reloads
      lD.reloadData(lD.layout,
        {
          refreshEditor: true,
        });
    });
  }

  return Promise.resolve();
};

/**
 * Transform an element group using the new values
 * @param {object=} [transform] - Transformation values
 * @param {number} [transform.width] - New width (for resize tranformation)
 * @param {number} [transform.height] - New height (for resize tranformation)
 * @param {number} [transform.top] - New top position (for move tranformation)
 * @param {number} [transform.left] - New left position (for move tranformation)
 */
ElementGroup.prototype.transform = function(transform) {
  const transformation = {
    scaleX: 0,
    scaleY: 0,
  };

  const originalDimensions = {
    width: this.width,
    height: this.height,
  };

  // Apply changes to the group ( updating values )
  if (transform.width) {
    transformation.scaleX = transform.width / this.width;
    this.width = transform.width;
  }

  if (transform.height) {
    transformation.scaleY = transform.height / this.height;
    this.height = transform.height;
  }

  if (transform.top) {
    this.top = transform.top;
  }

  if (transform.left) {
    this.left = transform.left;
  }

  const elGroup = this;

  // Apply changes to each element of the group
  Object.values(this.elements).forEach((el) => {
    const elRelativePositionScaled = {
      left: (el.left - elGroup.left) * transformation.scaleX,
      top: (el.top - elGroup.top) * transformation.scaleY,
    };

    if (el.groupScale == 1) {
      // Scale with the element
      el.transform({
        width: transformation.scaleX * el.width,
        height: transformation.scaleY * el.height,
        top: elGroup.top + elRelativePositionScaled.top,
        left: elGroup.left + elRelativePositionScaled.left,
      });
    } else {
      // Keep top and left on the same place by default
      let newTop = el.top - elGroup.top;
      let newLeft = el.left - elGroup.left;

      // If bottom bound
      if (
        el.groupScaleTypeV === 'bottom'
      ) {
        // Distance to bottom
        const distToBottom = originalDimensions.height - el.height;

        // Calculate top based on bottom position
        newTop = newTop - (distToBottom - (elGroup.height - el.height));
      } else if (
        el.groupScaleTypeV === 'middle'
      ) {
        // Group middle
        const groupMiddle = originalDimensions.height / 2;

        const newGroupMiddle = elGroup.height /2;

        // Element middle
        const elMiddle = el.height / 2;

        // Distance to middle
        const distMiddleToMiddle = groupMiddle - elMiddle;

        // Calculate top based on bottom position
        newTop = newTop + (newGroupMiddle - distMiddleToMiddle - elMiddle);
      }

      // If right bound
      if (
        el.groupScaleTypeH === 'right'
      ) {
        // Calculate left based on right position
        newLeft = newLeft - (originalDimensions.width - elGroup.width);
      } else if (
        el.groupScaleTypeV === 'middle'
      ) {
        // Group center
        const groupCenter = originalDimensions.width / 2;

        const newGroupCenter = elGroup.width /2;

        // Element center
        const elCenter = el.width / 2;

        // Distance to center
        const distCenterToCenter = groupCenter - elCenter;

        // Calculate top based on bottom position
        newLeft = newLeft + (newGroupCenter - distCenterToCenter - elCenter);
      }

      // Transform without scaling
      el.transform({
        top: elGroup.top + newTop,
        left: elGroup.left + newLeft,
      });
    }
  });
};

module.exports = ElementGroup;
