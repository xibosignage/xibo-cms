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
  this.groupId = data.groupId;

  // If group id is set, grab group properties
  if (this.groupId) {
    this.groupProperties = data.groupProperties;
  }

  this.id = data.id;
  this.elementId = data.elementId;
  this.elementType = data.type;

  this.left = data.left;
  this.top = data.top;
  this.width = data.width;
  this.height = data.height;
  this.rotation = data.rotation;
  this.layer = data.layer;
  this.properties = data.properties;

  // Set element to always be deletable
  this.isDeletable = true;

  // Element data from the linked widget/module
  this.data = {};

  // Element template
  this.template = {};

  // Data source index ( default = 1 )
  this.source = data.source || null;
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
      if (templateCopy != undefined) {
        for (let j = 0; j < templateCopy.properties.length; j++) {
          const property = templateCopy.properties[j];

          // If we have a value for the property, set it
          if (self.properties) {
            for (let i = 0; i < self.properties.length; i++) {
              const elementProperty = self.properties[i];

              if (elementProperty.id === property.id) {
                property.value = elementProperty.value;
                property.default = elementProperty.default;
              }
            }
          }

          // If value is unset and we have default, use it instead
          // Make replacements for the default value
          // if we have any special value
          if (String(property.default).match(/%(.*?)%/gi)) {
            const placeholder = property.default.slice(1, -1);

            switch (placeholder) {
              case 'THEME_COLOR':
                property.default = lD.viewer.themeColors[lD.viewer.theme];
                break;

              default:
                break;
            }
          }

          if (property.value == undefined) {
            property.value = property.default;
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
    if (self.template.templateId != undefined) {
      resolve(self.template);
    } else {
      lD.templateManager.getTemplateById(
        self.id,
        self.elementType,
      ).then((template) => {
        // Save the template
        self.template = template;

        // If template is an extention of another template
        // load the parent template
        if (template.extends) {
          lD.templateManager.getTemplateById(
            template.extends.template,
            'global',
          ).then((parentTemplate) => {
            // Merge the parent template properties with the template properties
            // (if the template has a property with the same id as the parent
            // template, use the template's property instead)
            self.template.parent = parentTemplate;
            const newProperties = [];

            // Loop through parent template properties
            for (let i = 0; i < parentTemplate.properties.length; i++) {
              const parentProperty = parentTemplate.properties[i];
              let found = false;

              // If property is the one in overrides, don't add it
              if (template.extends?.override == parentProperty.id) {
                continue;
              }

              // Loop through template properties
              for (let j = 0; j < template.properties.length; j++) {
                const property = template.properties[j];

                // If we have a property with the same id, use the template's
                if (property.id === parentProperty.id) {
                  found = true;
                  break;
                }
              }

              // If we didn't find a property with the same id, add it
              if (!found) {
                newProperties.push(parentProperty);
              }
            }

            // Add the new properties to the template
            self.template.properties =
              newProperties.concat(template.properties);

            // If template doesn't have onTemplateRender, use parent's
            if (!template.onTemplateRender) {
              template.onTemplateRender = parentTemplate.onTemplateRender;
            } else {
              // If onTemplateRender has the "callParent" placeholder,
              // replace it with the parent's onTemplateRender
              if (
                template.onTemplateRender &&
                template.onTemplateRender.includes('callParent')) {
                template.onTemplateRender = template.onTemplateRender
                  .replace('%callParent%', parentTemplate.onTemplateRender);
              }
            }

            return resolve(self.template);
          });
        } else {
          // Resolve the promise
          resolve(template);
        }
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

/**
 * Get linked widget data
  * @return {Promise} - Promise with widget data
 */
Element.prototype.getData = function() {
  const self = this;
  const parentWidget = lD.getElementByTypeAndId(
    'widget',
    'widget_' + this.regionId + '_' + this.widgetId,
    'canvas',
  );

  return new Promise(function(resolve, reject) {
    // If element already has data, use cached data
    if (
      self.elementType === 'global'
    ) {
      resolve();
    } else {
      const source = (self.source > 0) ? (self.source - 1) : null;
      parentWidget.getData().then((data) => {
        // Resolve the promise with the data
        resolve(data[source]);
      });
    }
  });
};

module.exports = Element;
