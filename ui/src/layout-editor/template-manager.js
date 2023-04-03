/**
 * Template Manager
 * @param {object} parent - Parent object
 */
const TemplateManager = function(parent) {
  this.parent = parent;
  this.templates = {};
};

/**
 * Get template by id
 * @param {string} templateId
 * @param {string} templateDataType
 * @return {Promise} - Promise with the template object
*/
TemplateManager.prototype.getTemplateById = function(
  templateId,
  templateDataType,
) {
  return new Promise((resolve, reject) => {
    if (
      this.templates[templateDataType] &&
      this.templates[templateDataType][templateId]
    ) {
      resolve(this.templates[templateDataType][templateId]);
    } else {
      // If we don't have the template, we make the request by dataType
      this.getTemplateByDataType(templateDataType).then((templates) => {
        for (const template in templates) {
          if (
            templates.hasOwnProperty(template) &&
            templates[template].templateId === templateId
          ) {
            // Return the template
            resolve(templates[template]);
          }
        }

        // If we don't find the template, we reject the promise
        reject(new Error('Template not found'));
      });
    }
  });
};

/**
 * Get templated by dataType
 * @param {string} templateDataType
 * @return {Promise} - Promise with the template objects
*/
TemplateManager.prototype.getTemplateByDataType = function(
  templateDataType,
) {
  const self = this;
  return new Promise((resolve, reject) => {
    if (this.templates[templateDataType]) {
      resolve(this.templates[templateDataType]);
    } else {
      // Make the request to get all templates
      let requestPath = urlsForApi.module.getTemplates.url;
      requestPath = requestPath.replace(':dataType', templateDataType);

      $.ajax({
        url: requestPath,
        type: urlsForApi.module.getTemplates.type,
      }).done(function(res) {
        if (res.data) {
          // Save the templates in the manager
          self.templates[templateDataType] = {};
          for (let i = 0; i < res.data.length; i++) {
            self.templates[templateDataType][res.data[i].templateId] =
              res.data[i];
          }

          // Return the templates
          resolve(self.templates[templateDataType]);
        } else {
          reject(res);
        }
      });
    }
  });
};

module.exports = TemplateManager;
