// NAVIGATOR Module

// Load templates
const navigatorLayoutTemplate = require('../templates/navigator-layout.hbs');
const navigatorLayoutNavbarTemplate = require('../templates/navigator-layout-edit-navbar.hbs');

/**
 * Navigator contructor
 * @param {object} container - the container to render the navigator to
 * @param {object =} [options] - Navigator options
 * @param {bool} [options.edit = false] - Edit mode enable flag
 * @param {number} [options.padding = 0.05] - Padding for the navigator
 * @param {object} [options.editNavbar = null] - Container to render the navbar
 */
let Navigator = function(container, {edit = false, editNavbar = null, padding = 0.05} = {}) {
    this.editMode = edit;
    this.DOMObject = container;
    this.navbarContainer = editNavbar;
    this.paddingPercentage = padding;
};

/**
 * Calculate layout values for the layout based on the scale of this container
 * @param {object} layout - object to use as base to scale to
 */
Navigator.prototype.scaleLayout = function(layout, container) {

    //TODO: Probably needs some refactor
    
    const layoutSizeRatio = layout.width / layout.height;
    const containerWidth = container.width();
    const containerHeight = container.height();
    const containerSizeRatio = containerWidth / containerHeight;
    const containerPadding = Math.min(containerWidth, containerHeight) * this.paddingPercentage;

    if(layoutSizeRatio > containerSizeRatio) { // If the layout W/H is bigger than the container
        // Calculate width and height 
        layout.containerProperties.width = Math.floor(containerWidth - (containerPadding * 2));
        layout.containerProperties.height = Math.floor(layout.containerProperties.width / layoutSizeRatio);

        // Calculate position of the layout
        layout.containerProperties.left = Math.floor(containerPadding);
        layout.containerProperties.top = Math.floor(containerHeight / 2 - layout.containerProperties.height / 2);

    } else { // If the layout W/H is smaller than the container
        // Calculate width and height 
        layout.containerProperties.height = Math.floor(containerHeight - (containerPadding * 2));
        layout.containerProperties.width = Math.floor(layout.containerProperties.height * layoutSizeRatio);

        // Calculate position of the layout
        layout.containerProperties.top = Math.floor(containerPadding);
        layout.containerProperties.left = Math.floor(containerWidth / 2 - layout.containerProperties.width / 2);
    }

    // Calculate scale from the original
    layout.containerProperties.scaleToTheOriginal = layout.containerProperties.width / layout.width;

    // Regions Scalling
    for(let region in layout.regions) {
        // Loop through the container properties and scale them according to the layout scale from the original
        for(let property in layout.regions[region].containerProperties) {
            if(layout.regions[region].containerProperties.hasOwnProperty(property)) {
                layout.regions[region].containerProperties[property] = layout.regions[region].dimensions[property] * layout.containerProperties.scaleToTheOriginal;
            }
        }

    }
};

/**
 * Render Navigator and the layout
 * @param {Object} layout - the layout object to be rendered
 */
Navigator.prototype.render = function(layout) {

    // Apply navigator scale to the layout
    this.scaleLayout(layout, this.DOMObject);

    // Compile layout template with data
    const html = navigatorLayoutTemplate(layout);

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Make regions draggable and resizable if navigator's on edit mode
    // Get layout container
    const layoutContainer = this.DOMObject.find('#' + layout.id);

    // Find all the regions and enable drag and resize
    this.DOMObject.find('#regions .region').resizable({
        containment: layoutContainer,
        disabled: !this.editMode
    }).draggable({
        containment: layoutContainer,
        disabled: !this.editMode
    }).on("resizestop dragstop",
        function(event, ui) {

            const scale = layout.containerProperties.scaleToTheOriginal;
            
            layout.regions[$(this).attr('id')].transform(
                {
                    'width': parseFloat(($(this).width() / scale).toFixed(2)),
                    'height': parseFloat(($(this).height() / scale).toFixed(2)),
                    'top': parseFloat(($(this).position().top / scale).toFixed(2)),
                    'left': parseFloat(($(this).position().left / scale).toFixed(2))
                }
            );

            // Render navbar to calculate changes and refresh buttons
            lD.navigatorEdit.renderNavbar();
        }
    );

    // Enable select for each layout/region
    this.DOMObject.find('.selectable').click(function(e) {
        e.stopPropagation();
        lD.selectObject($(this));
    });

    // Render navbar
    this.renderNavbar();
};

/**
 * Render Navbar
 */
Navigator.prototype.renderNavbar = function() {

    // Return if navbar does not exist
    if(this.navbarContainer === null) {
        return;
    }

    this.navbarContainer.html(navigatorLayoutNavbarTemplate(
        {
            selected: ((lD.selectedObject.type === 'region') ? '' : 'disabled'),
            undo: ((lD.manager.changeHistory.length > 0) ? '' : 'disabled')
        }
    ));

    // Navbar buttons
    this.navbarContainer.find('#close-btn').click(function() {
        lD.manager.saveAllChanges().then(function() {
            lD.toggleNavigatorEditing(false);
        });
    });

    this.navbarContainer.find('#undo-btn').click(function() {
        lD.manager.revertChange();
    });

    this.navbarContainer.find('#add-btn').click(function() {
        lD.manager.saveAllChanges().then(function() {
            lD.layout.addElement('region');
        });
    });

    this.navbarContainer.find('#delete-btn').click(function() {

        if(lD.selectedObject.type === 'region') {

            bootbox.confirm({
                title: 'Delete Region',
                message: 'Are you sure? All changes related to this object will be erased',
                buttons: {
                    confirm: {
                        label: 'Yes',
                        className: 'btn-danger'
                    },
                    cancel: {
                        label: 'No',
                        className: 'btn-default'
                    }
                },
                callback: function(result) {
                    if(result) {

                        // Save all changes first
                        lD.manager.saveAllChanges().then(function() {

                            // Remove changes from the history array
                            lD.manager.removeAllChanges(lD.selectedObject.type, lD.selectedObject[lD.selectedObject.type + 'Id']).then(function() {

                                // Delete element from the layout
                                lD.layout.deleteElement(
                                    lD.selectedObject.regionId,
                                    lD.selectedObject.type
                                );
                    
                            });
                        });
                    }
                }
            });

        }
    });
};

module.exports = Navigator;
