// VIEWER Module

// Load templates
const viewerTemplate = require('../templates/viewer.hbs');
const viewerWidgetTemplate = require('../templates/viewer-widget.hbs');
const viewerLayoutPreview = require('../templates/viewer-layout-preview.hbs');
const loadingTemplate = require('../templates/loading.hbs');

/**
 * Viewer contructor
 * @param {object} parent - Parent object
 * @param {object} container - the container to render the viewer to
 */
const Viewer = function(parent, container) {
  this.parent = parent;
  this.DOMObject = container;

  // Element dimensions inside the viewer container
  this.containerElementDimensions = null;

  // State of the inline editor (  0: off, 1: on, 2: edit )
  this.inlineEditorState = 0;

  // If the viewer is currently playing the preview
  this.previewPlaying = false;

  // Moveable object
  this.moveable = null;

  // Initialise moveable
  this.initMoveable();
};

/**
 * Calculate element scale to fit inside the container
 * @param {object} element - original object to be rendered
 * @param {object} container - container to render the element to
 * @return {object} Object containing dimensions for the object
 */
Viewer.prototype.scaleElement = function(element, container) {
  // Get container dimensions
  const containerDimensions = {
    width: container.width(),
    height: container.height(),
  };

  // Get element dimensions
  const elementDimensions = {
    width: parseFloat(
      (element.dimensions) ? element.dimensions.width : element.width),
    height: parseFloat(
      (element.dimensions) ? element.dimensions.height : element.height),
    scale: 1,
    top: 0,
    left: 0,
  };

  // Calculate ratio
  const elementRatio = elementDimensions.width / elementDimensions.height;
  const containerRatio = containerDimensions.width / containerDimensions.height;

  // Calculate scale factor
  if (elementRatio > containerRatio) {
    // element is more "landscapish" than the container
    // Scale is calculated using width
    elementDimensions.scale =
      containerDimensions.width / elementDimensions.width;
  } else {
    // Same ratio or the container is the most "landscapish"
    // Scale is calculated using height
    elementDimensions.scale =
      containerDimensions.height / elementDimensions.height;
  }

  // Calculate new values for the element using the scale factor
  elementDimensions.width *= elementDimensions.scale;
  elementDimensions.height *= elementDimensions.scale;

  // Calculate top and left values to centre the element in the container
  elementDimensions.top =
    containerDimensions.height / 2 - elementDimensions.height / 2;
  elementDimensions.left =
    containerDimensions.width / 2 - elementDimensions.width / 2;

  return elementDimensions;
};

/**
 * Render Viewer
 */
Viewer.prototype.render = function() {
  const viewerContainer = this.DOMObject;
  // IF preview is playing, refresh the bottombar
  if (this.previewPlaying && this.parent.selectedObject.type == 'layout') {
    this.parent.bottombar.render(this.parent.selectedObject);
  }

  // Show loading template
  viewerContainer.html(loadingTemplate());

  // Set preview play as false
  this.previewPlaying = false;

  // Reset container properties
  viewerContainer.css('background', '#111');
  viewerContainer.css('border', 'none');

  // Apply viewer scale to the layout
  this.containerElementDimensions =
    this.scaleElement(lD.layout, viewerContainer);

  // Apply viewer scale to the layout
  const scaledLayout = lD.layout.scale(viewerContainer);

  const html = viewerTemplate({
    type: 'layout',
    renderLayout: true,
    containerStyle: 'layout-player',
    dimensions: this.containerElementDimensions,
    layout: scaledLayout,
    trans: viewerTrans,
  });

  // Replace container html
  viewerContainer.html(html);

  // Render background image or color to the preview
  if (lD.layout.backgroundImage === null) {
    viewerContainer.find('.viewer-element')
      .css('background', lD.layout.backgroundColor);
  } else {
    // Get API link
    let linkToAPI = urlsForApi.layout.downloadBackground.url;
    // Replace ID in the link
    linkToAPI = linkToAPI.replace(':id', lD.layout.layoutId);

    viewerContainer.find('.viewer-element')
      .css(
        'background',
        'url(\'' + linkToAPI + '?preview=1&width=' +
        (lD.layout.width * this.containerElementDimensions.scale) +
        '&height=' +
        (
          lD.layout.height *
          this.containerElementDimensions.scale
        ) +
        '&proportional=0&layoutBackgroundId=' +
        lD.layout.backgroundImage + '\') top center no-repeat',
      );
  }

  // Render preview regions/widgets
  for (const regionIndex in lD.layout.regions) {
    if (lD.layout.regions.hasOwnProperty(regionIndex)) {
      const region = lD.layout.regions[regionIndex];
      const regionContainer = viewerContainer.find('#' + region.id);
      for (const widgetIndex in region.widgets) {
        if (region.widgets.hasOwnProperty(widgetIndex)) {
          const widget = region.widgets[widgetIndex];
          // Render widgets on the first widget
          this.renderWidgetToRegion(widget, regionContainer, region);
        }
      }
    }
  }

  // Handle droppable regions
  viewerContainer.find('.layout.droppable').droppable({
    greedy: true,
    tolerance: 'pointer',
    drop: _.debounce(function(event, ui) {
      lD.dropItemAdd(event.target, ui.draggable[0]);
    }, 200),
  });

  // Handle click and double click
  let clicks = 0;
  let timer = null;
  viewerContainer.find('.viewer-element-select').off()
    .on('mousedown', function(e) {
      e.stopPropagation();

      // Right click open context menu
      if (e.which == 3) {
        return;
      }

      if ($(e.target).hasClass('layout')) {
        lD.selectObject();
      } else {
        clicks++;

        if (clicks === 1 && e.which === 1) {
          timer = setTimeout(function() {
            // Single click action
            clicks = 0;

            // Select region ( only if target is not selected )
            if (!$(e.target).hasClass('selected-region')) {
              lD.selectObject($(e.target));
            }
          }, 200);
        } else {
          // Double click action
          clearTimeout(timer);
          clicks = 0;

          // Select widget if exists
          if ($(e.target).find('.designer-widget').length > 0) {
            lD.selectObject($(e.target).find('.designer-widget'));
          }
        }
      }
    }).on('dblclick', function(e) {
      // Cancel default double click
      e.preventDefault();
    }).children().on('mousedown dblclick', function(e) {
      // Cancel default click
      e.stopPropagation();
    }).contextmenu(function(ev) {
      // Context menu
      if (
        $(ev.target).is('.editable, .deletable, .permissionsModifiable')
      ) {
        // Open context menu
        lD.openContextMenu(ev.target, {
          x: ev.pageX,
          y: ev.pageY,
        });
      }
      // Prevent browser menu to open
      return false;
    });

  // If an element is selected, use it as a target for the moveable
  this.moveable.target = (this.DOMObject.find('.selected-region').length > 0) ?
    this.DOMObject.find('.selected-region') :
    null;

  // Handle fullscreen button
  viewerContainer.parent().find('#fullscreenBtn').off().click(function() {
    this.toggleFullscreen();
  }.bind(this));
};

/**
 * Render widget in region container
 * @param {object} element - widget to render
 * @param {object} container - region container
 * @param {object} region - region object
 */
Viewer.prototype.renderWidgetToRegion = function(
  element,
  container,
  region,
) {
  const self = this;

  // If there was still a render request, abort it
  if (
    this.renderRequest != undefined &&
    this.renderRequest.target == container
  ) {
    this.renderRequest.abort('requestAborted');
  }

  // Show loading
  container.html(loadingTemplate());

  // Apply scaling
  const containerElementDimensions = {
    width: container.width(),
    height: container.height(),
  };

  // Get request path
  let requestPath = urlsForApi.region.preview.url;
  requestPath = requestPath.replace(
    ':id',
    region['regionId'],
  );

  requestPath +=
    '?widgetId=' + element[element.type + 'Id'] +
    '&width=' + containerElementDimensions.width +
    '&height=' + containerElementDimensions.height;

  // Get HTML for the given element from the API
  this.renderRequest = {
    target: container,
  };

  this.renderRequest.request = $.get(requestPath).done(function(res) {
    // Clear request var after response
    self.renderRequest = undefined;

    // Prevent rendering null html
    if (!res.success) {
      toastr.error(res.message);
      container.html(res.message);
      return;
    }

    const elementType = (element.type + '_' + element.subType);

    // Replace container html
    const html = viewerWidgetTemplate({
      res: res,
      id: element.id,
      regionId: region['id'],
      dimensions: containerElementDimensions,
      type: elementType,
      editable: element.isEditable,
      parentId: element.regionId,
      selected: element.selected,
      trans: viewerTrans,
    });

    // Append layout html to the container div
    container.html(html);

    // Handle droppables
    container.find('.droppable').droppable({
      greedy: true,
      tolerance: 'pointer',
      drop: _.debounce(function(event, ui) {
        lD.dropItemAdd(event.target, ui.draggable[0]);
      }, 200),
    });

    // Update navbar
    lD.bottombar.render(lD.selectedObject, res);

    // If inline editor is on, show the controls for it
    // ( fixing asyc load problem )
    if (lD.propertiesPanel.inlineEditor) {
      // Show inline editor controls
      this.showInlineEditor();
    }
  }.bind(this)).fail(function(res) {
    // Clear request var after response
    self.renderRequest = undefined;

    if (res.statusText != 'requestAborted') {
      toastr.error(errorMessagesTrans.previewFailed);
      container.html(errorMessagesTrans.previewFailed);
    }
  });
};

/**
 * Play preview
 * @param {string} url - Preview url
 * @param {object} dimensions - Preview dimensions
 */
Viewer.prototype.playPreview = function(url, dimensions) {
  // Compile layout template with data
  const html = viewerLayoutPreview({
    url: url,
    width: dimensions.width,
    height: dimensions.height,
  });

  // Append layout html to the main div
  this.DOMObject.find('.layout-player').html(html);
};

/**
 * Toggle fullscreen
 */
Viewer.prototype.toggleFullscreen = function() {
  // If inline editor is opened, needs to be saved/closed
  if (this.inlineEditorState == 2) {
    // Close editor content
    this.closeInlineEditorContent();
  }

  this.DOMObject.parents('#layout-viewer-container').toggleClass('fullscreen');
  this.parent.editorContainer.toggleClass('fullscreen-mode');
  this.render(lD.selectedObject, lD.layout);
};

/**
 * Initialise moveable
 */
Viewer.prototype.initMoveable = function() {
  /**
 * Save the new position of the region
 * @param {object} region - Region object
 */
  const saveRegionProperties = function(region) {
    const scale = lD.viewer.containerElementDimensions.scale;
    const transform = {
      'width': parseInt($(region).width() / scale),
      'height': parseInt($(region).height() / scale),
      'top': parseInt($(region).position().top / scale),
      'left': parseInt($(region).position().left / scale),
    };

    if ($(region).attr('id') == lD.selectedObject.id) {
      lD.layout.regions[$(region).attr('id')].transform(transform, false);

      if (typeof window.regionChangesForm === 'function') {
        window.regionChangesForm();
        lD.propertiesPanel.saveRegion();
        lD.viewer.render();
      }
    }
  };

  // Resize frame helper
  const resizeFrame = {
    translate: [0, 0],
  };

  // Create moveable
  this.moveable = new Moveable(document.body, {
    draggable: true,
    resizable: true,
  });

  /* draggable */
  this.moveable.on('drag', (e) => {
    e.target.style.left = `${e.left}px`;
    e.target.style.top = `${e.top}px`;
  }).on('dragEnd', (e) => {
    saveRegionProperties(e.target);
  });

  /* resizable */
  this.moveable.on('resizeStart', (e) => {
    e.setOrigin(['%', '%']);
    e.dragStart && e.dragStart.set(resizeFrame.translate);
  }).on('resize', (e) => {
    const beforeTranslate = e.drag.beforeTranslate;
    resizeFrame.translate = beforeTranslate;
    e.target.style.width = `${e.width}px`;
    e.target.style.height = `${e.height}px`;
    e.target.style.transform =
      `translate(${beforeTranslate[0]}px, ${beforeTranslate[1]}px)`;
  }).on('resizeEnd', (e) => {
    saveRegionProperties(e.target);
  });
};

module.exports = Viewer;
