// TIMELINE Module

// Load templates
const timelineTemplate = require('../templates/playlist-timeline.hbs');

/**
 * Timeline contructor
 * @param {object} container - the container to render the timeline to
 * @param {object =} [options] - Timeline options
 */
let PlaylistTimeline = function(container) {
    this.DOMObject = container;
};

/**
 * Render Timeline and the layout
 * @param {Object} layout - the layout object to be rendered
 */
PlaylistTimeline.prototype.render = function(layout) {

    // Render timeline template
    const html = timelineTemplate(pE.playlist);

    // Append html to the main div
    this.DOMObject.html(html);

    // Enable select for each widget
    this.DOMObject.find('.playlist-widget.selectable:not(.ui-sortable)').click(function(e) {
        e.stopPropagation();
        pE.selectObject($(this));
    });

    this.DOMObject.find('.playlist-widget').droppable({
        accept: '[drop-to="widget"]',
        drop: function(event, ui) {
            pE.playlist.addElement(event.target, ui.draggable[0]);
        }
    });

    // Handle widget attached audio click
    this.DOMObject.find('.playlist-widget .editProperty').click(function(e) {
        e.stopPropagation();
        const widget = pE.getElementByTypeAndId($(this).parent().data('type'), $(this).parent().attr('id'), $(this).parent().data('widgetRegion'));

        widget.editPropertyForm($(this).data('property'), $(this).data('propertyType'));
    });

    // Sortable widgets
    this.DOMObject.find('#timeline-container').sortable();
};

module.exports = PlaylistTimeline;
