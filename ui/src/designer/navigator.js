// NAVIGATOR Module

// Load templates
const navigatorLayoutTemplate = require('../templates/navigator-layout.hbs');

/**
 * Navigator contructor
 * @param {object} container - the container to render the navigator to
 * @param {object =} [options] - Navigator options
 * @param {bool} [options.edit = false] - Edit mode enable flag
 * @param {number} [options.padding = 0.05] - Padding for the navigator
 */
var Navigator = function(container, {edit = false, padding = 0.05} = {}) {

    this.editMode = edit;
    this.DOMObject = container;
    this.paddingPercentage = padding;
};


/**
 * Render Navigator and the layout
 * @param {Object} layout - the layout object to be rendered
 */
Navigator.prototype.render = function(layout) {
    // Apply navigator scale to the layout
    layout.scaleTo(this);

    // Regions Scalling
    for(var region in layout.regions) {
        layout.regions[region].scaleTo(layout.containerProperties.scaleToTheOriginal);
    }

    // Compile layout template with data
    var html = navigatorLayoutTemplate(layout);

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Make regions draggable and resizable if navigator's on edit mode
    // Get layout container
    var layoutContainer = this.DOMObject.find('#' + layout.id);

    // Find all the regions and enable drag and resize
    this.DOMObject.find('#regions .region').resizable({
        containment: layoutContainer,
        disabled: !this.editMode
    }).draggable({
        containment: layoutContainer,
        disabled: !this.editMode
    }).on("resizestop dragstop",
        function(event, ui) {

            var scale = layout.containerProperties.scaleToTheOriginal;

            layout.regions[$(this).attr('id')].saveTransformation(
                "transform",
                {
                    width: $(this).width() / scale,
                    height: $(this).height() / scale,
                    top: $(this).position().top / scale,
                    left: $(this).position().left / scale
                }
            );
        }
    );

    // Enable hover and select for each layout/region
    if(!this.editMode) {
        this.DOMObject.find('.selectable').click(function(e) {
            e.stopPropagation();
            lD.selectObject($(this));
        });
    }
};

module.exports = Navigator;
