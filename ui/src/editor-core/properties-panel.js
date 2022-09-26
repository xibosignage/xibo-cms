/* eslint-disable new-cap */
// PROPERTIES PANEL Module

const loadingTemplate = require('../templates/loading.hbs');
const messageTemplate = require('../templates/properties-panel-message.hbs');
const propertiesPanel = require('../templates/properties-panel.hbs');
const actionsTemplate = require('../templates/actions-form-template.hbs');
const actionsButtonTemplate =
  require('../templates/actions-button-template.hbs');
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
          app.viewer.renderRegion(
            app.getElementByTypeAndId('region', element.regionId));
        }

        // Reload data, and refresh viewer if we're saving the layout properties
        app.reloadData(mainObject, false, (element.type === 'layout'));
      };

      // Check if its a drawer widget and
      // if we need to save the target region id
      const $drawerWidgetTargetRegion =
        this.DOMObject.find('#drawerWidgetTargetRegion');
      if (
        $drawerWidgetTargetRegion.length > 0 &&
        $drawerWidgetTargetRegion.val() != ''
      ) {
        const valueToSave = $drawerWidgetTargetRegion.val();

        let requestPath = urlsForApi[element.type].setRegion.url;
        requestPath = requestPath.replace(':id', element[element.type + 'Id']);

        $.ajax({
          url: requestPath,
          type: urlsForApi[element.type].setRegion.type,
          data: {
            targetRegionId: valueToSave,
          },
        }).done(function(res) {
          if (res.success) {
            toastr.success(res.message);
            reloadData();
          } else {
            // Login Form needed?
            if (res.login) {
              window.location.href = window.location.href;
              location.reload();
            } else {
              toastr.error(errorMessagesTrans.formLoadFailed);

              // Just an error we dont know about
              if (res.message == undefined) {
                console.error(res);
              } else {
                console.error(res.message);
              }
            }
          }
        }).catch(function(jqXHR, textStatus, errorThrown) {
          console.error(jqXHR, textStatus, errorThrown);
          toastr.error(errorMessagesTrans.formLoadFailed);
        });
      } else {
        reloadData();
      }
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
 * @return {boolean} - result status
 */
PropertiesPanel.prototype.render = function(element, step) {
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

    const html = propertiesPanel({
      header: res.dialogTitle,
      style: element.type,
      form: htmlTemplate(res.data),
      buttons: buttons,
      trans: propertiesPanelTrans,
      isDrawerWidget: element.drawerWidget || false,
    });

    // Append layout html to the main div
    self.DOMObject.html(html);

    // Add the action tab (to layout editor only)
    if (app.mainObjectType === 'layout') {
      // the url to Action Add Form
      let actionFormAddRequest = urlsForApi[element.type].addActionForm.url;
      actionFormAddRequest =
        actionFormAddRequest.replace(':id', element[element.type + 'Id']);

      // append new tab
      const tabName = actionsTranslations.tableHeaders.name;
      const tabList = self.DOMObject.find('.nav-tabs');
      // TODO use template for the tab element
      const tabHtml =
      `<li class="nav-item">
        <a class="nav-link action-tab"
          href="#actionTab" role="tab" data-toggle="tab">
          <span id="actionTabName"></span>
        </a>
      </li>`;

      $(tabHtml).appendTo(tabList);
      $('#actionTabName').text(tabName);

      // render the html from actions template
      const actionsHtml = actionsTemplate({
        trans: actionsTranslations,
      });

      // append Action tab html to tab content in edit form
      const tabContent = self.DOMObject.find('.tab-content');
      $(actionsHtml).appendTo(tabContent);

      // call the javascript to render the datatable when on Actions tab
      showActionsGrid(element.type, element[element.type + 'Id']);

      // add a button to the button panel for adding an action.
      self.DOMObject.find('.button-container').append($(actionsButtonTemplate({
        addUrl: actionFormAddRequest,
        trans: actionsTranslations,
      })));
    }

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
        );
      } else {
        // Remove configure tab
        self.DOMObject.find('[href="#configureTab"]').parent().remove();

        // Select advanced tab
        self.DOMObject.find('[href="#advancedTab"]').tab('show');
      }

      // Appearance tab ( if template exists )
      if (res.data.template) {
        forms.createFields(
          res.data.template.properties,
          self.DOMObject.find('#appearanceTab'),
        );

        // Show the appearance tab
        self.DOMObject.find('.nav-link[href="#appearanceTab"]')
          .parent().removeClass('d-none');
      }
    }

    // Set condition and handle replacements
    forms.handleFormReplacements(self.DOMObject.find('form'), res.data);
    forms.setConditions(self.DOMObject.find('form'), res.data);

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

    // Handle buttons click
    if (app.readOnlyMode === undefined || app.readOnlyMode === false) {
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
    }

    // Call Xibo Init for this form
    XiboInitialise('#' + self.DOMObject.attr('id'));

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

    // Populate the drawer select if exists
    if (self.DOMObject.find('.form-editor-controls-target-region').length > 0) {
      const $selectOptionContainer =
        self.DOMObject.find(
          '.form-editor-controls-target-region #drawerWidgetTargetRegion',
        );

      // Clear container
      $selectOptionContainer.find('option:not(.default-option)').remove();
      const elementTargetRegion = element.targetRegionId || '';
      for (regionID in lD.layout.regions) {
        if (lD.layout.regions.hasOwnProperty(regionID)) {
          const region = lD.layout.regions[regionID];
          const $newOption =
          $(
            '<option value="' +
            region.regionId +
            '">' +
            region.name +
            '</option>',
          );
          if (elementTargetRegion == region.regionId) {
            $newOption.attr('selected', 'selected');
          }
          $newOption.appendTo($selectOptionContainer);
        }
      }
    }

    // Open panel if object is an invalid widget
    if (
      app.mainObjectType === 'layout' &&
      element.type === 'widget' &&
      element.isValid === 0 &&
      !$togglePanel.hasClass('opened')
    ) {
      app.togglePanel($togglePanel);
      app.savePrefs();
    }
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

module.exports = PropertiesPanel;
