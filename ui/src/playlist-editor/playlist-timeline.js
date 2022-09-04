// TIMELINE Module

// Load templates
const timelineTemplate = require('../templates/playlist-timeline.hbs');

/**
 * Timeline contructor
 * @param {object} container - the container to render the timeline to
 * @param {object =} [options] - Timeline options
 */
const PlaylistTimeline = function(container) {
  this.DOMObject = container;
};

/**
 * Render Timeline and the layout
 */
PlaylistTimeline.prototype.render = function() {
  // Render timeline template
  const html = timelineTemplate(
    $.extend({}, pE.playlist, {trans: editorsTrans}),
  );

  // Create grid
  this.createGrid();

  // Append html to the main div
  this.DOMObject.html(html);

  // Enable select for each widget
  this.DOMObject.find('.playlist-widget.selectable').click(function(e) {
    e.stopPropagation();
    if (!$(e.currentTarget).hasClass('to-be-saved')) {
      pE.selectObject($(e.currentTarget));
    }
  });

  this.DOMObject.find('.timeline-overlay-step').droppable({
    greedy: true,
    tolerance: 'pointer',
    accept: '[drop-to="region"]',
    drop: function(event, ui) {
      const position = parseInt($(event.target).data('position')) + 1;

      pE.playlist.addElement(event.target, ui.draggable[0], position);
    },
  });

  this.DOMObject.find('.timeline-overlay-step').click(function(e) {
    if (
      !$.isEmptyObject(pE.toolbar.selectedCard) ||
      !$.isEmptyObject(pE.toolbar.selectedQueue)
    ) {
      e.stopPropagation();
      const position = parseInt($(e.target).data('position')) + 1;

      pE.selectObject(
        $(e.target).parents('#playlist-timeline'),
        false,
        {positionToAdd: position},
      );
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
      pE.playlist.addElement(event.target, ui.draggable[0]);
    },
  });

  // Handle widget attached audio click
  this.DOMObject.find(
    '.playlist-widget.editable .editProperty',
  ).click(function(e) {
    e.stopPropagation();

    const widget =
      pE.getElementByTypeAndId(
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
    pE.saveOrder();
    pE.timeline.DOMObject.find('#unsaved').hide();
    pE.timeline.DOMObject.find('#saved').show();
  }, 1000);

  // Sortable widgets
  this.DOMObject.find('#timeline-container').sortable({
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

      pE.timeline.DOMObject.find('#unsaved').show();
      saveOrderFunc();
    },
  });
};

/**
 * Create grid
 */
PlaylistTimeline.prototype.createGrid = function() {
  return;
  // TODO This is just a sample grig, it should be replaced with a real one
  const $stepEven =
    $(`<div class="time-grid-step-with-value time-grid-step">
      <div class="step-value"></div>
    </div>`);
  const $stepOdd =
    $('<div class="time-grid-step"></div>');

  // Add 20 steps
  for (let i = 0; i < 30; i++) {
    if (i % 2 === 0) {
      $stepOdd.clone().appendTo('.time-grid');
    } else {
      $stepEven.find('.step-value').text(i);
      $stepEven.clone().appendTo('.time-grid');
    }
  }
};

module.exports = PlaylistTimeline;
