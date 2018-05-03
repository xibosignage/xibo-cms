// NAVIGATOR Module

// Load templates
const bottomToolbarTemplate = require('../templates/bottom-toolbar.hbs');

/**
 * Bottom toolbar contructor
 * @param {object} container - the container to render the navigator to
 */
let BottomToolbar = function(container) {
    this.DOMObject = container;
};

/**
 * Render toolbar
 */
BottomToolbar.prototype.render = function() {

    // Compile layout template with data
    const html = bottomToolbarTemplate({
        undo: ((lD.manager.changeHistory.length > 0) ? '' : 'disabled')
    });

    // Append layout html to the main div
    this.DOMObject.html(html);

    // TODO: remove this, use just for debug purposes
    this.DOMObject.find('#debugButton').click(function() {
        lD.manager.toggleVisibility();
    });

    // Button actions
    this.DOMObject.find('#refreshDesigner').click(function() {
        lD.reloadData(lD.layout);
    });

    this.DOMObject.find('#undoLastAction').click(function() {
        lD.manager.revertChange();
    });

    // Button to enable navigator edit
    this.DOMObject.find('#enableNavigatorEditMode').click(function() {
        lD.toggleNavigatorEditing(true);
    });
};

module.exports = BottomToolbar;
