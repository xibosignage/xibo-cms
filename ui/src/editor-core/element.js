/* eslint-disable new-cap */
// ELEMENT Module

/**
 * Element contructor
 * @param {object} data - data from the API request
 * @param {number} widgetId - widget id
 * @param {number} regionId - region id
 */
const Element = function(data, widgetId, regionId) {
  this.widgetId = widgetId;
  this.regionId = regionId;

  this.id = data.id;
  this.elementId = data.elementId;

  this.elementType = data.elementType;

  this.left = data.left;
  this.top = data.top;
  this.width = data.width;
  this.height = data.height;
  this.rotation = data.rotation;
  this.layer = data.layer;
  this.properties = data.properties;

  // Set element to always be deletable
  this.isDeletable = true;

  this.template = {};
};

/**
 * Get the element properties (merged with template properties)
 * @return {Promise} - Element properties array
 */
Element.prototype.getProperties = function() {
  const self = this;

  return new Promise(function(resolve, reject) {
    self.getTemplate().then((template) => {
      // Create a full copy of the template object
      // (we don't want to modify the original template)
      const templateCopy = JSON.parse(JSON.stringify(template));

      // Merge template properties with element properties
      if (self.properties != undefined && templateCopy != undefined) {
        for (let j = 0; j < templateCopy.properties.length; j++) {
          const property = templateCopy.properties[j];

          // If we have a value for the property, set it
          for (let i = 0; i < self.properties.length; i++) {
            const elementProperty = self.properties[i];

            if (elementProperty.id === property.id) {
              property.value = elementProperty.value;
            }
          }
        }
      }

      // Update the element properties
      self.properties = templateCopy.properties;

      // Return the element properties in a promise
      resolve(self.properties);
    });
  });
};

/**
 * Get template
 * @return {Promise} - Promise that resolves when the template is loaded
 */
Element.prototype.getTemplate = function() {
  const self = this;
  return new Promise(function(resolve, reject) {
    // If the template is already loaded, resolve the promise
    if (self.template.id != undefined) {
      resolve(self.template);
    } else {
      lD.templateManager.getTemplateById(
        self.id,
        self.elementType,
      ).then((template) => {
        // Save the template
        self.template = template;

        // Resolve the promise
        resolve(template);
      });
    }
  });
};

/**
 * Transform an element using the new values
 * @param {object=} [transform] - Transformation values
 * @param {number} [transform.width] - New width (for resize tranformation)
 * @param {number} [transform.height] - New height (for resize tranformation)
 * @param {number} [transform.top] - New top position (for move tranformation)
 * @param {number} [transform.left] - New left position (for move tranformation)
 */
Element.prototype.transform = function(transform) {
  // Apply changes to the element ( updating values )
  (transform.width) && (this.width = transform.width);
  (transform.height) && (this.height = transform.height);
  (transform.top) && (this.top = transform.top);
  (transform.left) && (this.left = transform.left);
};

module.exports = Element;
