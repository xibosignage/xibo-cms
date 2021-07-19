// NAVIGATOR Module

// Load templates
const BottombarTemplate = require('../templates/bottombar.hbs');

/**
 * Bottom topbar contructor
 * @param {object} container - the container to render the navigator to
 */
let Bottombar = function(parent, container) {

    this.parent = parent;
    
    this.DOMObject = container;

    // Flag to mark if the topbar has been rendered at least one time
    this.firstRun = true;
};

/**
 * Render topbar
 */
Bottombar.prototype.render = function() {

    // Load preferences when the topbar is rendered for the first time
    if(this.firstRun) {
        // Mark topbar as loaded
        this.firstRun = false;
    }

    let self = this;
    const app = this.parent;

    // Get main object 
    const mainObject = app.getElementByTypeAndId(app.mainObjectType, app.mainObjectId);

    // Get topbar trans
    let newBottombarTrans = $.extend(toolbarTrans, bottombarTrans);

    // Compile layout template with data
    const html = BottombarTemplate({
        customDropdownOptions: this.customDropdownOptions,
        displayTooltips: app.common.displayTooltips,
        trans: newBottombarTrans,
        mainObject: mainObject
    });

    // Append layout html to the main div
    this.DOMObject.html(html);
};

module.exports = Bottombar;