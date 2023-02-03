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
};

/**
 * Save properties from the panel form
 * @param {object} element - the element that the form relates to
 */
PropertiesPanel.prototype.save = function(element) {
  const app = this.parent;
  const self = this;

  // If main container has inline editing class, remove it
  app.editorContainer.removeClass('inline-edit-mode');

  // If inline editor and viewer exist
  if (this.inlineEditor && (typeof app.viewer != 'undefined')) {
    app.viewer.hideInlineEditor();
  }

  // Run form submit module optional function
  if (element.type === 'widget') {
    formHelpers.widgetFormEditBeforeSubmit(this.DOMObject, element.subType);
  }

  const form = $(this.DOMObject).find('form');

  let requestPath;
  if (form.attr('action') != undefined && form.attr('method') != undefined) {
    // Get custom path
    requestPath = {
      url: form.attr('action'),
      type: form.attr('method'),
    };
  }

  // If form is valid, submit it ( add change )
  if (form.valid()) {
    const formNewData = form.serialize();

    app.common.showLoadingScreen();

    // Save content tab
    this.openTabOnRender =
      'a[href="' +
      app.propertiesPanel.DOMObject.find('.nav-tabs .nav-link.active')
        .attr('href') +
      '"]';

    // Add a save form change to the history array
    // with previous form state and the new state
    app.manager.addChange(
      'saveForm',
      element.type, // targetType
      element[element.type + 'Id'], // targetId
      this.formSerializedLoadData, // oldValues
      formNewData, // newValues
      {
        customRequestPath: requestPath,
      },
    ).then((res) => {
      // Success
      app.common.hideLoadingScreen();

      const resultMessage = res.message;

      const reloadData = function() {
        toastr.success(resultMessage);

        const mainObject =
          app.getElementByTypeAndId(app.mainObjectType, app.mainObjectId);

        // If we're saving a widget, reload region on the viewer
        if (element.type === 'widget' && app.viewer) {
          // Reload data, but only render the region that the widget is in
          app.reloadData(mainObject).done(() => {
            if (!element.drawerWidget) {
              app.viewer.renderRegion(
                app.getElementByTypeAndId('region', element.regionId),
              );
            } else {
              app.viewer.renderRegion(
                app.layout.drawer,
                element,
              );
            }
          });
        } else {
          // Reload data, and refresh viewer if layout
          app.reloadData(mainObject, (element.type === 'layout'));
        }
      };

      reloadData();
    }).catch((error) => { // Fail/error
      app.common.hideLoadingScreen();

      // Show error returned or custom message to the user
      let errorMessage = '';

      if (typeof error == 'string') {
        errorMessage += error;
      } else {
        errorMessage += error.errorThrown;
      }
      // Remove added change from the history manager
      app.manager.removeLastChange();

      // Display message in form
      formHelpers.displayErrorMessage(form, errorMessage, 'danger');

      // If Save fails and we have an inline editor opened, reshow it
      if (app.propertiesPanel.inlineEditor) {
        app.viewer.showInlineEditor();
      }

      // Reset active tab
      self.openTabOnRender = '';

      // Show toast message
      toastr.error(errorMessage);
    });
  }
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
 * Disable all the form inputs and make it read only
 */
PropertiesPanel.prototype.makeFormReadOnly = function() {
  // Disable inputs, select, textarea and buttons
  this.DOMObject
    .find('input, select, textarea, button:not(.copyTextAreaButton)')
    .attr('disabled', 'disabled');

  // Hide buttons
  this.DOMObject.find('button:not(.copyTextAreaButton)').hide();

  // Hide bootstrap switch
  this.DOMObject.find('.bootstrap-switch').hide();
};

/**
 * Render panel
 * @param {Object} element - the element object to be rendered
 * @param {number} step - the step to render
 * @param {boolean} actionEditMode - render while editing an action
 * @return {boolean} - result status
 */
PropertiesPanel.prototype.render = function(
  element,
  step,
  actionEditMode = false,
) {
  const self = this;

  // Hide panel if no element is passed
  if (element == undefined || $.isEmptyObject(element)) {
    this.DOMObject.parent().addClass('closed');
    return;
  } else {
    this.DOMObject.parent().removeClass('closed');
  }

  // Show a message if the module is disabled for a widget rendering
  if (
    element.type === 'widget' &&
    !element.enabled
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
  let requestPath = urlsForApi[element.type].getForm.url;
  requestPath = requestPath.replace(':id', element[element.type + 'Id']);

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
    const htmlTemplate = formTemplates[element.type];

    // Extend element with translation
    $.extend(res.data, {
      trans: propertiesPanelTrans,
    });

    // Create buttons object
    let buttons = {};
    if (
      res.buttons != undefined &&
      res.buttons != '' &&
      (app.readOnlyMode === undefined || app.readOnlyMode === false)
    ) {
      // Process buttons from result
      buttons = formHelpers.widgetFormRenderButtons(res.buttons);
    } else {
      // Render save button
      buttons = formHelpers.widgetFormRenderButtons(formTemplates.buttons);
    }

    const html = propertiesPanelTemplate({
      header: res.dialogTitle,
      style: element.type,
      form: htmlTemplate(res.data),
      buttons: buttons,
      trans: propertiesPanelTrans,
    });

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
    if (element.type === 'widget') {
      // Create configure tab if we have properties
      if (res.data.module.properties.length > 0) {
        // Configure tab
        forms.createFields(
          res.data.module.properties,
          self.DOMObject.find('#configureTab'),
          element.widgetId,
        );
      } else {
        // Remove configure tab
        self.DOMObject.find('[href="#configureTab"]').parent().remove();

        // Select advanced tab
        self.DOMObject.find('[href="#advancedTab"]').tab('show');
      }

      // Appearance tab ( if template exists and we have properties )
      if (
        res.data.template != undefined &&
        res.data.template.properties.length > 0
      ) {
        forms.createFields(
          res.data.template.properties,
          self.DOMObject.find('#appearanceTab'),
          element.widgetId,
        );

        // Show the appearance tab
        self.DOMObject.find('.nav-link[href="#appearanceTab"]')
          .parent().removeClass('d-none');
      }
    }

    // Set condition and handle replacements
    forms.handleFormReplacements(self.DOMObject.find('form'), res.data);
    forms.setConditions(
      self.DOMObject.find('form'),
      res.data,
      element.widgetId,
    );

    // Run form open module optional function
    if (element.type === 'widget') {
      // Pass widget options to the form as data
      if (element.getOptions != undefined) {
        self.DOMObject.find('form').data(
          'elementOptions',
          element.getOptions(),
        );
      }

      formHelpers.widgetFormEditAfterOpen(self.DOMObject, element.subType);
    } else if (
      element.type === 'region' &&
      typeof window.regionFormEditOpen === 'function'
    ) {
      window.regionFormEditOpen.bind(self.DOMObject)();
    }

    // Check for spacing issues on text fields
    forms.checkForSpacingIssues(self.DOMObject);

    // Save form data
    self.formSerializedLoadData = self.DOMObject.find('form').serialize();

    // If we're not in read only mode
    if (app.readOnlyMode === undefined || app.readOnlyMode === false) {
      // Handle buttons
      self.DOMObject.find('.properties-panel-btn').click(function(e) {
        if ($(e.target).data('action')) {
          self[$(e.target).data('action')](
            element,
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

      // Handle keyboard keys
      self.DOMObject.off('keydown').keydown(function(handler) {
        if (handler.key == 'Enter' && !$(handler.target).is('textarea')) {
          self.save(element, $(handler.target).data('subAction'));
        }
      });

      // Render action tab
      if (app.mainObjectType === 'layout') {
        self.renderActionTab(element, {
          reattach: actionEditMode,
        });
      }
    }

    // Call Xibo Init for this form
    XiboInitialise(
      '#' + self.DOMObject.attr('id'),
      (element.type == 'widget') ?
        {targetId: element.widgetId} :
        null,
    );

    // For the layout properties, call background Setup
    // TODO Move method to a common JS file
    if (element.type == 'layout') {
      backGroundFormSetup(self.DOMObject);
    }

    // Make form read only
    if (app.readOnlyMode != undefined && app.readOnlyMode === true) {
      self.makeFormReadOnly();
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
  }).fail(function(data) {
    // Clear request var after response
    self.renderRequest = undefined;

    if (data.statusText != 'requestAborted') {
      toastr.error(errorMessagesTrans.getFormFailed, errorMessagesTrans.error);
    }
  });
};

/**
 * Save Region
 * @return {boolean} false if unsuccessful
 */
PropertiesPanel.prototype.saveRegion = function() {
  const app = this.parent;
  const self = this;
  const form = $(self.DOMObject).find('form');

  // If form not loaded, prevent changes
  if (form.length == 0) {
    return false;
  }

  const element = app.selectedObject;
  const formNewData = form.serialize();
  const requestPath =
    urlsForApi.region.saveForm.url.replace(':id', element[element.type + 'Id']);

  // If form is valid, and it changed, submit it ( add change )
  if (form.valid() && self.formSerializedLoadData != formNewData) {
    // Add a save form change to the history array
    // with previous form state and the new state
    app.manager.addChange(
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
      toastr.success(res.message);
    }).catch((error) => { // Fail/error
      // Show error returned or custom message to the user
      let errorMessage = '';

      if (typeof error == 'string') {
        errorMessage += error;
      } else {
        errorMessage += error.errorThrown;
      }
      // Remove added change from the history manager
      app.manager.removeLastChange();

      // Display message in form
      formHelpers.displayErrorMessage(form, errorMessage, 'danger');

      // Show toast message
      toastr.error(errorMessage);
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
    $newActionContainer.find('#sourceId'),
    {
      $typeInput: $newActionContainer.find('#source'),
      value: actionData.sourceId,
    },
    actionData,
  );

  app.populateDropdownWithLayoutElements(
    $newActionContainer.find('#targetId'),
    {
      $typeInput: $newActionContainer.find('#target'),
      value: actionData.targetId,
      filters: ['layout', 'regions'],
    },
    actionData,
  );

  if (actionData.actionType == 'navWidget') {
    // Populate dropdowns with drawer elements
    app.populateDropdownWithLayoutElements(
      $newActionContainer.find('#widgetId'),
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
      $newActionContainer.find('#source').val('layout');
      $newActionContainer.find('#sourceId').val(app.mainObjectId);
    }
  };

  updateSource(actionData.triggerType);

  // Handle trigger type change
  $newActionContainer.find('#triggerType').on('change', function(e) {
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

module.exports = PropertiesPanel;
