/* eslint-disable new-cap */
// VIEWER Module

// Load templates
const viewerTemplate = require('../templates/viewer.hbs');
const viewerWidgetTemplate = require('../templates/viewer-widget.hbs');
const viewerLayoutPreview = require('../templates/viewer-layout-preview.hbs');
const viewerActionEditRegionTemplate =
  require('../templates/viewer-action-edit-region.hbs');
const loadingTemplate = require('../templates/loading.hbs');
const viewerElementTemplate = require('../templates/viewer-element.hbs');
const viewerElementGroupTemplate =
  require('../templates/viewer-element-group.hbs');
const viewerElementContentTemplate =
  require('../templates/viewer-element-content.hbs');
const drawThrottle = 60;

/**
 * Viewer contructor
 * @param {object} parent - Parent object
 * @param {object} container - the container to render the viewer to
 */
const Viewer = function(parent, container) {
  this.parent = parent;
  this.DOMObject = container;

  // First load
  this.reload = true;

  // Element dimensions inside the viewer container
  this.containerElementDimensions = null;

  // State of the inline editor (  0: off, 1: on, 2: edit )
  this.inlineEditorState = 0;

  // If the viewer is currently playing the preview
  this.previewPlaying = false;

  // Theme ( light / dark)
  this.theme = 'light';
  this.themeColors = {
    light: '#f9f9f9',
    dark: '#333333',
  };

  // Moveable object
  this.moveable = null;
  this.moveableOptions = {
    snapToGrid: false,
    snapGridGap: 20,
    snapToBorders: false,
    snapToElements: false,
  };

  // Layout orientation
  this.orientation = null;

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
 * Get layout orientation
 * @param {number} width
 * @param {number} height
 * @return {string} portrait | landscape
 * */
Viewer.prototype.getLayoutOrientation = function(width, height) {
  // Get layout orientation
  if (width < height) {
    return 'portrait';
  } else {
    return 'landscape';
  }
};


/**
 * Render viewer
 * @param {object} forceReload - Force reload
*/
Viewer.prototype.render = function(forceReload = false) {
  // Check background colour and set theme
  const hsvColor =
    (this.parent.layout.backgroundColor) ?
      Color(this.parent.layout.backgroundColor) : null;

  // If we don't have a background colour, set the theme to light
  // or if the background colour is too light or saturated
  // set the theme to dark
  if (
    hsvColor &&
    (
      (
        hsvColor.values.hsv[2] > 75 &&
        hsvColor.values.hsv[1] < 50
      ) ||
      hsvColor.values.hsv[2] > 90
    )
  ) {
    this.theme = 'dark';
  } else {
    this.theme = 'light';
  }

  // Refresh if it's not the reload
  if (!forceReload && !this.reload) {
    this.update();
    return;
  }

  // Set reload to false
  this.reload = false;

  // Render the viewer
  this.DOMObject.html(viewerTemplate());

  const $viewerContainer = this.DOMObject;

  // If preview is playing, refresh the bottombar
  if (this.previewPlaying && this.parent.selectedObject.type == 'layout') {
    this.parent.bottombar.render(this.parent.selectedObject);
  }

  // Show loading template
  $viewerContainer.html(loadingTemplate());

  // Set preview play as false
  this.previewPlaying = false;

  // Reset container properties
  $viewerContainer.css('background',
    (this.theme == 'dark') ? '#2c2d2e' : '#F3F8FF',
  );
  $viewerContainer.css('border', 'none');

  // Apply viewer scale to the layout
  this.containerElementDimensions =
    this.scaleElement(lD.layout, $viewerContainer);

  this.orientation = this.getLayoutOrientation(
    this.containerElementDimensions.width,
    this.containerElementDimensions.height,
  );

  // Apply viewer scale to the layout
  const scaledLayout = lD.layout.scale($viewerContainer);

  const html = viewerTemplate({
    type: 'layout',
    renderLayout: true,
    containerStyle: 'layout-player',
    dimensions: this.containerElementDimensions,
    layout: scaledLayout,
    trans: viewerTrans,
    theme: this.theme,
    orientation: this.orientation,
  });

  // Replace container html
  $viewerContainer.html(html);

  // Render background image or color to the preview
  if (lD.layout.backgroundImage === null) {
    $viewerContainer.find('.viewer-element')
      .css('background', lD.layout.backgroundColor);
  } else {
    // Get API link
    let linkToAPI = urlsForApi.layout.downloadBackground.url;
    // Replace ID in the link
    linkToAPI = linkToAPI.replace(':id', lD.layout.layoutId);

    $viewerContainer.find('.viewer-element')
      .css({
        background:
          'url(\'' + linkToAPI + '?preview=1&width=' +
          (lD.layout.width * this.containerElementDimensions.scale) +
          '&height=' +
          (
            lD.layout.height *
            this.containerElementDimensions.scale
          ) +
          '&proportional=0&layoutBackgroundId=' +
          lD.layout.backgroundImage + '\') top center no-repeat',
        backgroundSize: '100% 100%',
        backgroundColor: lD.layout.backgroundColor,
      });
  }

  // Render preview regions/widgets
  for (const regionIndex in lD.layout.regions) {
    if (lD.layout.regions.hasOwnProperty(regionIndex)) {
      this.renderRegion(lD.layout.regions[regionIndex]);
    }
  }

  // Render preview canvas if it's not an empty object
  (!$.isEmptyObject(lD.layout.canvas)) && this.renderCanvas(lD.layout.canvas);

  // Handle UI interactions
  this.handleInteractions();

  // If we are selecting an element in a group,
  // we need to put the group in edit mode
  if (
    this.parent.selectedObject.type == 'element' &&
    this.parent.selectedObject.groupId != undefined
  ) {
    this.editGroup(
      this.DOMObject.find(
        '.designer-element-group#' +
        this.parent.selectedObject.groupId,
      ),
      this.parent.selectedObject.elementId,
    );
  }

  // Refresh on window resize
  $(window).on('resize', function() {
    this.update();
  }.bind(this));

  // Update moveable
  this.updateMoveable(true);

  // Update moveable options
  this.updateMoveableOptions({
    savePreferences: false,
  });

  // Update moveable UI
  this.updateMoveableUI();

  // Initialise tooltips
  this.parent.common.reloadTooltips(
    this.DOMObject.parent(),
  );
};

/**
 * Handle viewer interactions
 */
Viewer.prototype.handleInteractions = function() {
  const self = this;
  const $viewerContainer = this.DOMObject;

  const calculatePosition = function(
    $droppableArea,
    event,
    ui,
  ) {
    const draggableDimensions = {
      width: ui.draggable.width(),
      height: ui.draggable.height(),
    };

    const droppableAreaPosition = {
      x: $droppableArea.offset().left,
      y: $droppableArea.offset().top,
    };

    // Get position, event location
    // adjusted with the viewer container
    // and the helper offset
    const position = {
      top: event.pageY -
        droppableAreaPosition.y -
        (draggableDimensions.height / 2),
      left: event.pageX -
        droppableAreaPosition.x -
        (draggableDimensions.width / 2),
    };

    // Scale value to original size ( and parse to int )
    position.top = parseInt(
      position.top /
      self.containerElementDimensions.scale);
    position.left = parseInt(
      position.left /
      self.containerElementDimensions.scale);

    return position;
  };

  // Handle droppable layout area
  const $droppableArea = $viewerContainer.find('.layout.droppable');
  $droppableArea.droppable({
    greedy: true,
    accept: (draggable) => {
      // Check target
      return lD.common.hasTarget(draggable, 'layout');
    },
    tolerance: 'pointer',
    drop: _.debounce(function(event, ui) {
      const position = calculatePosition(
        $droppableArea,
        event,
        ui,
      );

      lD.dropItemAdd(event.target, ui.draggable[0], position);
    }, 200),
    activate: function(_event, ui) {
      // if draggable is an action, add special class
      if ($(ui.draggable).data('type') == 'actions') {
        $(this).addClass('ui-droppable-actions-target');
      }
    },
  });

  // Handle droppable on the main container
  $viewerContainer.droppable({
    greedy: true,
    accept: (draggable) => {
      // Check target
      return lD.common.hasTarget(draggable, 'layout');
    },
    tolerance: 'pointer',
    drop: _.debounce(function(event, ui) {
      lD.dropItemAdd(event.target, ui.draggable[0]);
    }, 200),
    activate: function(_event, ui) {
      // if draggable is an action, add special class
      if ($(ui.draggable).data('type') == 'actions') {
        $(this).addClass('ui-droppable-actions-target');
      }
    },
  });

  // Handle droppable empty regions ( zones )
  this.DOMObject.find(
    '.designer-region.designer-region-zone',
  ).droppable({
    greedy: true,
    tolerance: 'pointer',
    accept: (draggable) => {
      // Check target
      return lD.common.hasTarget(draggable, 'zone');
    },
    drop: _.debounce(function(event, ui) {
      lD.dropItemAdd(event.target, ui.draggable[0]);
    }, 200),
  });

  // Handle droppable empty regions ( playlist )
  this.DOMObject.find(
    '.designer-region.designer-region-playlist',
  ).droppable({
    greedy: true,
    tolerance: 'pointer',
    accept: (draggable) => {
      // Check target
      return lD.common.hasTarget(draggable, 'playlist');
    },
    drop: _.debounce(function(event, ui) {
      lD.dropItemAdd(event.target, ui.draggable[0]);
    }, 200),
  });

  // Handle droppable group
  this.DOMObject.find(
    '.designer-element-group',
  ).each((_idx, element) => {
    const $el = $(element);
    let elementsType = 'global';

    // Go through elements and check if there's any other than global
    $el.find('.designer-element').each((_idx, elementInGroup) => {
      const $elInGroup = $(elementInGroup);
      // Check element type
      if ($elInGroup.data('elementType') != 'global') {
        elementsType = $elInGroup.data('elementType');
        return false;
      }
    });

    $el.droppable({
      greedy: true,
      tolerance: 'pointer',
      accept: (draggable) => {
        // Validate if element is of the same
        // type as existing element or group

        // Check if element group is in edit mode
        if (!$el.hasClass('editing')) {
          return false;
        }

        return (
          lD.common.hasTarget(draggable, 'element') &&
          (
            $(draggable).data('dataType') == elementsType ||
            $(draggable).data('dataType') == 'global'
          )
        );
      },
      drop: _.debounce(function(event, ui) {
        const position = calculatePosition(
          $droppableArea,
          event,
          ui,
        );

        lD.dropItemAdd(event.target, ui.draggable[0], position);
      }, 200),
    });
  });


  // Handle click and double click
  let clicks = 0;
  let timer = null;
  $viewerContainer.parent().find('.viewer-element-select').off()
    .on('mousedown', function(e) {
      e.stopPropagation();

      // Right click open context menu
      if (e.which == 3) {
        return;
      }

      const playlistEditorBtnClick = function(playlistId) {
        // Edit region if it's a playlist
        // Get region object
        const regionObject =
          lD.getElementByTypeAndId('region', playlistId);
        // Open playlist editor
        lD.openPlaylistEditor(
          regionObject.playlists.playlistId,
          regionObject);
      };

      // Get click position
      const clickPosition = {
        left: e.pageX -
          $viewerContainer.find('.layout.viewer-element-select').offset().left,
        top: e.pageY -
          $viewerContainer.find('.layout.viewer-element-select').offset().top,
      };

      // Scale value to original size ( and parse to int )
      clickPosition.top = parseInt(
        clickPosition.top /
        self.containerElementDimensions.scale);
      clickPosition.left = parseInt(
        clickPosition.left /
        self.containerElementDimensions.scale);

      // Click on layout or layout wrapper to clear selection
      // or add item to the layout
      if (
        $(e.target).hasClass('ui-droppable-actions-target')
      ) {
        // Add action to the selected object
        lD.selectObject({
          target: $(e.target),
          forceSelect: true,
        });
      } else if (
        (
          $(e.target).hasClass('designer-region-zone') ||
          $(e.target).hasClass('designer-region-playlist') ||
          $(e.target).hasClass('designer-widget') ||
          $(e.target).hasClass('designer-element')
        ) &&
        $(e.target).hasClass('ui-droppable-active')
      ) {
        // Add item to the selected element
        lD.selectObject({
          target: $(e.target),
          forceSelect: true,
          clickPosition: clickPosition,
        });
      } else if (
        $(e.target).is('.designer-element-group.editing.ui-droppable-active')
      ) {
        // Add item to the selected element group
        lD.selectObject({
          target: $(e.target),
          forceSelect: true,
          clickPosition: clickPosition,
        });
      } else if (
        $(e.target).hasClass('layout-wrapper') ||
        $(e.target).hasClass('layout')
      ) {
        // Clear selected object
        lD.selectObject({
          target: null,
          reloadViewer: false,
          clickPosition: $(e.target).hasClass('layout') ? clickPosition : null,
        });
        self.selectElement();
      } else if (
        $(e.target).hasClass('group-edit-btn')
      ) {
        self.editGroup(
          $(e.target).parents('.designer-element-group'),
        );
      } else if (
        $(e.target).hasClass('playlist-edit-btn')
      ) {
        // Edit region if it's a playlist
        playlistEditorBtnClick($(e.target)
          .parents('.designer-region-playlist').attr('id'));
      } else {
        // Select elements inside the layout
        clicks++;

        // Single click
        if (clicks === 1 && e.which === 1) {
          timer = setTimeout(function() {
            // Single click action
            clicks = 0;

            if (
              $(e.target).data('subType') === 'playlist' &&
              $(e.target).hasClass('designer-region') &&
              !$(e.target).hasClass('selected')
            ) {
              // Select region
              lD.selectObject({
                target: $(e.target),
              });
              self.selectElement($(e.target));
            } else if (
              $(e.target).data('subType') === 'zone' &&
              $(e.target).hasClass('designer-region') &&
              !$(e.target).hasClass('selected')
            ) {
              // Select zone
              lD.selectObject({
                target: $(e.target),
              });
              self.selectElement($(e.target));
            } else if (
              $(e.target).find('.designer-widget').length > 0 &&
              !$(e.target).find('.designer-widget').hasClass('selected') &&
              !$(e.target).hasClass('selected')
            ) {
              // Select widget if exists
              lD.selectObject({
                target: $(e.target).find('.designer-widget'),
              });
              self.selectElement($(e.target));
            } else if (
              $(e.target).hasClass('designer-element') &&
              !$(e.target).hasClass('selected')
            ) {
              // Select element if exists
              lD.selectObject({
                target: $(e.target),
                clickPosition: clickPosition,
              });
              self.selectElement($(e.target));
            } else if (
              $(e.target).hasClass('group-select-overlay') &&
              !$(e.target).parent().hasClass('selected')
            ) {
              // Select element if exists
              lD.selectObject({
                target: $(e.target).parent(),
                clickPosition: clickPosition,
              });
              self.selectElement($(e.target).parent());
            }
          }, 200);
        } else {
          // Double click action
          clearTimeout(timer);
          clicks = 0;

          if (
            $(e.target).data('subType') === 'playlist'
          ) {
            // Edit region if it's a playlist
            playlistEditorBtnClick($(e.target).attr('id'));
          } else if (
            $(e.target).hasClass('group-select-overlay')
          ) {
            self.editGroup(
              $(e.target).parents('.designer-element-group'),
            );
          } else {
            // Move out from group editing
            lD.selectObject();
            self.selectElement();
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
      // If target has class group-select-overlay
      // set target to the parent
      if ($(ev.target).hasClass('group-select-overlay')) {
        ev.target = $(ev.target).parent()[0];
      }

      // Context menu
      if (
        $(ev.target).is('.editable, .deletable, .permissionsModifiable') &&
        !$(ev.target).hasClass('contextMenuOpen') &&
        !(
          $(ev.target).hasClass('designer-element-group') &&
          $(ev.target).hasClass('editing')
        )
      ) {
        // Open context menu
        lD.openContextMenu(ev.target, {
          x: ev.pageX,
          y: ev.pageY,
        });

        // Mark context menu as open for the target
        $(ev.target).addClass('contextMenuOpen');
      }
      // Prevent browser menu to open
      return false;
    });

  // Handle fullscreen button
  $viewerContainer.parent().find('#fullscreenBtn').off().click(function() {
    this.reload = true;
    this.toggleFullscreen();
  }.bind(this));

  // Handle snap buttons
  $viewerContainer.parent().find('#snapToGrid').off().click(function(ev) {
    this.moveableOptions.snapToGrid = !this.moveableOptions.snapToGrid;

    // Turn off snap to element if grid is on
    if (this.moveableOptions.snapToGrid) {
      this.moveableOptions.snapToElements = false;
    }

    // Update moveable options
    this.updateMoveableOptions();

    // Update moveable UI
    this.updateMoveableUI();
  }.bind(this));

  $viewerContainer.parent().find('#snapToBorders').off().click(function() {
    this.moveableOptions.snapToBorders = !this.moveableOptions.snapToBorders;

    // Update moveable options
    this.updateMoveableOptions();

    // Update moveable UI
    this.updateMoveableUI();
  }.bind(this));

  $viewerContainer.parent().find('#snapToElements').off().click(function() {
    this.moveableOptions.snapToElements = !this.moveableOptions.snapToElements;

    // Turn off snap to grid if element is on
    if (this.moveableOptions.snapToElements) {
      this.moveableOptions.snapToGrid = false;
    }

    // Update moveable options
    this.updateMoveableOptions();

    // Update moveable UI
    this.updateMoveableUI();
  }.bind(this));

  const updateMoveableWithDebounce = _.debounce(function() {
    self.updateMoveableOptions();
  }, 1000);
  $viewerContainer.parent().find('.snap-to-grid-value')
    .off().on('input', function(ev) {
      let gridValue = Number($(ev.currentTarget).val());

      if (gridValue < 1) {
        gridValue = 1;
        $(ev.currentTarget).val(1).trigger('select');
      }

      self.moveableOptions.snapGridGap = gridValue;

      // Update moveable options
      updateMoveableWithDebounce();
    });
};

/**
 * Update Viewer
 */
Viewer.prototype.update = function() {
  const $viewerContainer = this.DOMObject;
  const $viewElement = $viewerContainer.find('.viewer-element');
  const self = this;

  // Hide viewer element
  $viewElement.hide();

  // Apply viewer scale to the layout
  this.containerElementDimensions =
    this.scaleElement(lD.layout, $viewerContainer);

  // Apply viewer scale to the layout
  lD.layout.scale($viewerContainer);

  $viewElement.css({
    width: this.containerElementDimensions.width,
    height: this.containerElementDimensions.height,
    top: this.containerElementDimensions.top,
    left: this.containerElementDimensions.left,
    scale: this.containerElementDimensions.scale,
  });

  // Show viewer element
  $viewElement.show();

  // Render preview regions/widgets
  for (const regionIndex in lD.layout.regions) {
    if (lD.layout.regions.hasOwnProperty(regionIndex)) {
      this.updateRegion(lD.layout.regions[regionIndex]);
    }
  }

  // Render preview elements
  for (const canvasWidget in lD.layout.canvas.widgets) {
    if (lD.layout.canvas.widgets.hasOwnProperty(canvasWidget)) {
      const widgetElements = lD.layout.canvas.widgets[canvasWidget].elements;

      for (const elementIndex in widgetElements) {
        if (widgetElements.hasOwnProperty(elementIndex)) {
          this.updateElement(widgetElements[elementIndex]);
          self.renderElement(
            widgetElements[elementIndex],
            lD.layout.canvas,
          );
        }
      }
    }
  }

  // Update action helper if exists
  $viewElement.find('.designer-region-drawer').each(function() {
    this.updateRegion(
      lD.layout.drawer,
      ($viewElement.data('target') == 'layout'),
    );
  }.bind(this));

  // Update moveable
  this.updateMoveable(true);

  // Update moveable options
  this.updateMoveableOptions();
};

/**
 * Render widget in region container
 * @param {object} region - region object
 * @param {object} widgetToLoad - widget object to render
 * @return {jqXHR} - ajax request object
 */
Viewer.prototype.renderRegion = function(
  region,
  widgetToLoad = null,
) {
  const self = this;
  const $container = this.DOMObject.find(`#${region.id}`);
  const isPlaylist = region.subType == 'playlist';

  // Get first widget of the region
  const widget = (widgetToLoad) ?
    widgetToLoad :
    region.widgets[Object.keys(region.widgets)[0]];

  // If there's no widget, return
  if (!widget && !isPlaylist) {
    return;
  }

  // If there was still a render request, abort it
  if (
    this.renderRequest != undefined &&
    this.renderRequest.target == $container
  ) {
    this.renderRequest.abort('requestAborted');
  }

  // Show loading
  $container.html(loadingTemplate());

  // Apply scaling
  const containerElementDimensions = {
    width: $container.width(),
    height: $container.height(),
  };

  // Get request path
  let requestPath = urlsForApi.region.preview.url;
  requestPath = requestPath.replace(
    ':id',
    region['regionId'],
  );

  requestPath +=
    '?width=' + containerElementDimensions.width +
    '&height=' + containerElementDimensions.height;

  // If it's not a playlist, add widget to request
  if (!isPlaylist) {
    requestPath += '&widgetId=' + widget['widgetId'];
  }

  // Get HTML for the given element from the API
  this.renderRequest = {
    target: $container,
  };

  // If region is selected, update moveable
  if (region.selected) {
    this.selectElement($container);
  }

  this.renderRequest.request = $.get(requestPath).done(function(res) {
    // Clear request var after response
    self.renderRequest = undefined;

    // Prevent rendering null html
    if (!res.success) {
      toastr.error(res.message);
      $container.html(res.message);
      return;
    }

    const options = {
      res: res,
      regionId: region['id'],
      trans: viewerTrans,
    };

    if (isPlaylist) {
      $.extend(true, options, {
        elementType: 'playlist',
      });
    } else {
      $.extend(true, options, {
        id: widget.id,
        widgetId: widget.widgetId,
        elementType: (widget.type + '_' + widget.subType),
        editable: widget.isEditable,
        parentId: widget.regionId,
        selected: widget.selected,
        drawerWidget: widget.drawerWidget,
      });
    }

    $.extend(toolbarTrans, topbarTrans);

    // Replace container html
    const html = viewerWidgetTemplate(options);

    // Append layout html to the container div
    $container.html(html);

    // If it's playlist, add playlist edit button
    if (isPlaylist) {
      $container.append(`<div class="playlist-edit-btn viewer-element-select"
        title="${viewerTrans.editPlaylist}">
          <i class="fa fa-list-ol" aria-hidden="true"></i>
      </div>`);
    }

    // If widget is selected, update moveable for the region
    if (widget && widget.selected) {
      this.selectElement($container);
    }

    // Select droppables in the region
    let $droppables = $container.find('.droppable');

    // Check if region is also a droppable
    // if so, add it to the droppables
    if ($container.hasClass('droppable')) {
      $droppables = $.merge($droppables, $container);
    }

    // Init droppables
    $droppables.droppable({
      greedy: true,
      tolerance: 'pointer',
      accept: function(draggable) {
        // Get type ( if region, get subType )
        const dataType =
          ($(this).hasClass('designer-region')) ?
            $(this).data('subType') :
            $(this).data('type');

        // Check target
        return lD.common.hasTarget(draggable, dataType);
      },
      drop: _.debounce(function(event, ui) {
        lD.dropItemAdd(event.target, ui.draggable[0]);
      }, 200),
      activate: function(_event, ui) {
        // if draggable is an action, add special class
        if ($(ui.draggable).data('type') == 'actions') {
          $(this).addClass('ui-droppable-actions-target');
        }
      },
    });

    // Update navbar
    lD.bottombar.render(
      (widgetToLoad) ? widgetToLoad : lD.selectedObject,
      res,
    );

    // If inline editor is on, show the controls for it
    // ( fixing asyc load problem )
    if (lD.propertiesPanel.inlineEditor) {
      // Show inline editor controls
      this.showInlineEditor();
    }

    // Force scale region container
    // by updating region
    self.updateRegion(region);
  }.bind(this)).fail(function(res) {
    // Clear request var after response
    self.renderRequest = undefined;

    if (res.statusText != 'requestAborted') {
      toastr.error(errorMessagesTrans.previewFailed);
      $container.html(errorMessagesTrans.previewFailed);
    }
  });

  // Return request
  return this.renderRequest;
};

/** Render region with debounce */
Viewer.prototype.renderRegionDebounced = _.debounce(
  function(region, widgetToLoad = null) {
    lD.viewer.renderRegion(region, widgetToLoad);
  },
  500,
);

/**
 * Update element
 * @param {object} element
 */
Viewer.prototype.updateElement = _.throttle(function(
  element,
) {
  const $container = lD.viewer.DOMObject.find(`#${element.elementId}`);

  // Calculate scaled dimensions
  element.scaledDimensions = {
    height: element.height * lD.viewer.containerElementDimensions.scale,
    left: element.left * lD.viewer.containerElementDimensions.scale,
    top: element.top * lD.viewer.containerElementDimensions.scale,
    width: element.width * lD.viewer.containerElementDimensions.scale,
  };

  // Update element index
  $container.css({
    'z-index': element.layer,
  });

  // Update element content
  lD.viewer.renderElementContent(
    element,
  );
}, drawThrottle);

/**
 * Update element group
 * @param {object} elementGroup
 */
Viewer.prototype.updateElementGroup = _.throttle(function(
  elementGroup,
) {
  Object.values(elementGroup.elements).forEach((element) => {
    const $container = lD.viewer.DOMObject.find(`#${element.elementId}`);

    // Calculate scaled dimensions
    element.scaledDimensions = {
      height: element.height * lD.viewer.containerElementDimensions.scale,
      left: (element.left - elementGroup.left) *
        lD.viewer.containerElementDimensions.scale,
      top: (element.top - elementGroup.top) *
        lD.viewer.containerElementDimensions.scale,
      width: element.width * lD.viewer.containerElementDimensions.scale,
    };

    // Update element index
    $container.css({
      height: element.scaledDimensions.height,
      left: element.scaledDimensions.left,
      top: element.scaledDimensions.top,
      width: element.scaledDimensions.width,
    });

    // Update element content
    lD.viewer.renderElementContent(
      element,
    );
  });
}, drawThrottle);

/**
 * Update Region
 * @param {object} region - region object
 * @param {boolean} changed - if region was changed
 */
Viewer.prototype.updateRegion = _.throttle(function(
  region,
  changed = false,
) {
  const $container = lD.viewer.DOMObject.find(`#${region.id}`);

  // If drawer and has target region, set dimensions
  // to be the same as it
  if (
    region.isDrawer &&
    $container.data('targetRegionDimensions') != undefined
  ) {
    region.dimensions = $container.data('targetRegionDimensions');
    region.zIndex = $container.data('targetRegionzIndex');
  }

  // Calculate scaled dimensions
  region.scaledDimensions = {
    height: region.dimensions.height *
      lD.viewer.containerElementDimensions.scale,
    left: region.dimensions.left * lD.viewer.containerElementDimensions.scale,
    top: region.dimensions.top * lD.viewer.containerElementDimensions.scale,
    width: region.dimensions.width * lD.viewer.containerElementDimensions.scale,
  };

  // Update region container dimensions
  $container.css({
    height: region.scaledDimensions.height,
    left: region.scaledDimensions.left,
    top: region.scaledDimensions.top,
    width: region.scaledDimensions.width,
  });

  // Update z index if set
  if (region.zIndex != undefined) {
    $container.css('z-index', region.zIndex);
  }

  // Update region content
  lD.viewer.updateRegionContent(region, changed);
}, drawThrottle);


/**
 * Render canvas in the viewer
 * @param {object} canvas - canvas object
 */
Viewer.prototype.renderCanvas = function(
  canvas,
) {
  // Render widgets
  for (const widgetId in canvas.widgets) {
    if (canvas.widgets.hasOwnProperty(widgetId)) {
      const widget = canvas.widgets[widgetId];

      // Get elements from widget
      for (const elementId in widget.elements) {
        if (widget.elements.hasOwnProperty(elementId)) {
          const element = widget.elements[elementId];

          // Render element
          this.renderElement(element, canvas);
        }
      }
    }
  }
};

/**
 * Render element
 * @param {object} element - element object
 * @param {object} canvas - canvas object
 */
Viewer.prototype.renderElement = function(
  element,
  canvas,
) {
  const self = this;
  // Get canvas region container
  const $canvasRegionContainer = this.DOMObject.find(`#${canvas.id}`);

  // Scale element based on viewer scale
  const viewerScale = this.containerElementDimensions.scale;
  const elementRenderDimensions = {
    height: element.height * viewerScale,
    left: element.left * viewerScale,
    top: element.top * viewerScale,
    width: element.width * viewerScale,
    rotation: element.rotation,
    // If layer is negative, set it to 0
    layer: element.layer < 0 ? 0 : element.layer,
  };

  // If element belongs to a group, adjust top and left
  if (element.groupId) {
    elementRenderDimensions.left -=
      element.group.left * viewerScale;

    elementRenderDimensions.top -=
      element.group.top * viewerScale;
  }

  // Render element container
  const $newElement = $(viewerElementTemplate({
    element: element,
    dimensions: elementRenderDimensions,
  }));

  // If elements has a group, get group container
  let $groupContainer;
  if (element.groupId) {
    // Create group container if it doesn't exist
    if (
      $canvasRegionContainer.find(`#${element.groupId}`).length == 0
    ) {
      $canvasRegionContainer.append(
        viewerElementGroupTemplate({
          element: element,
          trans: viewerTrans,
        }),
      );
    }

    // Get group container
    $groupContainer = $canvasRegionContainer.find(
      `#${element.groupId}`,
    );

    // Get group object
    const group = lD.getElementByTypeAndId(
      'element-group',
      element.groupId,
      'widget_' + element.regionId + '_' + element.widgetId,
    );

    // If group is selected, add selected class
    if (group.selected) {
      this.selectElement($groupContainer);
    }

    // If group has source, add it to the container
    // or update it
    if (
      group.slot != undefined
    ) {
      const $slot = $groupContainer.find('.slot');
      if ($slot.length > 0) {
        $slot.find('span').html((Number(group.slot) + 1));
      } else {
        $groupContainer.append(
          '<div class="slot" title="' +
            propertiesPanelTrans.dataSlot +
          '">#' +
          '<span>' + (Number(group.slot) + 1) + '</span>' +
          '</div>');
      }
    }
  }

  // Append element html to the canvas region container
  // if it doesn't exist, otherwise replace it
  if ($canvasRegionContainer.find(`#${element.elementId}`).length) {
    $canvasRegionContainer.find(`#${element.elementId}`)
      .replaceWith($newElement);
  } else {
    // If element has group, append it to the group container
    if (element.groupId) {
      // Add element to group container
      $groupContainer.append($newElement);
    } else {
      // Otherwise append it to the canvas region container
      $canvasRegionContainer.append($newElement);
    }
  }

  // If we have a group container, set its dimensions
  if (element.groupId && $groupContainer) {
    // Set dimensions
    $groupContainer.css({
      position: 'absolute',
      height: element.group.height * viewerScale,
      left: element.group.left * viewerScale,
      top: element.group.top * viewerScale,
      width: element.group.width * viewerScale,
    });

    // Check if element ::after layer is equal or greater than group layer
    // If so, set group layer to element layer + 1
    if (
      $groupContainer.find('.group-select-overlay').css('z-index') == 'auto' ||
      element.layer >=
        Number($groupContainer.find('.group-select-overlay').css('z-index'))
    ) {
      $groupContainer.find('.group-select-overlay').css(
        'z-index',
        element.layer + 1,
      );
    }
  }

  // Render element content and handle interactions after
  this.renderElementContent(element, () => {
    // Handle viewer interactions
    self.handleInteractions();
  });
};

/**
 * Update element content
 * @param {Object} element
 * @param {Function} callback
 */
Viewer.prototype.renderElementContent = function(
  element,
  callback = null,
) {
  const self = this;
  // Get element container
  const $elementContainer = this.DOMObject.find(`#${element.elementId}`);

  const macroRegex = /^%(\+|\-)[0-9]([0-9])?(d|h|m|s)%$/gi;

  // TODO: Copied from player.js, to be added to a library so it can be reused
  const composeUTCDateFromMacro = (macroStr) => {
    const utcFormat = 'YYYY-MM-DDTHH:mm:ssZ';
    const dateNow = moment().utc();
    // Check if input has the correct format
    const dateStr = String(macroStr);

    if (dateStr.length === 0 ||
        dateStr.match(macroRegex) === null
    ) {
      return dateNow.format(utcFormat);
    }

    // Trim the macro date string
    const dateOffsetStr = dateStr.replaceAll('%', '');
    const params = (op) => dateOffsetStr.replace(op, '')
      .split(/(\d+)/).filter(Boolean);
    const addRegex = /^\+/g;
    const subtractRegex = /^\-/g;

    // Check if it's add or subtract offset and return composed date
    if (dateOffsetStr.match(addRegex) !== null) {
      return dateNow.add(...params(addRegex)).format(utcFormat);
    } else if (dateOffsetStr.match(subtractRegex) !== null) {
      return dateNow.subtract(...params(subtractRegex)).format(utcFormat);
    }
  };

  // Get element template ( most of the time
  // template will be already loaded/chached )
  element.getTemplate().then((template) => {
    // Create and render HBS template from template
    const stencil = template.stencil ?
      template.stencil : template.parent.stencil;
    let hbsTemplate = Handlebars.compile(
      (stencil?.hbs) ?
        stencil.hbs:
        '',
    );

    // If element dimensions are not set, set them
    // to the extended template, if it exists
    // or to hardcoded values
    if (!element.width || !element.height) {
      if (template.parent) {
        element.width = template.parent.startWidth;
        element.height = template.parent.startHeight;
      } else {
        element.width = 100;
        element.height = 100;
      }

      // Render element again
      self.renderElement(element, lD.layout.canvas);
      return;
    }

    // If we have slot, show it as a val+1
    if (element.slot != undefined) {
      element.slotView = Number(element.slot) + 1;
    }

    // If template can be rotated
    // set container and element to have that info
    if (template.canRotate) {
      element.canRotate = template.canRotate;
      $elementContainer.data('canRotate', element.canRotate);
    }

    // Render element with template
    $elementContainer.html($(viewerElementContentTemplate({
      element: element,
      template: template,
      scale: self.containerElementDimensions.scale,
      originalWidth: element.width,
      originalHeight: element.height,
      trans: propertiesPanelTrans,
    })));

    // Get element properties
    element.getProperties().then((properties) => {
      // Convert properties to object with id and value
      const convertedProperties = {};
      for (const key in properties) {
        if (properties.hasOwnProperty(key)) {
          const property = properties[key];

          // Convert checkbox values to boolean
          if (property.type === 'checkbox') {
            property.value = Boolean(Number(property.value));
          }

          // Add property to properties object
          convertedProperties[property.id] = (property.value == undefined) ?
            property.default : property.value;
        }
      }

      // Handle override property values
      if (
        stencil &&
        template.extends?.override &&
        template.extends?.with
      ) {
        // Compile template
        hbsTemplate = Handlebars.compile(stencil.hbs);
      }

      // Get element data from widget
      element.getData().then((elData) => {
        // Check all data elements and make replacements
        for (const key in elData) {
          if (elData.hasOwnProperty(key)) {
            const data = elData[key];

            // Check if data needs to be replaced
            if (String(data) && String(data).match(macroRegex) !== null) {
              // Replace macro with current date
              elData[key] = composeUTCDateFromMacro(data);
            }
          }
        }

        // Add widget data to properties
        convertedProperties.data = elData;

        // Send uniqueID
        convertedProperties.uniqueID = element.elementId;

        const extendOverrideKey = template?.extends?.override || null;
        const extendWithDataKey = template?.extends ?
          transformer.getExtendedDataKey(template.extends.with) : null;
        const elementParseDataFn = window[`onElementParseData_${element.id}`];
        const hasElementParseDataFn = typeof elementParseDataFn === 'function';
        const isInData = extendOverrideKey !== null &&
          elData && elData.hasOwnProperty(extendOverrideKey);

        if (extendWithDataKey !== null) {
          if (isInData) {
            convertedProperties[extendOverrideKey] = elData[extendOverrideKey];
          } else {
            convertedProperties[extendOverrideKey] = elData[extendWithDataKey];
          }
        }

        if (extendWithDataKey !== null) {
          if (template.onElementParseData && hasElementParseDataFn) {
            convertedProperties[extendOverrideKey] = elementParseDataFn(
              isInData ?
                elData[extendOverrideKey] :
                elData[extendWithDataKey],
            );
          }
        } else {
          if (template.onElementParseData && hasElementParseDataFn) {
            elementParseDataFn();
          }
        }

        // Compile hbs template with data
        let hbsHtml = hbsTemplate(convertedProperties);

        // Replace 123 with urls for [[assetID=123]] with asset url
        const assetRegex = /\[\[assetId=[\w&\-]+\]\]/gi;

        // Replace [[assetID=123]] with asset url
        hbsHtml.match(assetRegex)?.forEach((match) => {
          const assetId = match.split('[[assetId=')[1].split(']]')[0];
          const assetUrl = assetDownloadUrl.replace(':assetId', assetId);

          // Replace asset id with asset url
          hbsHtml = hbsHtml.replace(match, assetUrl);
        });

        // Append hbs html to the element
        $elementContainer.find('.element-content').html(hbsHtml);

        // Call on template render if it exists
        if (template.onTemplateRender) {
          const onTemplateRender =
            window['onTemplateRender_' + element.elementId];

          // Call on template render on element creation
          onTemplateRender && onTemplateRender(
            element.elementId,
            $elementContainer.find('.element-content'),
            [],
            convertedProperties,
          );
        }

        // Call callback if it exists
        if (callback) {
          callback();
        }
      });
    });
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
  const self = this;

  // Create moveable
  this.moveable = new Moveable(document.body, {
    draggable: true,
    resizable: true,
  });

  // Const save tranformation
  const saveTransformation = function(target) {
    // Apply transformation to the element
    const transformSplit = (target.style.transform).split(/[(),]+/);
    let hasTranslate = false;

    // If the transform has translate
    if (target.style.transform.search('translate') != -1) {
      target.style.left =
        `${parseFloat(target.style.left) + parseFloat(transformSplit[1])}px`;
      target.style.top =
        `${parseFloat(target.style.top) + parseFloat(transformSplit[2])}px`;

      hasTranslate = true;
    }

    // Reset transform
    if (target.style.transform.search('rotate') != -1) {
      const rotateValue = (hasTranslate) ?
        transformSplit[4] :
        transformSplit[1];

      target.style.transform = `rotate(${rotateValue})`;
    } else {
      target.style.transform = '';
    }

    // Return transform split
    return transformSplit;
  };

  /* draggable */
  this.moveable.on('drag', (e) => {
    // Margin to prevent dragging outside of the container
    const remainingMargin = 20;
    let elLeft = e.left;
    let elTop = e.top;

    // If dragged object is an element inside a group
    // use the group position to get the global position
    if ($(e.target).parent().hasClass('designer-element-group')) {
      const parentPos = $(e.target).parent().position();
      elLeft = parentPos.left + e.left;
      elTop = parentPos.top + e.top;
    }

    // Update horizontal position
    // if not outside of the container
    if (
      elLeft > -e.width + remainingMargin &&
      elLeft + remainingMargin < this.containerElementDimensions.width
    ) {
      e.target.style.left = `${e.left}px`;
    }

    // Update vertical position
    // if not outside of the container
    if (
      elTop > -e.height + remainingMargin &&
      elTop + remainingMargin < this.containerElementDimensions.height
    ) {
      e.target.style.top = `${e.top}px`;
    }
  }).on('dragEnd', (e) => {
    if (e.isDrag) {
      // Save transformation
      saveTransformation(e.target);

      // Save region properties
      (
        lD.selectedObject.type == 'region' ||
        lD.selectedObject.type == 'widget'
      ) &&
        self.saveRegionProperties(e.target, true, true, false);

      // Save element properties
      // if it's not a group
      (
        lD.selectedObject.type == 'element' &&
        !lD.selectedObject.groupId
      ) &&
        self.saveElementProperties(e.target, true);

      // Save element group properties
      (lD.selectedObject.type == 'element-group') &&
        self.saveElementGroupProperties(e.target);

      // Save element included in a group
      (
        lD.selectedObject.type == 'element' &&
        lD.selectedObject.groupId
      ) &&
      self.saveElementGroupProperties(
        $(e.target).parents('.designer-element-group'),
        true,
        false,
      );
    }
  });

  /* resizable */
  this.moveable.on('resize', (e) => {
    e.target.style.cssText += `width: ${e.width}px; height: ${e.height}px`;
    e.target.style.transform = e.drag.transform;

    // If selected object is a widget, get parent instead
    const selectedObject = (lD.selectedObject.type == 'widget') ?
      lD.selectedObject.parent : lD.selectedObject;

    // Update element dimension properties
    selectedObject.transform({
      width: parseFloat(e.width / self.containerElementDimensions.scale),
      height: parseFloat(e.height / self.containerElementDimensions.scale),
    }, false);

    // Update target object
    if (selectedObject.type == 'region') {
      // Update region
      self.updateRegion(selectedObject, true);
    } else if (selectedObject.type == 'element') {
      // Update element
      self.updateElement(selectedObject);
    } else if (selectedObject.type == 'element-group') {
      self.updateElementGroup(selectedObject);
    }
  }).on('resizeEnd', (e) => {
    // Save transformation
    transformSplit = saveTransformation(e.target);

    // Check if the region moved when resizing
    const moved = (
      parseFloat(transformSplit[1]) != 0 ||
      parseFloat(transformSplit[2]) != 0
    );

    // Save region properties
    (
      (lD.selectedObject.type == 'region') ||
      (lD.selectedObject.type == 'widget')
    ) &&
      self.saveRegionProperties(e.target, true, moved, true);

    // Save element properties
    (
      lD.selectedObject.type == 'element' &&
      !lD.selectedObject.groupId
    ) && self.saveElementProperties(e.target, moved);

    // Save element included in a group
    (
      lD.selectedObject.type == 'element' &&
      lD.selectedObject.groupId
    ) && self.saveElementGroupProperties(
      $(e.target).parents('.designer-element-group'),
      true,
      false,
    );

    // Save group
    (
      lD.selectedObject.type == 'element-group'
    ) && self.saveElementGroupProperties(
      e.target,
      false,
      true,
    );
  });

  /* rotatable */
  this.moveable.on('rotate', (e) => {
    e.target.style.transform = e.drag.transform;
  }).on('rotateEnd', (e) => {
    // Save transformation
    saveTransformation(e.target);

    // Save element properties
    if (
      lD.selectedObject.type == 'element' &&
      !lD.selectedObject.groupId
    ) {
      // Save element
      self.saveElementProperties(e.target);
    }

    // Save element included in a group
    (
      lD.selectedObject.type == 'element' &&
      lD.selectedObject.groupId
    ) && self.saveElementGroupProperties(
      $(e.target).parents('.designer-element-group'),
      true,
      false,
    );
  });

  // Update moveable options
  this.updateMoveableOptions({
    savePreferences: false,
  });
};

/**
 * Save the new position of the region
 * @param {object} region - Region object
 * @param {boolean} updateRegion - Update region rendering
 * @param {boolean} hasMoved - Has region moved
 * @param {boolean} hasScaled - Has region scaled
 */
Viewer.prototype.saveRegionProperties = function(
  region,
  updateRegion = true,
  hasMoved = false,
  hasScaled = false,
) {
  const self = this;
  const scale = self.containerElementDimensions.scale;
  const regionId = $(region).attr('id');
  const transform = {};
  const regionObject = lD.layout.regions[regionId];

  // Only change width/height if region has scaled
  if (hasScaled) {
    transform.width = parseFloat($(region).width() / scale);
    transform.height = parseFloat($(region).height() / scale);
  } else {
    transform.width = regionObject.dimensions.width;
    transform.height = regionObject.dimensions.height;
  }

  // Only change top/left if region has moved
  if (hasMoved) {
    transform.top = parseFloat($(region).position().top / scale);
    transform.left = parseFloat($(region).position().left / scale);
  } else {
    transform.top = regionObject.dimensions.top;
    transform.left = regionObject.dimensions.left;
  }

  // If we're saving the region, update it
  if (regionId == lD.selectedObject.id) {
    regionObject.transform(transform, false);

    if (typeof window.regionChangesForm === 'function') {
      window.regionChangesForm();

      // Save region form
      lD.propertiesPanel.saveRegion();
      (updateRegion) &&
        lD.viewer.updateRegion(regionObject);
    }
  } else if (regionId == lD.selectedObject.parent.id) {
    // If we're saving the region through the widget
    // update parent region and update the position values on the form
    regionObject.transform(transform, false);

    // Update position form values
    lD.propertiesPanel.updatePositionForm(transform);

    // Save region but just the position properties
    lD.propertiesPanel.saveRegion(true);
    (updateRegion) &&
      lD.viewer.updateRegion(regionObject);
  }
};

/**
 * Save element properties
 * @param {*} element - Element object
 * @param {*} hasMoved
 * @param {*} save
 */
Viewer.prototype.saveElementProperties = function(
  element,
  hasMoved = false,
  save = true,
) {
  const self = this;
  const scale = self.containerElementDimensions.scale;

  const $element = $(element);
  const elementId = $element.attr('id');
  const parentWidget = lD.getElementByTypeAndId(
    'widget',
    'widget_' + $element.data('regionId') + '_' + $element.data('widgetId'),
    'canvas',
  );

  const elementObject = parentWidget.elements[elementId];

  // Save dimensions
  elementObject.width = Math.round($element.width() / scale);
  elementObject.height = Math.round($element.height() / scale);

  // Save rotation
  if (
    $element[0].style.transform.search('rotate') >= 0
  ) {
    const transformSplit = $element[0].style.transform.split(/[(),]+/);
    const rotation = (transformSplit.length == 3) ?
      transformSplit[1] :
      transformSplit[4];

    elementObject.rotation = Number(rotation.split('deg')[0]);
  }

  // Only change top/left if element has moved
  if (hasMoved) {
    const topPosition = Number($element.css('top').split('px')[0]);
    const leftPosition = Number($element.css('left').split('px')[0]);

    elementObject.top = (elementObject.group && elementObject.group.top) ?
      Math.round(topPosition / scale) + elementObject.group.top :
      Math.round(topPosition / scale);
    elementObject.left = (elementObject.group && elementObject.group.left) ?
      Math.round(leftPosition / scale) + elementObject.group.left :
      Math.round(leftPosition / scale);
  }

  // If we're not saving through a group
  // Update position form values
  if (elementObject.selected) {
    lD.propertiesPanel.updatePositionForm({
      top: (elementObject.group) ?
        (elementObject.top - elementObject.group.top) : elementObject.top,
      left: (elementObject.group) ?
        (elementObject.left - elementObject.group.left) : elementObject.left,
      width: elementObject.width,
      height: elementObject.height,
      rotation: elementObject.rotation,
    });
  }

  // Save elements
  if (save) {
    parentWidget.saveElements();
  }
};

/**
 * Save element group properties and recalculate dimensions
 * @param {*} elementGroup
 * @param {boolean} [updateDimensions=false]
 * @param {boolean} [savingGroup=true] - if we are saving the group object
 */
Viewer.prototype.saveElementGroupProperties = function(
  elementGroup,
  updateDimensions = false,
  savingGroup = true,
) {
  const self = this;
  const scale = self.containerElementDimensions.scale;

  // Get group position
  const $elementGroup = $(elementGroup);
  const groupPosition = {
    top: $elementGroup.position().top,
    left: $elementGroup.position().left,
    width: $elementGroup.width(),
    height: $elementGroup.height(),
  };

  const groupObject = lD.getElementByTypeAndId(
    'element-group',
    $elementGroup.attr('id'),
    'widget_' + $elementGroup.data('regionId') + '_' +
      $elementGroup.data('widgetId'),
  );

  // Get group elements
  const $groupElements = $elementGroup.find('.designer-element');

  // Update group dimensions
  const updateOffset = {
    width: null,
    height: null,
    top: null,
    left: null,
  };

  if (updateDimensions) {
    // Update group dimensions based on elements
    $groupElements.each(function(_key, el) {
      const elementPosition = $(el).position();
      // First we need to find the top/left position
      // left needs to adjust to the elements more to the left of the group
      if (
        updateOffset.left === null ||
        elementPosition.left < updateOffset.left
      ) {
        updateOffset.left = elementPosition.left;
      }

      // top needs to adjust to the element more to the top
      if (
        updateOffset.top === null ||
        elementPosition.top < updateOffset.top
      ) {
        updateOffset.top = elementPosition.top;
      }
    });

    // Now we need to calculate the width and height
    $groupElements.each(function(_key, el) {
      const $element = $(el);
      const elementPosition = $element.position();
      let elWidth = $element.width();
      let elHeight = $element.height();

      // If the element has rotation, the dimensions need to
      // come from its bounding box ( for that we need to apply CSS rotation )
      const targetTransform = $element[0].style.transform;
      if (targetTransform.search('rotate') != -1) {
        const transformSplit = (targetTransform).split(/[(),]+/);
        const rotateValue = transformSplit[1];

        // Reset transform and give CSS rotation
        $element[0].style.transform = '';
        $element.css('rotate', rotateValue);

        // Assign bounding box values to width and height
        elWidth = $element[0].getBoundingClientRect().width;
        elHeight = $element[0].getBoundingClientRect().height;

        // Revert transform and CSS
        $element.css('rotate', '');
        $element[0].style.transform = targetTransform;
      }

      // Apply position offsets
      elementPosition.top -= updateOffset.top;
      elementPosition.left -= updateOffset.left;

      if (
        updateOffset.width === null ||
        elementPosition.left + elWidth >
        updateOffset.width
      ) {
        updateOffset.width = elementPosition.left + elWidth;
      }

      if (
        updateOffset.height === null ||
        elementPosition.top + elHeight >
        updateOffset.height
      ) {
        updateOffset.height = elementPosition.top + elHeight;
      }
    });

    // Update group element with offset
    groupPosition.top = groupPosition.top + updateOffset.top;
    groupPosition.left = groupPosition.left + updateOffset.left;
    groupPosition.width = updateOffset.width;
    groupPosition.height = updateOffset.height;

    // Also update CSS
    $elementGroup.css(groupPosition);
  }

  // Save scaled group dimensions to the object
  groupObject.top = Math.round(groupPosition.top / scale);
  groupObject.left = Math.round(groupPosition.left / scale);
  groupObject.width = Math.round(groupPosition.width / scale);
  groupObject.height = Math.round(groupPosition.height / scale);

  // Calculate group elements position, but only save on the last element
  $groupElements.each(function(_key, el) {
    // if we're updating the dimensions of the group
    // check if we have offset for position and apply that to all elements
    if (updateDimensions) {
      const topPosition = Number($(el).css('top').split('px')[0]);
      const leftPosition = Number($(el).css('left').split('px')[0]);
      $(el).css({
        top: topPosition - updateOffset.top,
        left: leftPosition - updateOffset.left,
      });
    }

    self.saveElementProperties(
      el,
      true,
      _key == $groupElements.length - 1,
    );
  });

  // Save position for the group object
  if (savingGroup) {
    // Update position form values
    lD.propertiesPanel.updatePositionForm({
      top: groupObject.top,
      left: groupObject.left,
      width: groupObject.width,
      height: groupObject.height,
    });
  }
};

/**
 * Select element
 * @param {object} element - Element object
 * @param {boolean} removeEditFromGroup
 */
Viewer.prototype.selectElement = function(
  element = null,
  removeEditFromGroup = true,
) {
  // Deselect all elements
  this.DOMObject.find('.selected').removeClass('selected');

  // Remove all editing from groups
  // if we're not selecting an element from that group
  if (
    removeEditFromGroup &&
  !(
    $(element).hasClass('designer-element') &&
    $(element).parent().hasClass('designer-element-group') &&
    $(element).parent().hasClass('editing')
  )) {
    this.DOMObject.find('.designer-element-group.editing')
      .removeClass('editing');
  }

  // Select element if exists
  if (element) {
    $(element).addClass('selected');
  }

  // Update moveable
  this.updateMoveable(true);
};

/**
 * Update moveable
 * @param {boolean} updateTarget
 */
Viewer.prototype.updateMoveable = function(
  updateTarget = false,
) {
  // On read only mode, don't update moveable
  if (this.parent.readOnlyMode) {
    return;
  }

  // Get selected element
  const $selectedElement = this.DOMObject.find('.selected');

  // Update moveable if we have a selected element, and is not a drawerWidget
  if (
    $selectedElement &&
    $.contains(document, $selectedElement[0]) &&
    !$selectedElement.hasClass('drawerWidget')
  ) {
    if ($selectedElement.hasClass('designer-element-group')) {
      this.moveable.dragTarget =
        $selectedElement.find('.group-select-overlay')[0];
    } else {
      this.moveable.dragTarget = undefined;
    }

    // Set rotatable
    if ($selectedElement.data('canRotate')) {
      this.moveable.rotatable = true;
      this.moveable.throttleRotate = 1;
    } else {
      this.moveable.rotatable = false;
    }

    // Update snap to elements targets
    if (
      updateTarget &&
      this.moveableOptions.snapToElements
    ) {
      const elementInGroup = $selectedElement.parent()
        .is('.designer-element-group');
      let $elementsToSnapTo;

      // If element is not in a group, match only with
      // elements outside of a group, groups and regions
      if (!elementInGroup) {
        // Get elements
        $elementsToSnapTo = this.DOMObject.find(
          '.designer-element-group:not(.selected)' +
          ',div:not(".designer-element-group") >' +
            '.designer-element:not(.selected)' +
          ',.designer-region:not(.selected)');
      } else {
        // If element is in a group, match with element
        // in the group and parent group
        $elementsToSnapTo = $.merge(
          $selectedElement.siblings('.designer-element:not(.selected)'),
          $selectedElement.parent('.designer-element-group:not(.selected)'),
        );
      }

      const elementsArray = [];
      Array.from($elementsToSnapTo).forEach(function(el) {
        elementsArray.push(el);
      });
      this.moveable.elementGuidelines = elementsArray;
    }

    // Update target only when needed
    if (updateTarget) {
      this.moveable.target = $selectedElement[0];

      // Show snap controls
      this.DOMObject.parent().find('.snap-controls').show();

      // Initialise tooltips
      this.parent.common.reloadTooltips(
        this.DOMObject.parent().find('.snap-controls'),
      );
    }

    // Always update the moveable area
    this.moveable.updateRect();
  } else {
    this.moveable.target = null;

    // Hide snap controls
    this.DOMObject.parent().find('.snap-controls').hide();
  }
};

/**
 * Update moveable interface
 */
Viewer.prototype.updateMoveableUI = function() {
  const $snapControls = this.DOMObject.parent().find('.snap-controls');

  // Snap to grid value
  const $gridValue = $snapControls.find('.snap-to-grid-value');
  if (!this.moveableOptions.snapToGrid) {
    // Hide number input
    $gridValue.hide();
  } else {
    // Show number input
    $gridValue.show();

    // Set snap to grid gap
    $gridValue.val(this.moveableOptions.snapGridGap);
  }

  // Snap to grid
  $snapControls.find('#snapToGrid').toggleClass(
    'active',
    this.moveableOptions.snapToGrid,
  );

  // Snap to borders
  $snapControls.find('#snapToBorders').toggleClass(
    'active',
    this.moveableOptions.snapToBorders,
  );

  // Snap to elements
  $snapControls.find('#snapToElements').toggleClass(
    'active',
    this.moveableOptions.snapToElements,
  );
};

/**
 * Update moveable options
 * @param {object} [options] - options
 * @param {boolean=} [options.snapToGrid] - Snap to grid lines
 * @param {boolean=} [options.snapGridGap]
 *  - Snap to grid distance between grid lines
 * @param {boolean=} [options.snapToBorders] - Snap to layout borders
 * @param {boolean=} [options.snapToElements] - Snap to other elements
 * @param {boolean=} [options.savePreferences=true] - Save preferences
 */
Viewer.prototype.updateMoveableOptions = function({
  snapToGrid,
  snapGridGap,
  snapToBorders,
  snapToElements,
  savePreferences = true,
} = {}) {
  const snapThreshold = 5;

  // Snap to grid
  (snapToGrid) &&
    (this.moveableOptions.snapToGrid = snapToGrid);

  // Update grid gap
  (snapGridGap) &&
    (this.moveableOptions.snapGridGap = snapGridGap);

  // Snap to borders
  (snapToBorders) &&
    (this.moveableOptions.snapToBorders = snapToBorders);

  // Snap to elements
  (snapToElements) &&
    (this.moveableOptions.snapToElements = snapToElements);

  // Container scale
  const scale = (this.containerElementDimensions) ?
    this.containerElementDimensions.scale : 1;
  const containerWidth = (this.containerElementDimensions) ?
    this.containerElementDimensions.width : lD.layout.width;
  const containerHeight = (this.containerElementDimensions) ?
    this.containerElementDimensions.height : lD.layout.height;

  // Toggle snap
  if (
    this.moveableOptions.snapToGrid ||
    this.moveableOptions.snapToBorders ||
    this.moveableOptions.snapToElements
  ) {
    this.moveable.snappable = true;
    this.moveable.snapThreshold = snapThreshold;
    this.moveable.snapContainer =
      this.DOMObject.find('.viewer-element.layout')[0];

    this.moveable.snapDirections = {
      top: true,
      left: true,
      bottom: true,
      right: true,
      center: (this.moveableOptions.snapToGrid) ? false : true,
      middle: (this.moveableOptions.snapToGrid) ? false : true,
    };

    this.moveable.elementSnapDirections = {
      top: true,
      left: true,
      bottom: true,
      right: true,
      center: true,
      middle: true,
    };

    this.moveable.snapDistFormat = function(v) {
      return `${Math.round(v/scale)}px`;
    };

    // Snap to middle points
    this.moveable.horizontalGuidelines =
      [{
        pos: containerHeight/2,
        className: 'red',
      }];
    this.moveable.verticalGuidelines =
      [{
        pos: containerWidth/2,
        className: 'red',
      }];
  } else {
    this.moveable.snappable = false;
    this.moveable.snapDirections = null;
    this.moveable.elementSnapDirections = null;
    this.moveable.horizontalGuidelines = [];
    this.moveable.verticalGuidelines = [];
  }

  // Grid snap
  if (this.moveableOptions.snapToGrid) {
    const gridGap = this.moveableOptions.snapGridGap * scale;

    this.moveable.snapGridWidth = gridGap;
    this.moveable.snapGridHeight = gridGap;
    this.moveable.isDisplayGridGuidelines = true;
    this.moveable.horizontalGuidelines = [];
    this.moveable.verticalGuidelines = [];
  } else {
    this.moveable.snapGridWidth = null;
    this.moveable.snapGridHeight = null;
    this.moveable.isDisplayGridGuidelines = false;
  }

  // Border snap
  if (this.moveableOptions.snapToBorders) {
    this.moveable.bounds = {
      left: 0,
      right: containerWidth,
      top: 0,
      bottom: containerHeight,
    };
  } else {
    this.moveable.bounds = null;
  }

  // Snap to elements
  if (this.moveableOptions.snapToElements) {
    // Get elements
    const $elementsToSnapTo =
      this.DOMObject.find(
        '.designer-element:not(.selected), ' +
        '.designer-region:not(.selected), ' +
        '.designer-element-group:not(.selected) ',
      );
    const elementsArray = [];
    Array.from($elementsToSnapTo).forEach(function(el) {
      elementsArray.push(el);
    });

    this.moveable.elementGuidelines = elementsArray;
  } else {
    // Clear guidelines
    this.moveable.elementGuidelines = [];
  }

  // Save snap preferences
  if (savePreferences) {
    this.parent.savePrefs();
  }
};

/**
 * Update region content
 * @param {object} region - Region object
 * @param {boolean} changed - Has region changed
 */
Viewer.prototype.updateRegionContent = function(
  region,
  changed = false,
) {
  const $container = this.DOMObject.find(`#${region.id}`);

  // Update iframe
  const updateIframe = function($iframe) {
    $iframe.css({
      width: region.scaledDimensions.width,
      height: region.scaledDimensions.height,
    });

    // Options for the message
    const options = {
      id: region.id,
      originalWidth: region.dimensions.width,
      originalHeight: region.dimensions.height,
    };

    // Check if it's the first call
    // If it is, send a flag to pause effects on start
    if (!$iframe.data('firstCall')) {
      $iframe.data('firstCall', true);
      options.pauseEffectOnStart = true;
    }

    // We need to recalculate the scale inside of the iframe
    $iframe[0].contentWindow
      .postMessage({
        method: 'renderContent',
        options: options,
      }, '*');
  };

  // Get iframe
  const $iframe = $container.find('iframe');

  // Check if iframe exists, and is loaded
  if ($iframe.length) {
    // If iframe globalOptions are not loaded
    // wait for the iframe to load
    if (!$iframe[0].contentWindow.window.globalOptions) {
      // Wait for the iframe to load and update it
      $iframe[0].onload = function() {
        updateIframe($iframe);
      };
    } else {
      // Update iframe
      updateIframe($iframe);
    }
  }

  // Process image
  const $imageContainer = $container
    .find('[data-type="widget_image"], [data-type="widget_video"]');
  if ($imageContainer.length) {
    const $image = $imageContainer.find('img');
    const $imageParent = $image.parent();
    const $imageParentContainer = $image.parents('.img-container');
    const urlSplit = $image.attr('src').split('&proportional=');

    // If the URL is not parsed
    if (urlSplit.length > 1) {
      // Get image properties ( [proportional, fit])
      // Stretch/fill [0,0]
      // Centre/contain [1,0]
      // Fit/cover [1,1]
      const imgValues = urlSplit[1].split('&fit=');
      const objectFit = [
        ['fill', 'none'],
        ['contain', 'cover'],
      ];

      // Get object fit value
      const currentObjectFit = objectFit[imgValues[0]][imgValues[1]];

      // Get object position value
      // if center/contain, get values
      // if others, remove object position value
      const objectPosition = (currentObjectFit === 'contain') ?
        (
          $imageParent.css('text-align') +
          ' ' +
          $imageParent.css('vertical-align')
        ) :
        '';

      // Remove style properties in image's parent
      // They will be applied in the image itself
      $imageParent.css({
        'text-align': '',
        'vertical-align': '',
      });

      // Change only onload
      $image.on('load', () => {
        // Update image fit
        $image.css({
          'object-fit': currentObjectFit,
          'object-position': objectPosition,
        });
      });

      // Change image url to a non styled one
      // Which triggers the onload event
      $image.attr('src', urlSplit[0]);
    }

    // Update image container height
    $imageParentContainer.css({
      height: region.scaledDimensions.height,
    });

    // Update image dimensions
    $image.css({
      width: region.scaledDimensions.width,
      height: region.scaledDimensions.height,
    });

    // If dimensions changed, render region
    if (changed) {
      this.renderRegionDebounced(region);
    }
  }
};

/**
 * Highlight action targets on viewer
 * @param {string} actionData - Action data
 * @param {string} level - Highlight level (0 - Light, 1- Heavy)
 */
Viewer.prototype.createActionHighlights = function(actionData, level) {
  const self = this;

  const typeSelectorMap = {
    region: '.designer-region:not(.designer-region-playlist)',
    widget: '.designer-region:not(.designer-region-playlist) .designer-widget',
    layout: '.viewer-element.layout',
  };

  // Clear previous highlights
  this.clearActionHighlights();

  const highlightElement = function(elementType, elementId, highlightType) {
    // Find element on viewer
    const $viewerElement = self.DOMObject.find(
      typeSelectorMap[elementType] +
      '[data-' + elementType + '-id="' + elementId + '"]',
    );

    // Add highlight class
    $viewerElement.addClass(
      `action-highlight action-highlight-${highlightType} highlight-${level}`,
    );
  };

  // Get target if exists
  if (actionData.target) {
    // If target is "screen", use "layout" to highlight
    const targetType = (actionData.target === 'screen') ?
      'layout' :
      actionData.target;

    highlightElement(targetType, actionData.targetId, 'target');
  }

  // Get trigger if exists
  if (actionData.source) {
    highlightElement(actionData.source, actionData.sourceId, 'trigger');
  }
};

/**
 * Clear action highlights on viewer
 */
Viewer.prototype.clearActionHighlights = function() {
  this.DOMObject.find('.action-highlight').removeClass(
    'action-highlight action-highlight-target ' +
    'action-highlight-trigger highlight-0 highlight-1',
  );
};

/**
 * Add new widget action element
 * @param {object} actionData - Action data
 * @param {string} createOrEdit - Create or edit action
 */
Viewer.prototype.addActionEditArea = function(
  actionData,
  createOrEdit,
) {
  const self = this;
  const $layoutContainer = this.DOMObject.find('.viewer-element.layout');
  const createOverlayArea = function(type) {
    // If target is "screen", use "layout" to highlight
    const targetType =
      (actionData.target === 'screen') ? 'layout' : actionData.target;

    // Create overlay area
    const $actionArea = $(viewerActionEditRegionTemplate({
      drawer: lD.layout.drawer,
      target: targetType,
      type: type,
    }));

    // Set background to be the same as the layout
    $actionArea.css({
      'background-color': lD.layout.backgroundColor,
    });

    // Get target
    const $targetRegion = self.parent.getElementByTypeAndId(
      targetType,
      targetType + '_' + actionData.targetId,
    );

    // Update drawer dimensions to match target
    if (targetType === 'region') {
      lD.layout.drawer.dimensions = $targetRegion.dimensions;
      // Set the z index of the drawer to be over the target region
      lD.layout.drawer.zIndex = $targetRegion.zIndex + 1;
    } else if (targetType === 'layout') {
      lD.layout.drawer.dimensions = {
        width: lD.layout.width,
        height: lD.layout.height,
        top: 0,
        left: 0,
      };
      // Set the z index to 0
      lD.layout.drawer.zIndex = 0;
    }

    // Save dimensions to the DOM object
    $actionArea.data('targetRegionDimensions', lD.layout.drawer.dimensions);
    $actionArea.data('targetRegionzIndex', lD.layout.drawer.zIndex);

    // Add after region or
    // if layout, add inside regions in the layout as first element
    if (targetType === 'region') {
      $actionArea.insertAfter(self.DOMObject.find('#' + $targetRegion.id));
    } else if (targetType === 'layout') {
      $layoutContainer.find('#regions').prepend($actionArea);
    }

    return $actionArea;
  };

  // Remove previous action create/edit area
  this.removeActionEditArea();

  if (createOrEdit === 'create') {
    // Create add action area
    const $actionArea = createOverlayArea('create');

    // Add label to action area
    $actionArea.html('<div class="action-label">' +
      viewerTrans.addWidget +
      '</div>');

    // Click to add widget
    $actionArea.on('click', () => {
      lD.selectObject({
        target: $actionArea,
      });
    });

    // Create droppable area
    $actionArea.droppable({
      greedy: true,
      tolerance: 'pointer',
      accept: function(draggable) {
        // Check target
        return lD.common.hasTarget(draggable, 'drawer');
      },
      drop: _.debounce(function(event, ui) {
        lD.dropItemAdd(event.target, ui.draggable[0]);
      }, 200),
    });
  } else if (createOrEdit === 'edit') {
    // Create action edit area
    const $actionArea = createOverlayArea('edit');

    // Get widget from drawer be loaded
    const widgetToRender = lD.getElementByTypeAndId(
      'widget',
      'widget_' + lD.layout.drawer.regionId + '_' + actionData.widgetId,
      'drawer',
    );

    // Render region with widget
    this.renderRegion(
      lD.layout.drawer,
      widgetToRender,
      (actionData.target === 'layout'),
    );

    // Edit on click
    $actionArea.on('click', () => {
      // Open widget edit form
      lD.editDrawerWidget(
        actionData,
      );
    });
  }

  // Update viewer
  this.update();
};

/**
 * Remove new widget action element
 */
Viewer.prototype.removeActionEditArea = function() {
  this.DOMObject.find('.designer-region-drawer').remove();
};

/**
 * Save temporary object
 * @param {string} objectId - Object ID
 * @param {string} objectType - Object type
 * @param {object} data - Object data
 */
Viewer.prototype.saveTemporaryObject = function(objectId, objectType, data) {
  lD.selectedObject.id = objectId;
  lD.selectedObject.type = objectType;

  // Append temporary object to the viewer
  $('<div>', {
    id: objectId,
    data: data,
  }).appendTo(this.DOMObject);
};

Viewer.prototype.editGroup = function(
  groupDOMObject,
  elementToSelectOnLoad = null,
) {
  const self = this;
  const editing = $(groupDOMObject).hasClass('editing');

  // Deselect all elements or select specific element
  if (elementToSelectOnLoad) {
    lD.selectObject({
      target: $('#' + elementToSelectOnLoad),
      forceSelect: true,
      refreshEditor: false,
      reloadPropertiesPanel: false,
    });
    self.selectElement('#' + elementToSelectOnLoad);
  } else {
    lD.selectObject();
    self.selectElement();
  }

  // Only add editing class if we were not
  if (!editing) {
    $(groupDOMObject).addClass('editing');

    // Add overlay and click to close
    self.DOMObject.siblings('.viewer-overlay').show()
      .off().on('click', () => {
        self.editGroup(groupDOMObject);
      });

    // Bump z-index to show over overlay
    $(groupDOMObject).css('z-index', 2);

    // Give group the same background as the layout's
    $(groupDOMObject).css('background-color',
      self.DOMObject.find('> .layout').css('background-color'));
  } else {
    // Hide overlay
    self.DOMObject.siblings('.viewer-overlay').hide();

    // Set z-index to auto
    $(groupDOMObject).css('z-index', '');

    // Remove background color
    $(groupDOMObject).css('background-color', '');
  }
};

module.exports = Viewer;
