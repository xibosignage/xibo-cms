// NAVIGATOR Module

/**
 * Navigator contructor
 * @param {object} container - the container to render the navigator to
 * @param {object =} [options] - Navigator options
 * @param {bool} [options.edit = false] - Edit mode enable flag
 * @param {number} [options.padding = 0.05] - Padding for the navigator
 */
var Navigator = function( container, { edit = false, padding = 0.05 } = {} ) {

    this.editMode = edit;

    this.DOMObject = container;
    
    this.paddingPercentage = padding;
};


/**
 * Render Navigator and the layout
 * @param {Object} layout - the layout object to be rendered
 * @param {function} layoutTemplate - the layout handlebar template function
 */
Navigator.prototype.render = function(layout, layoutTemplate) {
    console.log('Navigator - render');

    // Apply navigator scale to the layout
    layout.scaleTo(this);

    // Regions Scalling
    for(var region in layout.regions) {
        layout.regions[region].scaleTo(layout.containerProperties.scaleToTheOriginal);
    }
    

    // Get the background image ( and resize it ) or color
    if(layout.data.backgroundImageId == null) {
        layout.backgroundCss = layout.data.backgroundColor;
    } else {
        layout.backgroundCss = "url('/layout/background/" + layout.id + "?preview=1&width=" + layout.containerProperties.width + "&height=" + layout.containerProperties.height + "&proportional=0&layoutBackgroundId=" + layout.data.backgroundImageId + "') top center no-repeat; background-color: " + layout.data.backgroundColor;
    }

    // Compile layout template with data
    var html = layoutTemplate(layout);

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Make regions draggable and resizable if navigator's on edit mode
    // Get layout container
    var layoutContainer = this.DOMObject.find('#layout_' + layout.id);

    // Find all the regions and enable drag and resize
    this.DOMObject.find('#regions .region').resizable({
        containment: layoutContainer,
        disabled: !this.editMode
    }).draggable({
        containment: layoutContainer,
        disabled: !this.editMode
    }).on("resizestop dragstop",
        function(event, ui) {
            layout.regions[$(this).data('regionId')].saveTransformation($(this).width(), $(this).height(), $(this).position().top, $(this).position().left, layout.containerProperties.scaleToTheOriginal);
        });
}

module.exports = Navigator;
