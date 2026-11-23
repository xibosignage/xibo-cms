/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

/**
 * Template Manager
 * @param {object} parent - Parent object
 */
const TemplateManager = function(parent) {
  this.parent = parent;
  this.templates = {};

  // Cached requests
  this.requests = {};
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
  const self = this;

  const clearRequest = function() {
    // Clear the request
    delete self.requests[templateDataType + '_' + templateId];
  };

  // If we have the template request, we return it
  if (self.requests[templateDataType + '_' + templateId]) {
    return self.requests[templateDataType + '_' + templateId];
  }

  // If we don't have the template, we make the request by dataType
  self.requests[
    templateDataType + '_' + templateId
  ] = new Promise((resolve, reject) => {
    if (
      this.templates[templateDataType] &&
      this.templates[templateDataType][templateId]
    ) {
      // Clear the request
      clearRequest();

      // Return the template
      resolve(this.templates[templateDataType][templateId]);
    } if (
      // If template is global
      this.templates['global'] &&
      this.templates['global'][templateId]
    ) {
      // Clear the request
      clearRequest();

      // Return the template
      resolve(this.templates['global'][templateId]);
    } else {
      // If we don't have the template, we make the request by dataType
      this.getTemplateByDataType(templateDataType).then((templates) => {
        for (const template in templates) {
          if (
            templates.hasOwnProperty(template) &&
            templates[template].templateId === templateId
          ) {
            // Clear the request
            clearRequest();

            // Return the template
            resolve(templates[template]);
          }
        }

        // If we don't find the template, try to find it in global templates
        this.getTemplateByDataType('global').then((templates) => {
          for (const template in templates) {
            if (
              templates.hasOwnProperty(template) &&
              templates[template].templateId === templateId
            ) {
              // Clear the request
              clearRequest();

              // Return the template
              resolve(templates[template]);
            }
          }

          // Clear the request
          clearRequest();

          // If we don't find the template, we reject the promise
          reject(new Error('Template not found'));
        });
      });
    }
  });

  // Return the request
  return self.requests[templateDataType + '_' + templateId];
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

  const clearRequest = function() {
    // Clear the request
    delete self.requests[templateDataType];
  };

  // If we already have a request for this dataType, we return it
  if (self.requests[templateDataType]) {
    return self.requests[templateDataType];
  }

  // If we don't have the templates, we make the request
  self.requests[
    templateDataType
  ] = new Promise((resolve, reject) => {
    if (this.templates[templateDataType]) {
      // Clear the request
      clearRequest();

      // Return the templates
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

          // Clear the request
          clearRequest();

          // Return the templates
          resolve(self.templates[templateDataType]);
        } else {
          // Clear the request
          clearRequest();

          // Reject the promise
          reject(res);
        }
      });
    }
  });

  // Return the request
  return self.requests[templateDataType];
};

module.exports = TemplateManager;
