// ELEMENT Module

/**
 * Element contructor
 * @param {object} data - data from the API request
 * @param {number} widgetId - widget id
 * @param {number} regionId - region id
 * @param {object} parentWidget - parent widget
 */
const Element = function(data, widgetId, regionId, parentWidget) {
  this.type = 'element';
  this.widgetId = widgetId;
  this.regionId = regionId;
  this.groupId = data.groupId;

  // Name
  this.elementName = (data.elementName) ? data.elementName : '';

  // If group id is set, grab group properties
  if (this.groupId) {
    this.groupProperties = data.groupProperties;
    this.group = {};
  }

  this.id = data.id;
  this.elementId = data.elementId;
  this.elementType = (data.elementType) ? data.elementType : data.type;

  // has data type ( if it's not global )
  this.hasDataType = (data.type != 'global');

  this.left = data.left;
  this.top = data.top;
  this.width = data.width;
  this.height = data.height;
  this.rotation = data.rotation;
  this.layer = data.layer;
  this.properties = data.properties;

  // Set element to have same properties for edit and delete as parent widget
  this.isEditable = (parentWidget) ? parentWidget.isEditable : true;
  // For elements to be deletable, the parent widget also needs to be editable
  this.isDeletable = (parentWidget) ?
    (
      parentWidget.isDeletable &&
      parentWidget.isEditable
    ) : true;
  this.isViewable = (parentWidget) ? parentWidget.isViewable : true;

  // Check if the element is visible on rendering ( true by default )
  this.isVisible = (data.isVisible === undefined) ? true : data.isVisible;

  // Element data from the linked widget/module
  this.data = {};

  // Element template
  this.template = {};

  // Can rotate?
  this.canRotate = false;

  // Data slot index
  this.slot = data.slot;
  this.pinSlot = (data.pinSlot) ? data.pinSlot : false;

  // Group scale
  this.groupScale = (data.groupScale != undefined) ?
    data.groupScale : 1;
  this.groupScaleTypeV = (data.groupScaleTypeV != undefined) ?
    data.groupScaleTypeV : 'top';
  this.groupScaleTypeH = (data.groupScaleTypeH != undefined) ?
    data.groupScaleTypeH : 'left';

  // Animation effect
  this.effect = data.effect || 'noTransition';

  // Media id and name
  this.mediaId = data.mediaId;
  this.mediaName = data.mediaName;

  this.selected = false;
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

      // If type is wrong, or not defined, change it to the template's
      if (
        typeof self.elementType === 'undefined' ||
        template.dataType != self.elementType
      ) {
        self.elementType = template.dataType;
      }

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

        // Check if element has rotation and set it
        if (templateCopy.canRotate) {
          self.canRotate = templateCopy.canRotate;
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
        // If template is an extention of another template
        // load the parent template
        if (template.extends) {
          lD.templateManager.getTemplateById(
            template.extends.template,
            'global',
          ).then((parentTemplate) => {
            // Save the template only after we get the parent
            self.template = template;

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
              template.properties.concat(newProperties);

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
          // Save the template
          self.template = template;

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
 * @param {number} [transform.rotation] - New rotation
 */
Element.prototype.transform = function(transform) {
  // Apply changes to the element ( updating values )
  (transform.width) && (this.width = transform.width);
  (transform.height) && (this.height = transform.height);
  (transform.top) && (this.top = transform.top);
  (transform.left) && (this.left = transform.left);
  (transform.rotation) && (this.rotation = transform.rotation);
};

/**
 * Get linked widget data
  * @return {Promise} - Promise with widget data
 */
Element.prototype.getData = function() {
  const self = this;
  const parentWidget = lD.getObjectByTypeAndId(
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
      const slot = self.slot ? self.slot : 0;
      const loaderTargetId = (self.groupId) ?
        self.groupId : self.elementId;

      // Show loader on element or group
      lD.viewer.toggleLoader(loaderTargetId, true);

      parentWidget.getData().then(({data, meta}) => {
        // Show loader on element or group
        lD.viewer.toggleLoader(loaderTargetId, false);

        // Resolve the promise with the data
        // If slot is outside the data array
        // restart from 0
        resolve({data: data[slot % data.length], meta});
      });
    }
  });
};

/**
 * Replace media in element
 * @param {string} mediaId
 * @param {string} mediaName
 * @return {Promise} - Promise with widget data
 */
Element.prototype.replaceMedia = function(mediaId, mediaName) {
  const self = this;
  const parentWidget = lD.getObjectByTypeAndId(
    'widget',
    'widget_' + this.regionId + '_' + this.widgetId,
    'canvas',
  );

  // Replace media id
  self.mediaId = mediaId;
  self.mediaName = mediaName;

  return parentWidget.saveElements();
};

module.exports = Element;
