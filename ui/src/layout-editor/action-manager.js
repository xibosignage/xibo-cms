/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

const confirmationModalTemplate =
  require('../templates/confirmation-modal.hbs');

/**
 * Action Manager
 * @param {object} parent - Parent object
 */
const ActionManager = function(parent) {
  this.parent = parent;
  this.actions = {};

  this.editing = {};

  this.widgetEditing = null;
};

/**
 * Add action
 * @return {Promise}
  */
ActionManager.prototype.getAllActions = function(layoutId) {
  const self = this;
  return new Promise((resolve, reject) => {
    $.ajax({
      url: urlsForApi.actions.get.url,
      type: urlsForApi.actions.get.type,
      dataType: 'json',
      data: {
        layoutId: layoutId,
      },
    }).done(function(res) {
      // Add actions to array
      self.actions = res.data.reduce((accumulator, action) => {
        accumulator[action.actionId] = action;
        return accumulator;
      }, {});

      // Resolve with data
      resolve(self.actions);
    }).fail(function(_data) {
      toastr.error(
        errorMessagesTrans.actionsGetFailed,
        errorMessagesTrans.error,
      );

      reject(errorMessagesTrans.actionsGetFailed);
    });
  });
};

/**
 * Add action
 * @param {object} $form - New action form
 * @param {string} layoutId - Layout to add action to
 * @return {Promise}
  */
ActionManager.prototype.addAction = function($form, layoutId) {
  const dataToSave = $($form).serializeObject();

  // Add layout Id
  dataToSave.layoutId = layoutId;

  // If source is types screen, change it to layout
  (dataToSave.source === 'screen') &&
    (dataToSave.source = 'layout');

  return new Promise((resolve, reject) => {
    $.ajax({
      url: urlsForApi.actions.add.url,
      type: urlsForApi.actions.add.type,
      data: dataToSave,
    }).done(function(_res) {
      if (_res.success) {
        resolve(_res);
      } else {
        reject(_res);
      }
    }).fail((_res) => {
      reject(_res);
    });
  });
};

/**
 * Save action
 * @param {object} $form - Form to get data from
 * @param {string} actionId - Action to save
 * @return {Promise}
 */
ActionManager.prototype.saveAction = function($form, actionId) {
  const requestURL = urlsForApi.actions.edit.url.replace(
    ':id',
    actionId,
  );

  // TODO: Check if action hasn't changed

  return new Promise((resolve, reject) => {
    $.ajax({
      url: requestURL,
      type: urlsForApi.actions.edit.type,
      data: $form.serialize(),
    }).done(function(_res) {
      if (_res.success) {
        resolve(_res);
      } else {
        reject(_res);
      }
    }).fail(function(_res) {
      reject(_res);
    });
  });
};

/**
 * Delete action
 * @param {object} action
  */
ActionManager.prototype.deleteAction = function(action) {
  const app = this.parent;

  // Show confirmation modal
  const $modal = $(confirmationModalTemplate(
    {
      title: editorsTrans.actions.deleteModal.title,
      message: editorsTrans.actions.deleteModal.message,
      buttons: {
        cancel: {
          label: editorsTrans.actions.deleteModal.buttons.cancel,
          class: 'btn-default cancel',
        },
        delete: {
          label: editorsTrans.actions.deleteModal.buttons.delete,
          class: 'btn-danger confirm',
        },
      },
    },
  ));

  const removeModal = function() {
    $modal.modal('hide');
    // Remove modal
    $modal.remove();

    // Remove backdrop
    $('.modal-backdrop.show').remove();
  };

  // Add modal to the DOM
  app.editorContainer.append($modal);

  // Show modal
  $modal.modal('show');

  return new Promise((resolve, reject) => {
    // Confirm button
    $modal.find('button.confirm').on('click', function() {
      const requestURL = urlsForApi.actions.delete.url.replace(
        ':id',
        action.actionId,
      );

      $.ajax({
        url: requestURL,
        type: urlsForApi.actions.delete.type,
      }).done(function(_res) {
        // Remove modal
        removeModal();

        // Resolve with true for action deleted
        resolve(true);
      }).fail(function(_data) {
        toastr.error(
          errorMessagesTrans.replace('%error%', _data.message),
          errorMessagesTrans.error,
        );

        reject(new Error('%error%', _data.message));
      });
    });

    // Cancel button
    $modal.find('button.cancel').on('click', () => {
      // Remove modal
      removeModal();

      // Resolve with false for action not deleted
      resolve(false);
    });
  });
};

module.exports = ActionManager;
