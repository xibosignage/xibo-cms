// Load templates
const bottomBarViewerTemplate = require('../templates/bottombar-viewer.hbs');

/**
 * Bottom topbar contructor
 * @param {object} parent - Parent object
 * @param {object} container - the container to render the bottombar to
 */
const Bottombar = function(parent, container) {
  this.parent = parent;
  this.DOMObject = container;
};

/**
 * Render bottombar
 * @param {object} object - the object to render the bottombar to
 * @param {boolean} renderMultiple
 */
Bottombar.prototype.render = function(object, renderMultiple = true) {
  const app = this.parent;
  const self = this;
  const readOnlyModeOn = (app?.readOnlyMode === true);
  let trashBinActive = false;
  let multipleSelected = false;

  if (typeof object === 'undefined') {
    object = this.parent.selectedObject;
  }

  // Get topbar trans
  const newBottomBarTrans =
    $.extend({}, toolbarTrans, topbarTrans, bottombarTrans);

  const checkHistory = app.checkHistory();
  newBottomBarTrans.undoActiveTitle =
    (checkHistory) ? checkHistory.undoActiveTitle : '';

  // Do we have multiple objects selected
  const selectedInViewer = lD.viewer.getMultipleSelected();
  if (
    renderMultiple &&
    selectedInViewer.multiple === true
  ) {
    multipleSelected = true;
    trashBinActive = selectedInViewer.canBeDeleted;

    newBottomBarTrans.trashBinActiveTitle =
    (trashBinActive) ?
      newBottomBarTrans.deleteMultipleObjects :
      '';
  } else {
    // Check if trash bin is active
    trashBinActive =
      app.selectedObject.isDeletable &&
      (app?.readOnlyMode === false);

    // Get text for bin tooltip
    newBottomBarTrans.trashBinActiveTitle =
      (trashBinActive) ?
        newBottomBarTrans.deleteObject.replace(
          '%object%',
          app.selectedObject.type,
        ) :
        '';
  }

  if (multipleSelected) {
    // Render toolbar for multiple
    this.DOMObject.html(bottomBarViewerTemplate(
      {
        trans: newBottomBarTrans,
        readOnlyModeOn: readOnlyModeOn,
        undoActive: checkHistory.undoActive,
        trashActive: trashBinActive,
      },
    ));
  } else if (object.type == 'widget') {
    // Render widget toolbar
    const renderBottomBar = function(templateTitle) {
      self.DOMObject.html(bottomBarViewerTemplate(
        {
          trans: newBottomBarTrans,
          readOnlyModeOn: readOnlyModeOn,
          object: object,
          objectTypeName: newBottomBarTrans.objectType.widget,
          moduleTemplateTitle: templateTitle,
          undoActive: checkHistory.undoActive,
          trashActive: trashBinActive,
        },
      ));
    };

    // Check if we have datatype
    if (object.moduleDataType != '' && object.moduleDataType != undefined) {
      // Get template
      lD.templateManager.getTemplateById(
        object.getOptions().templateId,
        object.moduleDataType,
      ).then((template) => {
        renderBottomBar(template.title);
      });
    } else {
      renderBottomBar();
    }
  } else if (object.type == 'layout') {
    // Render layout toolbar
    this.DOMObject.html(bottomBarViewerTemplate(
      {
        trans: newBottomBarTrans,
        readOnlyModeOn: readOnlyModeOn,
        renderLayout: true,
        object: object,
        objectTypeName: newBottomBarTrans.objectType.layout,
        undoActive: checkHistory.undoActive,
        trashActive: trashBinActive,
      },
    ));

    // Handle play button ( play or pause )
    this.DOMObject.find('#play-btn').on('click', function() {
      if (lD.viewer.previewPlaying) {
        app.viewer.stopPreview();
      } else {
        app.viewer.playPreview();
      }
    });
  } else if (object.type == 'region') {
    // Render region toolbar
    this.DOMObject.html(bottomBarViewerTemplate(
      {
        trans: newBottomBarTrans,
        readOnlyModeOn: readOnlyModeOn,
        object: object,
        objectTypeName: newBottomBarTrans.objectType[object.subType],
        undoActive: checkHistory.undoActive,
        trashActive: trashBinActive,
      },
    ));
  } else if (
    object.type == 'element' ||
    object.type == 'element-group'
  ) {
    const widget = lD.getObjectByTypeAndId(
      'widget',
      'widget_' + object.regionId + '_' + object.widgetId,
      'canvas',
    );

    // If element has media Id or media Name
    if (
      object.mediaId != undefined || object.mediaName != undefined
    ) {
      // If name is defined, use media Id and name in the tooltip/helper
      object.elementMediaInfo = {
        name: object.mediaName,
        id: object.mediaId,
      };
    }

    // Render element and element group toolbar
    this.DOMObject.html(bottomBarViewerTemplate(
      {
        trans: newBottomBarTrans,
        readOnlyModeOn: readOnlyModeOn,
        object: object,
        widget: widget,
        objectTypeName: newBottomBarTrans.objectType[object.type],
        undoActive: checkHistory.undoActive,
        trashActive: trashBinActive,
      },
    ));
  }

  // If read only mode is enabled
  if (app?.readOnlyMode === true) {
    // Create the read only alert message
    const $readOnlyMessage =
      $('<div id="read-only-message" class="alert alert-warning' +
      'text-center navbar-nav" data-container=".editor-bottom-bar"' +
      'data-toggle="tooltip" data-placement="bottom" data-title="' +
      layoutEditorTrans.readOnlyModeMessage +
      '" role="alert"><strong>' + layoutEditorTrans.readOnlyModeTitle +
      '</strong>:&nbsp;' + layoutEditorTrans.readOnlyModeMessage + '</div>');

    // Prepend the element to the bottom toolbar's content
    $readOnlyMessage.insertAfter(this.DOMObject.find('.pull-left'))
      .on('click', lD.checkoutLayout);
  }

  // Button handlers
  this.DOMObject.find('#delete-btn').on('click', function() {
    lD.deleteSelectedObject();
  });

  this.DOMObject.find('#undo-btn').on('click', function() {
    app.undoLastAction();
  });

  this.DOMObject.find('.properties-btn').on('click', function(e) {
    const buttonData = $(e.currentTarget).data();
    let targetObj = object;

    if ($(e.currentTarget).hasClass('properties-widget')) {
      targetObj = lD.getObjectByTypeAndId(
        'widget',
        'widget_' + object.regionId + '_' + object.widgetId,
        'canvas',
      );
    }

    targetObj.editPropertyForm(
      buttonData['property'],
      buttonData['propertyType'],
    );
  });

  // Reload tooltips
  app.common.reloadTooltips(this.DOMObject);
};

/**
 * Show message on play button
 */
Bottombar.prototype.showPlayMessage = function() {
  const self = this;
  const $target = self.DOMObject.find('#play-btn i');

  // Show popover
  $target.popover('show');

  // Destroy popover after some time
  setTimeout(function() {
    $target.popover('dispose');
  }, 4000);
};

module.exports = Bottombar;
