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
 */
Bottombar.prototype.render = function(object) {
  const app = this.parent;
  const readOnlyModeOn = (app?.readOnlyMode === true);

  if (typeof object === 'undefined') {
    object = this.parent.selectedObject;
  }

  // Get topbar trans
  const newBottomBarTrans =
    $.extend({}, toolbarTrans, topbarTrans, bottombarTrans);

  const checkHistory = app.checkHistory();
  newBottomBarTrans.undoActiveTitle =
    (checkHistory) ? checkHistory.undoActiveTitle : '';

  // Check if trash bin is active
  const trashBinActive =
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

  if (object.type == 'widget') {
    // Render widget toolbar
    this.DOMObject.html(bottomBarViewerTemplate(
      {
        trans: newBottomBarTrans,
        readOnlyModeOn: readOnlyModeOn,
        object: object,
        objectTypeName: newBottomBarTrans.objectType.widget,
        undoActive: checkHistory.undoActive,
        trashActive: trashBinActive,
      },
    ));
  } else if (object.type == 'layout') {
    // Render layout  toolbar
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
    this.DOMObject.find('#play-btn').click(function() {
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
        objectTypeName: newBottomBarTrans.objectType.region,
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
  this.DOMObject.find('#delete-btn').click(function() {
    if (object.isDeletable) {
      lD.deleteSelectedObject();
    }
  });

  this.DOMObject.find('#undo-btn').click(function() {
    app.undoLastAction();
  });

  this.DOMObject.find('.properties-btn').click(function(e) {
    const buttonData = $(e.currentTarget).data();
    object.editPropertyForm(
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
