// TIMELINE Module

// Load templates
const timelineTemplate = require('../templates/playlist-timeline.hbs');
const timelineInfoTemplate = require('../templates/playlist-timeline-info.hbs');
const timelineHeaderInfoTemplate =
  require('../templates/playlist-timeline-header-info.hbs');

const defaultStepHeight = 4;
const minStepHeight = 2;
const maxStepHeight = 90;
const widgetMinHeight = 50;
const widgetDefaultHeight = 90;

/**
 * Timeline contructor
 * @param {object} container - the container to render the timeline to
 * @param {object =} [options] - Timeline options
 */
const PlaylistTimeline = function(container) {
  this.DOMObject = container;

  // Set step height
  this.stepHeight = defaultStepHeight;

  // Set total height
  this.totalTimelineHeight = 0;

  // Timeline mode
  this.scaledTimeline = false;
};

/**
 * Render Timeline and the layout
 */
PlaylistTimeline.prototype.render = function() {
  // Render timeline template
  const html = timelineTemplate(
    $.extend({}, pE.playlist, {trans: editorsTrans}),
  );

  // Append html to the main div
  this.DOMObject.html(html);

  // Calculate widget heights
  this.calculateWidgetHeights();

  // Create grid
  this.createGrid();

  // Update info
  this.updateInfo();

  // Enable select for each widget
  this.DOMObject.find('.playlist-widget.selectable').on('click', function(e) {
    e.stopPropagation();
    if (!$(e.currentTarget).hasClass('to-be-saved')) {
      pE.selectObject({target: $(e.currentTarget)});
    }
  });

  this.DOMObject.find('.timeline-overlay-step').droppable({
    greedy: true,
    tolerance: 'pointer',
    accept: (draggable) => {
      // Check target
      return pE.common.hasTarget(draggable, 'playlist');
    },
    drop: function(event, ui) {
      const position = parseInt($(event.target).data('position')) + 1;

      pE.playlist.addObject(event.target, ui.draggable[0], position);
    },
  });

  this.DOMObject.find('.timeline-overlay-step').on('click', function(e) {
    if (
      !$.isEmptyObject(pE.toolbar.selectedCard) ||
      !$.isEmptyObject(pE.toolbar.selectedQueue)
    ) {
      e.stopPropagation();
      const position = parseInt($(e.target).data('position')) + 1;

      pE.selectObject({
        target: $(e.target).parents('#playlist-timeline'),
        reloadViewer: true,
        positionToAdd: position,
      });
    }
  });

  this.DOMObject.find('.playlist-widget').droppable({
    greedy: true,
    tolerance: 'pointer',
    accept: function(el) {
      return (
        $(this).hasClass('editable') &&
        $(el).attr('drop-to') === 'widget'
      ) ||
      (
        $(this).hasClass('permissionsModifiable') &&
        $(el).attr('drop-to') === 'all' &&
        $(el).data('subType') === 'permissions'
      );
    },
    drop: function(event, ui) {
      pE.playlist.addObject(event.target, ui.draggable[0]);
    },
  });

  // Handle widget attached audio click
  this.DOMObject.find(
    '.playlist-widget.editable .editProperty',
  ).on('click', function(e) {
    e.stopPropagation();

    const widget =
      pE.getObjectByTypeAndId(
        $(e.target).parents('.playlist-widget').data('type'),
        $(e.target).parents('.playlist-widget').attr('id'),
        $(e.target).parents('.playlist-widget').data('widgetRegion'),
      );

    widget.editPropertyForm(
      $(e.target).data('property'),
      $(e.target).data('propertyType'),
    );
  });

  this.DOMObject.find('.playlist-widget').contextmenu(function(ev) {
    if (
      $(ev.currentTarget).is('.editable, .deletable, .permissionsModifiable')
    ) {
      // Open context menu
      pE.openContextMenu(ev.currentTarget, {
        x: ev.pageX,
        y: ev.pageY,
      });
    }

    // Prevent browser menu to open
    return false;
  });

  // Save order function with debounce
  const saveOrderFunc = _.debounce(function() {
    // Check if editor container is
    // an empty object, if so, cancel
    if ($.isEmptyObject(pE.editorContainer)) {
      return;
    }

    pE.saveOrder();
    pE.timeline.DOMObject.find('#unsaved').hide();
    pE.timeline.DOMObject.find('#saved').show();
  }, 1000);

  // Sortable widgets
  this.DOMObject.find('#timeline-container').sortable({
    // Disabled if we're inline with a non-external playlist
    // and on read only mode
    disabled: (
      pE.inline === true &&
      pE.externalPlaylist === false &&
      typeof lD != undefined &&
      lD.readOnlyMode === true
    ),
    axis: 'y',
    items: '.playlist-widget',
    start: function(event, ui) {
      pE.timeline.DOMObject.find('#unsaved').hide();
      saveOrderFunc.cancel();
      pE.clearTemporaryData();
    },
    stop: function(event, ui) {
      // Mark target as "to be saved"
      $(ui.item).addClass('to-be-saved');

      // Re-render grid after drag to calculate scale values
      if (!pE.timeline.scaledTimeline) {
        pE.timeline.createGrid();
      }

      pE.timeline.DOMObject.find('#unsaved').show();

      // Save order
      saveOrderFunc();
    },
  });

  // Reload tooltips
  pE.common.reloadTooltips(pE.timeline.DOMObject);
};

/**
 * Create grid
 */
PlaylistTimeline.prototype.createGrid = function() {
  const $stepWithValue =
    $(`<div class="time-grid-step-with-value time-grid-step">
      <div class="step-value"></div>
    </div>`);
  const $step =
    $('<div class="time-grid-step"></div>');
  const $timeGrid = this.DOMObject.siblings('.time-grid');

  // Empty grid container
  $timeGrid.empty();

  // If we are in scaled mode
  if (this.scaledTimeline) {
    // Add steps until we fill the timeline
    // or we reach the number of items
    const timelineHeight = $timeGrid.parents('.editor-body').height() - 20;
    const targetHeight = (timelineHeight > this.totalTimelineHeight) ?
      timelineHeight : (this.totalTimelineHeight + this.stepHeight * 2);
    let step = 0;
    let stepDelta = 1;
    let stepLabelDelta = 0;

    // Calculate step show and label delta
    if (this.stepHeight > 30) {
      stepDelta = 1;
      stepLabelDelta = 1;
    } else if (this.stepHeight > 15) {
      stepDelta = 1;
      stepLabelDelta = 2;
    } else if (this.stepHeight >= 7) {
      stepDelta = 2;
      stepLabelDelta = 4;
    } else if (this.stepHeight >= 3) {
      stepDelta = 5;
      stepLabelDelta = 10;
    } else if (this.stepHeight >= 2) {
      stepDelta = 5;
      stepLabelDelta = 20;
    } else {
      stepDelta = 10;
      stepLabelDelta = 30;
    }

    // Set grid container gap to height minus step height (2px)
    const calculatedGap = (stepDelta * this.stepHeight) - 2;
    $timeGrid.css('gap', calculatedGap + 'px');

    for (
      let auxHeight = targetHeight;
      auxHeight > 0;
      auxHeight -= (stepDelta * this.stepHeight)
    ) {
      if ( step % stepDelta === 0 ) {
        if (step % stepLabelDelta === 0) {
          // Format date on step
          const stepFormatted = pE.common.timeFormat(step);
          // Add a labelled step
          $stepWithValue.find('.step-value').text(stepFormatted);
          $timeGrid.append($stepWithValue.clone());
        } else {
          // Add a normal step
          $timeGrid.append($step.clone());
        }
      }

      // Increment step
      step += stepDelta;
    }
  } else {
    // Create first step
    $stepWithValue.find('.step-value').text(
      pE.common.timeFormat(0),
    );
    $timeGrid.append($stepWithValue.clone());

    let durationSum = 0;

    // Create a labelled value for each widget in the timeline
    this.DOMObject.find('.playlist-widget').each(function(_idx, el) {
      const $widget = $(el);
      const duration = $widget.data('duration');

      // Format date on step and add to sum
      durationSum += duration;
      const durationFormatted = pE.common.timeFormat(
        durationSum,
      );

      // Add a labelled step
      $stepWithValue.find('.step-value').text(durationFormatted);
      $timeGrid.append($stepWithValue.clone());
    });

    // Timeline fixed gap minus step height (2px)
    $timeGrid.css('gap', (widgetDefaultHeight - 2) + 'px');
  }
};

/**
 * Calculate widget heights
 */
PlaylistTimeline.prototype.calculateWidgetHeights = function() {
  const self = this;

  // If we are in scaled mode
  if (this.scaledTimeline) {
    // Reset total height
    self.totalTimelineHeight = 0;

    // Calculate widget heights
    this.DOMObject.find('.playlist-widget').each(function(_idx, el) {
      const $widget = $(el);
      const duration = $widget.data('duration');

      // Calculate height
      const height = duration * self.stepHeight;

      // If height is less than minimum, show replacement
      if (height < widgetMinHeight) {
        $widget.addClass('minimal-widget');
      }

      // Set height
      $widget.css('height', height + 'px');

      // Give same height to the dropdown step
      self.DOMObject.find(`.timeline-overlay-dummy[data-position=${_idx}]`)
        .css('height', height + 'px');

      self.totalTimelineHeight += height;
    });
  } else {
    // All widgets have default height
    this.DOMObject.find('.playlist-widget')
      .css('height', widgetDefaultHeight + 'px');

    // Give same height to the dropdown step
    this.DOMObject.find(`.timeline-overlay-dummy`)
      .css('height', widgetDefaultHeight + 'px');

    // Calculate timeline height
    self.totalTimelineHeight =
      this.DOMObject.find('.playlist-widget').length *
      widgetDefaultHeight;
  }
};

/**
 * Change playlist zoom level
 * @param {number} zoomLevelChange
 */
PlaylistTimeline.prototype.changeZoomLevel = function(zoomLevelChange) {
  let zoomLevelChangeStep = 2;

  // If we're working with higher zooms, increase step change
  if (this.stepHeight >= 30) {
    zoomLevelChangeStep = 20;
  } else if (this.stepHeight >= 10) {
    zoomLevelChangeStep = 4;
  } else {
    zoomLevelChangeStep = 2;
  }

  // Calculate new zoom level
  // If zoomLevelChange is 0, it means we are resetting the zoom level
  this.stepHeight =
    (zoomLevelChange === 0) ?
      defaultStepHeight :
      this.stepHeight + zoomLevelChange * zoomLevelChangeStep;

  // Clamp zoom level between min and max
  this.stepHeight =
    Math.min(Math.max(this.stepHeight, minStepHeight), maxStepHeight);

  // Render timeline
  this.render();
};

/**
 * Update information about the current playlist
 */
PlaylistTimeline.prototype.updateInfo = function() {
  // Format playlist duration
  pE.playlist.durationFormatted = pE.common.timeFormat(pE.playlist.duration);

  // Render timeline template
  const html = timelineInfoTemplate(
    $.extend({}, {
      playlist: pE.playlist,
      widget: pE.selectedObject,
    }, {trans: toolbarTrans}),
  );
  const widgets = pE.playlist?.widgets || {};
  const headerHtml = timelineHeaderInfoTemplate(
    $.extend({}, {
      playlist: pE.playlist,
      widgetsCount: Object.keys(widgets).length,
    }, {trans: {
      ...playlistEditorTrans,
      editPlaylistTitle: playlistEditorTrans.editPlaylistTitle.replace(
        '%playlistName%',
        pE.playlist.name,
      ),
    }}),
  );

  // Inject HTML into container
  this.DOMObject.parents('#playlist-editor')
    .find('.selected-info').html(html);
  this.DOMObject.parents('.editor-modal')
    .find('.modal-header--left').html(headerHtml);
};

/**
 * Switch timeline scale mode
 * @param {boolean} force
 */
PlaylistTimeline.prototype.switchScaleMode = function(force) {
  // Switch flag
  this.scaledTimeline = (force) ? force: !this.scaledTimeline;

  // If switched on, show scale controls
  pE.editorContainer.toggleClass('timeline-scaled', this.scaledTimeline);

  // Render again
  this.render();
};

module.exports = PlaylistTimeline;
