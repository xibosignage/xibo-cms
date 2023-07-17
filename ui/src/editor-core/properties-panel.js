/* eslint-disable new-cap */
// PROPERTIES PANEL Module

const loadingTemplate = require('../templates/loading.hbs');
const messageTemplate = require('../templates/properties-panel-message.hbs');
const propertiesPanelTemplate = require('../templates/properties-panel.hbs');
const actionsFormTabTemplate =
  require('../templates/actions-form-tab-template.hbs');
const actionsFormContentTemplate =
  require('../templates/actions-form-content-template.hbs');
const actionFormElementTemplate =
  require('../templates/actions-form-element-template.hbs');
const actionFormElementEditTemplate =
    require('../templates/actions-form-element-edit-template.hbs');
const formTemplates = {
  widget: require('../templates/forms/widget.hbs'),
  region: require('../templates/forms/region.hbs'),
  layout: require('../templates/forms/layout.hbs'),
  position: require('../templates/forms/position.hbs'),
};

/**
 * Properties panel contructor
 * @param {object} parent - the parent object
 * @param {object} container - the container to render the panel to
 */
const PropertiesPanel = function(parent, container) {
  this.parent = parent;
  this.DOMObject = container;

  // Initialy loaded data on the form
  this.formSerializedLoadData = '';

  this.inlineEditor = false;

  this.openTabOnRender = '';

  this.actionForm = {};

  this.toSave = false;
};

/**
 * Save properties from the panel form
 * @param {object=} target - the element that the form relates to
 * @param {boolean=} [reloadAfterSave=true] - Refresh editor after save request
 * @param {boolean=} [showErrorMessages=true] - Display error messages
 * @param {function=} [callback=null] - Callback to be called after request
 * @param {function=} [callbackNoWait=null]
 *   - Callback to be called before request ends
 * @return {boolean} - false if form is invalid
 */
PropertiesPanel.prototype.save = function(
  {
    target = null,
    reloadAfterSave = true,
    showErrorMessages = true,
    callback = null,
    callbackNoWait = null,
  } = {},
) {
  const app = this.parent;
  const self = this;
  const form = $(this.DOMObject).find('form');
  let savingElement = false;
  let savingElementGroup = false;

  // If target isn't set, use selected object
  if (!target) {
    target = app.selectedObject;
  }

  // If main container has inline editing class, remove it
  app.editorContainer.removeClass('inline-edit-mode');

  // If inline editor and viewer exist
  if (this.inlineEditor && (typeof app.viewer != 'undefined')) {
    app.viewer.hideInlineEditor();
  }

  // If target is element or element group
  // switch the target to be the widget of that element
  if (
    target.type === 'element' ||
    target.type === 'element-group'
  ) {
    const oldTarget = target;

    target = app.getElementByTypeAndId(
      'widget',
      'widget_' + oldTarget.regionId + '_' + oldTarget.widgetId,
      'canvas',
    );

    // Reset element's parent widget data
    // So it can be reloaded in all elements
    target.cachedData = {};

    // Save element properties
    if (oldTarget.type === 'element') {
      this.saveElement(
        oldTarget,
        form.find('[name].element-property'),
      );

      savingElement = true;
    } else {
      savingElementGroup = true;
    }
  }

  // Run form submit module optional function
  if (target.type === 'widget') {
    formHelpers.widgetFormEditBeforeSubmit(this.DOMObject, target.subType);

    const errors = formHelpers.validateFormBeforeSubmit(this.DOMObject);

    if (errors !== null && showErrorMessages) {
      const errorMessage = Object.values(errors).join('</br>');
      // Display message in form
      formHelpers.displayErrorMessage(form, errorMessage, 'danger');
      return false;
    } else {
      formHelpers.clearErrorMessage(form);
    }
  }

  let requestPath;
  if (form.attr('action') != undefined && form.attr('method') != undefined) {
    // Get custom path
    requestPath = {
      url: form.attr('action'),
      type: form.attr('method'),
    };
  }

  // Get form data to save based on the target type
  let formFieldsToSave = (savingElement) ?
    form.find('[name]:not(.element-property)') :
    form.find('[name]');

  // Filter out position related fields
  formFieldsToSave =
    formFieldsToSave.filter('.tab-pane:not(#positionTab) [name]');

  // If form is valid, submit it ( add change )
  if (
    formFieldsToSave.length > 0 &&
    formFieldsToSave.valid()
  ) {
    // Get form data
    // if we're saving an element, don't include the element properties
    const formNewData = formFieldsToSave.serialize();

    app.common.showLoadingScreen();

    // Save content tab
    this.openTabOnRender =
      'a[href="' +
      app.propertiesPanel.DOMObject.find('.nav-tabs .nav-link.active')
        .attr('href') +
      '"]';

    // Add a save form change to the history array
    // with previous form state and the new state
    app.historyManager.addChange(
      'saveForm',
      target.type, // targetType
      target[target.type + 'Id'], // targetId
      this.formSerializedLoadData, // oldValues
      formNewData, // newValues
      {
        customRequestPath: requestPath,
      },
    ).then((_res) => {
      // Success
      app.common.hideLoadingScreen();

      // Clear error message
      formHelpers.clearErrorMessage(form);

      const reloadData = function() {
        const mainObject =
          app.getElementByTypeAndId(app.mainObjectType, app.mainObjectId);

        // If we're saving a widget, reload region on the viewer
        if (
          target.type === 'widget' &&
          app.viewer
        ) {
          // Reload data, but only render the region that the widget is in
          app.reloadData(
            mainObject,
            {
              reloadPropertiesPanel: false,
            }).done(() => {
            if (!target.drawerWidget) {
              app.viewer.renderRegion(
                app.getElementByTypeAndId('region', target.regionId),
              );
            } else {
              app.viewer.renderRegion(
                app.layout.drawer,
                target,
              );
            }
          });
        } else if (app.mainObjectType == 'playlist') {
          // Reload data, but don't refresh
          // toolbar or properties panel
          app.reloadData(
            {
              reloadToolbar: false,
              reloadPropertiesPanel: false,
            },
          );
        } else {
          // Reload data, and refresh viewer if layout
          // or if we're saving an element
          // but don't reload properties panel
          app.reloadData(
            mainObject,
            {
              refreshEditor: (target.type === 'layout'),
              reloadPropertiesPanel: false,
            },
          );
        }
      };

      // Save elements or element groups
      if (
        savingElement ||
        savingElementGroup
      ) {
        // If we're saving an element, reload all elements
        // from the widget that the element is in
        for (element in target.elements) {
          if (Object.prototype.hasOwnProperty.call(target.elements, element)) {
            app.viewer.renderElementContent(target.elements[element]);
          }
        }
      } else if (reloadAfterSave) {
        // Reload data if we're not saving an element
        reloadData();
      }

      // Call callback if exists
      (callback) && callback();
    }).catch((error) => { // Fail/error
      if (!showErrorMessages) {
        return;
      }

      app.common.hideLoadingScreen();

      // Show error returned or custom message to the user
      let errorMessage = '';

      if (typeof error == 'string') {
        errorMessage += error;
      } else {
        errorMessage += error.errorThrown;
      }
      // Remove added change from the history manager
      app.historyManager.removeLastChange();

      // Display message in form
      formHelpers.displayErrorMessage(form, errorMessage, 'danger');

      // If Save fails and we have an inline editor opened, reshow it
      if (app.propertiesPanel.inlineEditor) {
        app.viewer.showInlineEditor();
      }

      // Reset active tab
      self.openTabOnRender = '';
    });

    // Call callback without waiting for the request
    (callbackNoWait) && callbackNoWait();

    // Mark form as not needed to be saved anymore
    this.toSave = false;
  }
};

/**
 * Save element properties
 * @param {*} target - the element that the form relates to
 * @param {*} properties - the properties to save
 * @param {boolean} positionChanged - if the position of the element has changed
 */
PropertiesPanel.prototype.saveElement = function(
  target,
  properties,
  positionChanged = false,
) {
  const app = this.parent;

  // Get parent widget
  const parentWidget = app.getElementByTypeAndId(
    'widget',
    'widget_' + target.regionId + '_' + target.widgetId,
    'canvas',
  );

  // Form properties to the target element if they exist
  if (typeof properties != 'undefined') {
    const elementProperties =
      properties.map((_i, property) => {
        const propertyObject = {
          id: $(property).attr('name'),
          value: $(property).val(),
        };

        // If property is a checkbox
        if ($(property).attr('type') === 'checkbox') {
          propertyObject.value = $(property).is(':checked');
        }

        return propertyObject;
      }).get();

    // Add to the element properties
    if (parentWidget.elements[target.elementId]) {
      parentWidget.elements[target.elementId].properties = elementProperties;
    }
  }

  // Save elements to the widget
  parentWidget.saveElements().then((_res) => {
    // Update element position
    if (positionChanged) {
      app.viewer.updateElement(target);
    } else {
      // Render element content
      app.viewer.renderElementContent(target);
    }
  });
};

/**
 * Go to the previous form step
 * @param {object} element - the element that the form relates to
 */
PropertiesPanel.prototype.back = function(element) {
  // Get current step
  const currentStep = this.DOMObject.find('form').data('formStep');

  // Render previous form
  this.render(element, currentStep - 1);
};

/**
 * Delete selected element
 * @param {object} element - the element that the form relates to
 */
PropertiesPanel.prototype.delete = function(element) {
  lD.deleteSelectedObject();
};

/**
 * Render panel
 * @param {Object} target - the element object to be rendered
 * @param {number} step - the step to render
 * @param {boolean} actionEditMode - render while editing an action
 * @return {boolean} - result status
 */
PropertiesPanel.prototype.render = function(
  target,
  step,
  actionEditMode = false,
) {
  const self = this;
  const app = this.parent;
  const minSlotValue = 1;
  let targetAux;
  let renderElements = false;
  let isElementGroup = false;

  // Hide panel if no target element is passed
  if (target == undefined || $.isEmptyObject(target)) {
    this.DOMObject.parent().addClass('closed');
    this.DOMObject.parents('.editor-modal')
      .toggleClass('properties-panel-opened', false);
    return;
  } else {
    this.DOMObject.parent().removeClass('closed');
    this.DOMObject.parents('.editor-modal')
      .toggleClass('properties-panel-opened', true);
  }

  // If target is an element, change it to the widget
  // and save the element in a new variable
  if (target.type === 'element') {
    const elementId = target.elementId;

    // Get widget and change target
    target = app.getElementByTypeAndId(
      'widget',
      'widget_' + target.regionId + '_' + target.widgetId,
      'canvas',
    );

    // Get element from the widget
    targetAux = target.elements[elementId];

    // Set renderElements to true
    renderElements = true;
  } else if (target.type === 'element-group') {
    // Save element group in targetAux
    targetAux = target;

    // Get widget and set it as target
    target = app.getElementByTypeAndId(
      'widget',
      'widget_' + target.regionId + '_' + target.widgetId,
      'canvas',
    );

    isElementGroup = true;
  }

  // Show a message if the module is disabled for a widget rendering
  if (
    target.type === 'widget' &&
    !target.enabled
  ) {
    // Show invalid module message
    this.DOMObject.html(messageTemplate({
      message: editorsTrans.invalidModule,
    }));

    return false;
  }

  // Reset inline editor to false on each refresh
  this.inlineEditor = false;

  // Show loading template
  this.DOMObject.html(loadingTemplate());

  // Build request path
  let requestPath = urlsForApi[target.type].getForm.url;
  requestPath = requestPath.replace(':id', target[target.type + 'Id']);

  // If we have a step to render, append it to the request path
  if (step !== undefined && typeof step == 'number') {
    requestPath += '?step=' + step;
  }

  // If there was still a render request, abort it
  if (this.renderRequest != undefined) {
    this.renderRequest.abort('requestAborted');
  }

  // Create a new request
  this.renderRequest = $.get(requestPath).done(function(res) {
    const app = self.parent;

    // Clear request var after response
    self.renderRequest = undefined;

    // Show uncussess request message
    if (res.success === false) {
      self.DOMObject.html('<div class="unsuccessMessage">' +
        (res.message) ?
        res.message :
        propertiesPanelTrans.somethingWentWrong +
        '</div>');
      return false;
    }

    // Render template
    const htmlTemplate = formTemplates[target.type];

    // Extend element with translation
    $.extend(res.data, {
      trans: propertiesPanelTrans,
    });

    // Create buttons object for the external playlist editor
    let buttons = {};
    if (
      self.parent.mainObjectType == 'playlist' &&
      self.parent.inline === false
    ) {
      // Render save button
      buttons = formHelpers.widgetFormRenderButtons(formTemplates.buttons);
    }

    // If we have a widget, add the widgetId to the data
    const dataToRender = (target.type != 'widget') ?
      res.data :
      Object.assign(res.data, {
        target: target.widgetId,
      });

    // If the form is a layout
    // Add imageDownloadUrl and libraryAddUrl to the data
    if (target.type === 'layout') {
      dataToRender.imageDownloadUrl = imageDownloadUrl;
      dataToRender.libraryAddUrl = libraryAddUrl;

      // Add new property:orientation
      dataToRender.orientation = lD.viewer.getLayoutOrientation(
        dataToRender.resolution.width, dataToRender.resolution.height);
      dataToRender.bgImageName = dataToRender.backgrounds[0]?.name || '';
    }

    const propertiesPanelOptions = {
      header: res.dialogTitle,
      style: target.type,
      form: htmlTemplate(dataToRender),
      trans: propertiesPanelTrans,
    };

    if (!$.isEmptyObject(buttons)) {
      propertiesPanelOptions.buttons = buttons;
    }

    const html = propertiesPanelTemplate(propertiesPanelOptions);

    // Append layout html to the main div
    self.DOMObject.html(html);

    // Mark container as action edit mode
    self.DOMObject.toggleClass('action-edit-mode', actionEditMode);

    // Store the extra data
    self.DOMObject.data('extra', res.extra);

    // Check if there's a viewer element
    const viewerExists = (typeof app.viewer != 'undefined');
    self.DOMObject.data('formEditorOnly', !viewerExists);

    // If the viewer exists, save its data  to the DOMObject
    if (viewerExists) {
      self.DOMObject.data('viewerObject', app.viewer);
    }

    // Create the dynamic form fields
    // ( for now just for widget )
    if (target.type === 'widget') {
      // Create configure tab if we have properties
      if (res.data.module.properties.length > 0) {
        const widgetProperties = res.data.module.properties;

        // if it's an element group and we have a slot
        // add property to the top of configure tab
        if (
          isElementGroup &&
          targetAux.slot != undefined
        ) {
          widgetProperties.unshift({
            id: 'slot',
            title: propertiesPanelTrans.dataSlot,
            helpText: propertiesPanelTrans.dataSlotHelpText,
            value: Number(targetAux.slot) + 1,
            min: minSlotValue,
            type: 'number',
            visibility: [],
          });
        }

        // Configure tab
        forms.createFields(
          widgetProperties,
          self.DOMObject.find('#configureTab'),
          target.widgetId,
          target.playlistId,
          res.data.module.propertyGroups,
        );

        // if we created a new slot for element group input
        // handle when changed
        if (
          isElementGroup &&
          targetAux.slot != undefined
        ) {
          self.DOMObject.find('[name="slot"]')
            .on('change', function(ev) {
              let slotValue = $(ev.currentTarget).val();

              // If value is lower than minSlotValue
              // set it to minSlotValue
              if (Number(slotValue) < minSlotValue) {
                slotValue = minSlotValue;
                $(ev.currentTarget).val(minSlotValue);
              }

              // update slot for the group
              targetAux.updateSlot(Number(slotValue) - 1, true);

              // save elements
              target.saveElements();

              // Render canvas again
              app.viewer.renderCanvas(app.layout.canvas);
            });
        }
      } else {
        // Remove configure tab
        self.DOMObject.find('[href="#configureTab"]').parent().remove();

        // Select advanced tab
        self.DOMObject.find('[href="#advancedTab"]').tab('show');
      }

      // Appearance tab
      const showAppearanceTab = (selectTab = false) => {
        // Show appearance tab
        self.DOMObject.find('.nav-link[href="#appearanceTab"]')
          .parent().removeClass('d-none');

        if (selectTab) {
          // Deselect active tab
          self.DOMObject.find('.nav-link.active').removeClass('active');

          // Select appearance tab
          self.DOMObject.find('[href="#appearanceTab"]').tab('show');
        }
      };

      // If we have a template for the widget, create the fields
      if (
        res.data.template != undefined &&
        res.data.template != 'elements' &&
        res.data.template.properties.length > 0
      ) {
        forms.createFields(
          res.data.template.properties,
          self.DOMObject.find('#appearanceTab'),
          target.widgetId,
          target.playlistId,
          res.data.template.propertyGroups,
        );

        // Show the appearance tab
        showAppearanceTab();
      }

      // If we need to render the element properties
      if (
        renderElements
      ) {
        // Get element properties
        targetAux.getProperties().then((properties) => {
          // Create a clone of properties
          // so we don't modify the original object
          properties = JSON.parse(JSON.stringify(properties));

          // Create common fields
          const commonFields = [
            {
              id: 'layer',
              title: propertiesPanelTrans.layer,
              value: targetAux.layer,
              type: 'number',
              visibility: [],
            },
          ];

          // TODO: for now we disable scaling type
          // Show scaling type if element is in a group
          if (
            false &&
            targetAux.groupId != '' &&
            targetAux.groupId != undefined
          ) {
            commonFields.unshift(
              {
                id: 'groupScale',
                title: propertiesPanelTrans.groupScale,
                helpText: propertiesPanelTrans.groupScaleHelpText,
                value: targetAux.groupScale,
                type: 'checkbox',
                visibility: [],
              },
              {
                id: 'groupScaleType',
                title: propertiesPanelTrans.groupScaleType,
                helpText: propertiesPanelTrans.groupScaleTypeHelpText,
                value: targetAux.groupScaleType,
                options: [
                  {
                    title: propertiesPanelTrans.groupScaleTypeOptions.topLeft,
                    name: 'top_left',
                  },
                  {
                    title: propertiesPanelTrans.groupScaleTypeOptions.topRight,
                    name: 'top_right',
                  },
                  {
                    title: propertiesPanelTrans
                      .groupScaleTypeOptions.bottomLeft,
                    name: 'bottom_left',
                  },
                  {
                    title: propertiesPanelTrans
                      .groupScaleTypeOptions.bottomRight,
                    name: 'bottom_right',
                  },
                ],
                type: 'dropdown',
                visibility: [
                  {
                    conditions: [
                      {
                        field: 'groupScale',
                        type: 'eq',
                        value: '0',
                      },
                    ],
                  },
                ],
              },
            );
          }

          // Show slot if we the element isn't global
          // and in a group
          if (
            targetAux.elementType != 'global' &&
            (
              targetAux.groupId == '' ||
              targetAux.groupId == undefined
            )
          ) {
            commonFields.unshift(
              {
                id: 'slot',
                title: propertiesPanelTrans.dataSlot,
                helpText: propertiesPanelTrans.dataSlotHelpText,
                value: Number(targetAux.slot) + 1,
                min: minSlotValue,
                type: 'number',
                visibility: [],
              },
            );
          }

          forms.createFields(
            commonFields,
            self.DOMObject.find('#appearanceTab'),
            targetAux.elementId,
            null,
            null,
            'element-property element-common-property',
          );

          // Create element fields
          forms.createFields(
            properties,
            self.DOMObject.find('#appearanceTab'),
            targetAux.elementId,
            null,
            null,
            'element-property',
          );

          // Show the appearance tab
          showAppearanceTab(true);

          // Also Init fields for the element
          self.initFields(targetAux, res.data, actionEditMode, true);

          // Save element
          const saveElement = function(target) {
            const $target = $(target);
            let containerChanged = false;
            // If the property is common, save it to the element
            if ($target.hasClass('element-common-property')) {
              // Get the property name
              const propertyName = $target.attr('name');

              // Get the value
              let value = $target.val();

              // If property is layer, convert to int
              // and don't allow negative values
              if (propertyName === 'layer') {
                value = parseInt(value);
                if (value < 0) {
                  value = 0;
                }
              }

              // If property is slot, set a value
              // with -1 to match with the array
              if (propertyName === 'slot') {
                // If value is lower than minSlotValue
                // set it to minSlotValue
                if (Number(value) < minSlotValue) {
                  value = minSlotValue;
                  $(target).val(minSlotValue);
                }

                value = Number(value) - 1;
              }

              // Save group scale to element
              if (propertyName === 'groupScale') {
                value = $target.is(':checked');
              }

              // Set the property
              targetAux[propertyName] = value;

              // Set the container changed flag
              containerChanged = true;
            }

            // Save the element
            self.saveElement(
              targetAux,
              self.DOMObject.find(
                '[name].element-property:not(.element-common-property)',
              ),
              containerChanged,
            );
          };

          const saveDebounced = _.wrap(
            _.memoize(
              () => _.debounce(saveElement.bind(self), 250), _.property('id'),
            ),
            (getMemoizedFunc, obj) => getMemoizedFunc(obj)(obj),
          );

          // When we change the element fields, save them
          self.DOMObject.find(
            '[name].element-property',
          ).on({
            change: function(_ev) {
              // Debounce save based on the object being saved
              saveDebounced(
                _ev.currentTarget,
              );
            },
            focus: function(_ev) {
              self.toSave = true;
            },
          });
        });
      }
    }

    // If target is a widget or element
    // and we are in the Layout Editor
    // render position tab with region or element position
    if (
      app.mainObjectType === 'layout' &&
      (
        target.type === 'widget' ||
        target.subType === 'playlist' ||
        isElementGroup
      )
    ) {
      // Get position
      let positionProperties = {};
      if (isElementGroup) {
        positionProperties = {
          type: 'element-group',
          top: targetAux.top,
          left: targetAux.left,
          width: targetAux.width,
          height: targetAux.height,
        };
      } else if (targetAux?.type === 'element') {
        positionProperties = {
          type: 'element',
          top: targetAux.top,
          left: targetAux.left,
          width: targetAux.width,
          height: targetAux.height,
          zIndex: targetAux.layer,
        };

        if (targetAux.canRotate) {
          positionProperties.rotation = targetAux.rotation;
        }
      } else if (target.subType === 'playlist') {
        positionProperties = {
          type: 'region',
          regionType: target.subType,
          regionName: target.name,
          top: target.dimensions.top,
          left: target.dimensions.left,
          width: target.dimensions.width,
          height: target.dimensions.height,
          zIndex: target.zIndex,
        };
      } else {
        positionProperties = {
          type: 'region',
          regionType: target.parent.subType,
          regionName: target.parent.name,
          top: target.parent.dimensions.top,
          left: target.parent.dimensions.left,
          width: target.parent.dimensions.width,
          height: target.parent.dimensions.height,
          zIndex: target.parent.zIndex,
        };
      }

      // Get position template
      const positionTemplate = formTemplates.position;

      // Add position tab after advanced tab
      self.DOMObject.find('[href="#advancedTab"]').parent()
        .after('<li class="nav-item">' +
          '<a class="nav-link" href="#positionTab" data-toggle="tab">' +
          '<i class="fas fa-border-none"></i></a></li>');

      // Add position tab content after advanced tab content
      // If element is in a group, adjust position to the group's
      if (
        targetAux?.type == 'element' &&
        targetAux?.group != undefined
      ) {
        positionProperties.left -= targetAux.group.left;
        positionProperties.top -= targetAux.group.top;
      }

      self.DOMObject.find('#advancedTab').after(
        positionTemplate(positionProperties),
      );

      // Hide make fullscreen button for element groups
      if (isElementGroup) {
        self.DOMObject.find('#positionTab #setFullScreen').hide();
      }

      // If we change any input, update the target position
      self.DOMObject.find('#positionTab [name]').on('change', function(ev) {
        const viewerScale = lD.viewer.containerElementDimensions.scale;
        const form = $(ev.currentTarget).parents('#positionTab');

        if (targetAux == undefined) {
          // Widget
          const regionId = target.parent.id;

          lD.layout.regions[regionId].transform({
            width: form.find('[name="width"]').val(),
            height: form.find('[name="height"]').val(),
            top: form.find('[name="top"]').val(),
            left: form.find('[name="left"]').val(),
          }, true);

          lD.viewer.updateRegion(lD.layout.regions[regionId]);

          // Update moveable
          lD.viewer.updateMoveable();
        } else if (targetAux?.type == 'element') {
          // Element
          const $targetElement = $('#' + targetAux.elementId);

          // Move element
          $targetElement.css({
            width: form.find('[name="width"]').val() * viewerScale,
            height: form.find('[name="height"]').val() * viewerScale,
            top: form.find('[name="top"]').val() * viewerScale,
            left: form.find('[name="left"]').val() * viewerScale,
          });

          // Rotate element
          if (form.find('[name="rotation"]').val() != undefined) {
            $targetElement.css('transform', 'rotate(' +
              form.find('[name="rotation"]').val() +
              'deg)');
            lD.viewer.moveable.updateRect();
          }

          // Recalculate group dimensions
          if (targetAux.groupId) {
            lD.viewer.saveElementGroupProperties(
              lD.viewer.DOMObject.find('#' + targetAux.groupId),
              true,
              false,
            );
          } else {
            // Save properties
            lD.viewer.saveElementProperties($targetElement, true);
          }

          // Update element
          lD.viewer.updateElement(targetAux, true);

          // Update moveable
          lD.viewer.updateMoveable();
        } else if (targetAux?.type == 'element-group') {
          // Element group
          const $targetElementGroup = $('#' + targetAux.id);

          // Move element group
          $targetElementGroup.css({
            width: form.find('[name="width"]').val() * viewerScale,
            height: form.find('[name="height"]').val() * viewerScale,
            top: form.find('[name="top"]').val() * viewerScale,
            left: form.find('[name="left"]').val() * viewerScale,
          });

          // Scale group
          // Update element dimension properties
          targetAux.transform({
            width: parseFloat(
              form.find('[name="width"]').val(),
            ),
            height: parseFloat(
              form.find('[name="height"]').val(),
            ),
          }, false);
          lD.viewer.updateElementGroup(targetAux);

          // Save properties
          lD.viewer.saveElementGroupProperties($targetElementGroup, true, true);

          // Update moveable
          lD.viewer.updateMoveable();
        }
      });

      // Handle set fullscreen button
      self.DOMObject.find('#positionTab #setFullScreen').off().on(
        'click',
        function(ev) {
          const form = $(ev.currentTarget).parents('#positionTab');
          const viewerScale = lD.viewer.containerElementDimensions.scale;

          if (targetAux == undefined) {
            // Widget
            const regionId = target.parent.id;

            lD.layout.regions[regionId].transform({
              width: lD.layout.width,
              height: lD.layout.height,
              top: 0,
              left: 0,
            }, true);

            lD.viewer.updateRegion(lD.layout.regions[regionId]);
          } else if (targetAux?.type == 'element') {
            // Element
            const $targetElement = $('#' + targetAux.elementId);

            // Move element
            $targetElement.css({
              width: lD.layout.width * viewerScale,
              height: lD.layout.height * viewerScale,
              top: 0,
              left: 0,
            });

            // Save properties
            lD.viewer.saveElementProperties($targetElement, true);

            // Update element
            lD.viewer.updateElement(targetAux, true);
          }

          // Change position tab values
          form.find('[name="width"]').val(lD.layout.width);
          form.find('[name="height"]').val(lD.layout.height);
          form.find('[name="top"]').val(0);
          form.find('[name="left"]').val(0);

          // Update moveable
          lD.viewer.updateMoveable();
        });
    }

    // Init fields
    self.initFields(target, res.data, actionEditMode);
  }).fail(function(data) {
    // Clear request var after response
    self.renderRequest = undefined;

    if (data.statusText != 'requestAborted') {
      toastr.error(errorMessagesTrans.getFormFailed, errorMessagesTrans.error);
    }
  });
};

/**
 * Initialise the form fields
 * @param {*} target The target object
 * @param {*} data The data to be used
 * @param {boolean} actionEditMode - render while editing an action
 * @param {boolean} elementProperties - render element properties
 */
PropertiesPanel.prototype.initFields = function(
  target,
  data,
  actionEditMode = false,
  elementProperties = false,
) {
  const self = this;
  const app = this.parent;
  const targetIsElement = (target.type === 'element');
  const readOnlyModeOn =
    (typeof(lD) != 'undefined' && lD?.readOnlyMode === true) ||
    (app?.readOnlyMode === true);

  // Set condition and handle replacements
  forms.handleFormReplacements(self.DOMObject.find('form'), data);
  forms.setConditions(
    self.DOMObject.find('form'),
    data,
    (elementProperties) ? target.elementId : target.widgetId,
    (target.parent && target.parent.isTopLevel != undefined) ?
      target.parent.isTopLevel : true,
  );

  // Run form open module optional function
  if (target.type === 'widget') {
    // Pass widget options to the form as data
    if (target.getOptions != undefined) {
      self.DOMObject.find('form').data(
        'elementOptions',
        target.getOptions(),
      );
    }

    formHelpers.widgetFormEditAfterOpen(self.DOMObject, target.subType);
  } else if (
    target.type === 'region' &&
    typeof window.regionFormEditOpen === 'function'
  ) {
    window.regionFormEditOpen.bind(self.DOMObject)();
  }

  // Check for spacing issues on text fields
  forms.checkForSpacingIssues(self.DOMObject);

  // Save form data if not a element
  // and avoid saving element specific inputs
  if (!targetIsElement) {
    self.formSerializedLoadData =
      self.DOMObject.find('form [name]:not(.element-property)').serialize();
  }

  // If we're not in read only mode
  if (!readOnlyModeOn) {
    // Handle buttons
    self.DOMObject.find('.properties-panel-btn:not(.inline-btn)')
      .off().click(function(e) {
        if ($(e.target).data('action')) {
          self[$(e.target).data('action')](
            target,
            $(e.target).data('subAction'),
          );
        }
      });

    // Handle back button based on form page
    if (
      self.DOMObject.find('form').data('formStep') != undefined &&
      self.DOMObject.find('form').data('formStep') > 1
    ) {
      self.DOMObject.find('button#back').removeClass('d-none');
    } else {
      self.DOMObject.find('button#back').addClass('d-none');
    }

    // Handle delete button
    if (actionEditMode) {
      self.DOMObject.find('button#delete').removeClass('d-none');
    } else {
      self.DOMObject.find('button#delete').addClass('d-none');
    }

    // Render action tab
    if (
      app.mainObjectType === 'layout' &&
      !targetIsElement
    ) {
      self.renderActionTab(target, {
        reattach: actionEditMode,
      });
    }
  }

  // Xibo Init options
  let xiboInitOptions = null;
  if (target.type == 'widget') {
    xiboInitOptions = {
      targetId: target.widgetId,
    };
  } else if (elementProperties) {
    xiboInitOptions = {
      targetId: target.elementId,
      elementProperties: true,
    };
  }

  // Read only mode option
  if (readOnlyModeOn) {
    (!xiboInitOptions) && (xiboInitOptions = {});

    xiboInitOptions.readOnlyMode = true;
  }

  // Call Xibo Init for this form
  XiboInitialise(
    '#' + self.DOMObject.attr('id'),
    xiboInitOptions,
  );

  // For the layout properties, call background Setup
  // TODO Move method to a common JS file
  if (target.type == 'layout') {
    backGroundFormSetup(self.DOMObject);
  }

  // Make form read only
  if (readOnlyModeOn) {
    forms.makeFormReadOnly(self.DOMObject);
  }

  // if a tab was previously selected, select it again
  if (self.openTabOnRender != '') {
    // Open tab
    self.DOMObject.find(self.openTabOnRender).tab('show');

    // Reset flag
    self.openTabOnRender = '';
  }

  // Initialise tooltips
  app.common.reloadTooltips(
    self.DOMObject,
    {
      position: 'left',
    },
  );

  // Handle Auto Save
  // (only when working in the layout editor)
  if (
    !readOnlyModeOn &&
    (
      self.parent.mainObjectType == 'layout' ||
      (
        self.parent.mainObjectType == 'playlist' &&
        self.parent.inline === true
      )
    )
  ) {
    const saveDebounced = _.wrap(
      _.memoize(
        () => _.debounce(self.save.bind(self), 500), _.property('id'),
      ),
      (getMemoizedFunc, obj) => getMemoizedFunc(obj)(obj),
    );

    // Auto save when changing inputs
    $(self.DOMObject).find('form').off()
      .on({
        'change inputChange': function(_ev, options) {
          // Debounce save based on the object being saved
          if (!options?.skipSave) {
            saveDebounced(
              self.parent.selectedObject,
            );
          }
        },
        focus: function(_ev) {
          self.toSave = true;
        },
      },
      '.xibo-form-input:not(.position-input)' +
        ':not(.action-form-input):not(.snippet-selector) ' +
        'select:not(.element-property), ' +
      '.xibo-form-input:not(.position-input):not(.action-form-input) ' +
        'input:not(.element-property), ' +
      '.xibo-form-input:not(.position-input):not(.action-form-input) ' +
        'textarea:not(.element-property), ' +
      '[name="backgroundImageId"] ',
      );
  }
};

/**
 * Save Region
 * @param {Boolean} savePositionForm - if we want to save only the position form
 * @return {boolean} false if unsuccessful
 */
PropertiesPanel.prototype.saveRegion = function(
  savePositionForm = false,
) {
  const app = this.parent;
  const self = this;
  const form = (savePositionForm) ?
    $(this.DOMObject).find('form #positionTab [name]') :
    $(self.DOMObject).find('form');

  // If form not loaded, prevent changes
  if (form.length == 0) {
    return false;
  }

  const element = (savePositionForm) ?
    app.selectedObject.parent :
    app.selectedObject;
  const formNewData = form.serialize();
  const requestPath =
    urlsForApi.region.saveForm.url.replace(':id', element[element.type + 'Id']);

  // If form is valid, and it changed, submit it ( add change )
  if (form.valid() && self.formSerializedLoadData != formNewData) {
    // Add a save form change to the history array
    // with previous form state and the new state
    app.historyManager.addChange(
      'saveForm',
      element.type, // targetType
      element[element.type + 'Id'], // targetId
      self.formSerializedLoadData, // oldValues
      formNewData, // newValues
      {
        customRequestPath: {
          url: requestPath,
          type: urlsForApi.region.saveForm.type,
        },
        upload: true, // options.upload
      },
    ).then((res) => { // Success
      // Clear error message
      formHelpers.clearErrorMessage(form);
    }).catch((error) => { // Fail/error
      // Show error returned or custom message to the user
      let errorMessage = '';

      if (typeof error == 'string') {
        errorMessage += error;
      } else {
        errorMessage += error.errorThrown;
      }
      // Remove added change from the history manager
      app.historyManager.removeLastChange();

      // Display message in form
      formHelpers.displayErrorMessage(form, errorMessage, 'danger');
    });
  }
};

/**
 * Create action tab
 * @param {object} element
 * @param {object/boolean=} [options.reattach = false] - reattach the tab
 * @param {object/boolean=} [options.clearPrevious = false]
 *  - clear previous tab content
 * @param {object/boolean=} [options.selectAfterRender = false]
 *   - select the tab when rendered
 * @param {object/string=} [options.openEditActionAfterRender = null]
 */
PropertiesPanel.prototype.renderActionTab = function(
  element,
  {
    reattach = false,
    clearPrevious = false,
    selectAfterRender = false,
    openEditActionAfterRender = null,
  } = {},
) {
  const self = this;
  const app = this.parent;

  // Init drawer
  lD.initDrawer();

  // Remove action tab and content
  if (clearPrevious) {
    self.DOMObject.find('.nav-tabs .actions-tab').remove();
    self.DOMObject.find('#actionsTab').remove();
  }

  // Create tab
  self.DOMObject.find('.nav-tabs').append(
    actionsFormTabTemplate(element),
  );

  // Create tab content
  if (!reattach) {
    this.actionForm =
      $(actionsFormContentTemplate({
        elementType: element.type,
        trans: propertiesPanelTrans.actions,
      }));
  }

  // Attach to DOM
  this.actionForm.appendTo(this.DOMObject.find('.tab-content'));

  if (!reattach) {
    // Remove edit area from the viewer
    lD.viewer.removeActionEditArea();

    // Get actions and populate form containers
    $.ajax({
      url: urlsForApi.actions.get.url,
      type: urlsForApi.actions.get.type,
      dataType: 'json',
      data: {
        layoutId: app.mainObjectId,
      },
    }).done(function(res) {
      // Filter actions by groups
      const $elementActionsContainer =
        self.DOMObject.find('.element-actions');
      const $otherActionsContainer =
        self.DOMObject.find('.other-actions');

      const showEmptyMessage = ($container) => {
        $container.append(
          $('<div />').addClass('text-center no-actions').text(
            propertiesPanelTrans.actions.noActionsToShow,
          ),
        );
      };

      if (res.data && res.data.length > 0) {
        res.data.forEach((action) => {
          self.addActionToContainer(
            action,
            element,
            $elementActionsContainer,
            $otherActionsContainer,
          );
        });
      }

      // If container is empty, show message
      ($elementActionsContainer.find('.action-element').length == 0) &&
        showEmptyMessage($elementActionsContainer);
      ($otherActionsContainer.find('.action-element').length == 0) &&
        showEmptyMessage($otherActionsContainer);

      // Select tab after render
      if (selectAfterRender) {
        self.DOMObject.find('.nav-link, .tab-pane').removeClass('active');
        self.DOMObject.find('.actions-tab .nav-link, #actionsTab')
          .addClass('active');


        // Open edit action form
        if (openEditActionAfterRender != null) {
          self.openEditAction(
            self.DOMObject.find(
              '.action-element[data-action-id="' +
              openEditActionAfterRender +
              '"]',
            ),
          );
        }
      }
    }).fail(function(_data) {
      toastr.error(
        errorMessagesTrans.getFormFailed,
        errorMessagesTrans.error,
      );
    });
  }
};

/**
 * Add or update action
 * @param {object} action
 * @param {object} selectedObject
 * @param {object} $containerSelected
 * @param {object} $containerOther
 * @param {object} $elementToBeReplaced
 */
PropertiesPanel.prototype.addActionToContainer = function(
  action,
  selectedObject = lD.selectedObject,
  $containerSelected = null,
  $containerOther = null,
  $elementToBeReplaced = null,
) {
  const self = this;
  const app = this.parent;
  const selectedType = selectedObject.type;
  const selectedId = selectedObject[selectedType + 'Id'];

  // Check if current element is trigger or target for this action
  action.isTrigger =
    action.source == selectedType &&
    action.sourceId == selectedId;

  // For layout, compare with "screen"
  const elementType =
    (selectedType == 'layout') ? 'screen' : selectedType;
  action.isTarget =
    action.target == elementType &&
    action.targetId == selectedId;

  // If action target is layout/screen, add the id to the targetId
  if (action.target == 'screen') {
    action.targetId = app.mainObjectId;
  }

  // Create action title
  action.actionTitle = action.actionType;

  // Group actions into element actions and other actions
  let $targetContainer;
  if (action.isTrigger || action.isTarget) {
    $targetContainer = $containerSelected;
  } else {
    $targetContainer = $containerOther;
  }

  // Create action and add to container
  const newAction = actionFormElementTemplate($.extend({}, action, {
    trans: propertiesPanelTrans.actions,
  }));

  // Save data and add to container
  const $newAction = $(newAction).data(action).on('mouseenter',
    function() {
      // Highlight action on viewer
      // if there's no action being edited
      if (
        self.DOMObject.find('.action-element-form').length == 0
      ) {
        app.viewer.createActionHighlights(action, 0);
      }
    },
  ).on('mouseleave',
    function() {
      // Remove highlight
      // if there's no action being edited
      if (
        self.DOMObject.find('.action-element-form').length == 0
      ) {
        app.viewer.clearActionHighlights();
      }
    },
  );

  // Handle buttons
  $newAction.find('.action-btn').click(function(e) {
    const btnAction = $(e.currentTarget).data('action');

    if (btnAction == 'delete') {
      app.deleteAction(
        $(e.currentTarget).parents('.action-element'),
      );
    }

    if (btnAction == 'edit') {
      self.openEditAction($(e.currentTarget).parents('.action-element'));
    }
  });

  // Replace or add element
  if ($elementToBeReplaced) {
    $elementToBeReplaced.replaceWith($newAction);
  } else {
    $targetContainer.append($newAction);
  }
};

/**
 * Open edit action
 * @param {object} action
 * @return {boolean} false if unsuccessful
 */
PropertiesPanel.prototype.openEditAction = function(action) {
  const app = this.parent;
  const self = this;

  // Remove any opened forms
  this.DOMObject.find('.action-element-form').remove();

  // Show all hidden actions
  this.DOMObject.find('.action-element').removeClass('hidden');

  // Get data from action
  const actionData = action.data();

  // Send the layout code search URL with the action
  actionData.layoutCodeSearchURL = urlsForApi.layout.codeSearch.url;

  // Create action and add to container
  const newAction = actionFormElementEditTemplate($.extend({}, actionData, {
    trans: propertiesPanelTrans.actions,
  }));

  // Hide original action
  action.addClass('hidden');

  // Handle actions on the viewer
  app.viewer.createActionHighlights(actionData, 1);

  // Add edit form to container after original action
  const $newActionContainer = $(newAction).insertAfter(action);

  // Populate dropdowns with layout elements
  app.populateDropdownWithLayoutElements(
    $newActionContainer.find('[name="sourceId"]'),
    {
      $typeInput: $newActionContainer.find('[name="source"]'),
      value: actionData.sourceId,
    },
    actionData,
  );

  app.populateDropdownWithLayoutElements(
    $newActionContainer.find('[name="targetId"]'),
    {
      $typeInput: $newActionContainer.find('[name="target"]'),
      value: actionData.targetId,
      filters: ['layout', 'regions'],
    },
    actionData,
  );

  if (actionData.actionType == 'navWidget') {
    // Populate dropdowns with drawer elements
    app.populateDropdownWithLayoutElements(
      $newActionContainer.find('[name="widgetId"]'),
      {
        value: actionData.widgetId,
        filters: ['drawerWidgets'],
      },
      actionData,
    );
  }

  // If trigger type is webhook
  // set source as "screen"
  const updateSource = function(triggerType) {
    if (triggerType == 'webhook') {
      $newActionContainer.find('[name="source"]').val('layout');
      $newActionContainer.find('[name="sourceId"]').val(app.mainObjectId);
    }
  };

  updateSource(actionData.triggerType);

  // Handle trigger type change
  $newActionContainer.find('[name="triggerType"]').on('change', function(e) {
    const triggerType = $(e.currentTarget).val();

    updateSource(triggerType);
  });

  // Handle buttons
  $newActionContainer.find('[type="button"]').on('click', function(e) {
    const btnAction = $(e.currentTarget).data('action');

    if (btnAction == 'save') {
      app.saveAction(action, $newActionContainer);
    }

    if (btnAction == 'cancel') {
      // Destroy new action
      $newActionContainer.remove();

      // Remove edit area
      lD.viewer.removeActionEditArea();

      // Remove highlight
      app.viewer.clearActionHighlights();

      // Show original action
      $(action).removeClass('hidden');

      self.closeEditAction(
        $newActionContainer,
        $(action),
      );
    }
  });

  // Set actionData to container
  $newActionContainer.data(actionData);

  // Form conditions
  forms.setConditions($newActionContainer);

  // Initialise tooltips
  app.common.reloadTooltips(
    $newActionContainer,
    {
      position: 'left',
    },
  );

  // Run XiboInitialise on form
  XiboInitialise('.action-element-form');

  return true;
};

/**
 * Close edit action
 * @param {object} $actionEditContainer
 * @param {object} $originalAction
 */
PropertiesPanel.prototype.closeEditAction = function(
  $actionEditContainer,
  $originalAction,
) {
  // Destroy new action
  $actionEditContainer.remove();

  // Remove edit area
  lD.viewer.removeActionEditArea();

  // Remove highlight
  lD.viewer.clearActionHighlights();

  // Show original action
  $originalAction.removeClass('hidden');

  // Call close drawer widget
  // if selected element is a drawer widget
  lD.closeDrawerWidget();
};

/**
 * Detach action form
 */
PropertiesPanel.prototype.detachActionsForm = function() {
  // Remove active class from tab
  this.actionForm.removeClass('active');

  // Detach form
  this.actionForm.detach();
};

/**
 * Attach action form
 */
PropertiesPanel.prototype.attachActionsForm = function() {
  // Re-attach form to the tab content
  this.DOMObject.find('.tab-content').append(this.actionForm);
};

/**
 * Update position form
 * @param {object} properties
 */
PropertiesPanel.prototype.updatePositionForm = function(properties) {
  const $positionTab = this.DOMObject.find('form #positionTab');

  // Loop properties
  $.each(properties, function(key, value) {
    // If value is a number, round it
    if (typeof value == 'number') {
      value = Math.round(value);
    }

    // Change value in the form field
    $positionTab.find('[name="' + key + '"]').val(value);
  });
};

module.exports = PropertiesPanel;
