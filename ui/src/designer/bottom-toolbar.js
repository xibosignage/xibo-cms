// NAVIGATOR Module

// Load templates
const bottomToolbarTemplate = require('../templates/bottom-toolbar.hbs');

/**
 * Bottom toolbar contructor
 * @param {object} container - the container to render the navigator to
 */
var BottomToolbar = function(container) {
    this.DOMObject = container;

    /**
     * Check if there are changes to revert
     */
    this.checkChanges = function() {
        return (lD.manager.changeHistory.length > 0) ? '' : 'disabled';
    };

};

/**
 * Render toolbar
 */
BottomToolbar.prototype.render = function() {

    // Compile layout template with data
    var html = bottomToolbarTemplate(this);

    // Append layout html to the main div
    this.DOMObject.html(html);

    // TODO: remove this, use just for debug purposes
    this.DOMObject.find('#debugButton').click(function() {
        console.log('Manager changes:');
        console.log(lD.manager.changeHistory);

        console.log('Layout:');
        console.log(lD.layout);

    });

    // Button actions
    this.DOMObject.find('#refreshDesigner').click(function() {
        lD.refreshDesigner();
    });

    this.DOMObject.find('#undoLastAction').click(function() {
        lD.manager.revertLastChange();
    });

    // Button to enable navigator edit
    this.DOMObject.find('#enableNavigatorEditMode').click(function() {
        lD.toggleNavigatorEditing(true);
    });
};

module.exports = BottomToolbar;
