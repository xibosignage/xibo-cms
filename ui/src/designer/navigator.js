// NAVIGATOR Module

// Load templates
const navigatorLayoutTemplate = require('../templates/navigator-layout.hbs');
const navigatorLayoutNavbarTemplate = require('../templates/navigator-layout-edit-navbar.hbs');

/**
 * Navigator contructor
 * @param {object} container - the container to render the navigator to
 * @param {object =} [options] - Navigator options
 * @param {bool} [options.edit = false] - Edit mode enable flag
 * @param {object} [options.editNavbar = null] - Container to render the navbar
 */
let Navigator = function(container, {edit = false, editNavbar = null} = {}) {
    this.editMode = edit;
    this.DOMObject = container;
    this.navbarContainer = editNavbar;
};

/**
 * Calculate layout values for the layout based on the scale of this container
 * @param {object} layout - object to use as base to scale to
 * @returns {object} Object containing dimensions for the object
 */
Navigator.prototype.scaleLayout = function(layout, container) {

    let layoutClone = Object.assign({}, layout);

    // Get container dimensions
    const containerDimensions = {
        width: container.width(),
        height: container.height()
    };

    // Calculate ratio
    const elementRatio = layoutClone.width / layoutClone.height;
    const containerRatio = containerDimensions.width / containerDimensions.height;

    // Create container properties object
    layoutClone.scaledDimensions = {};

    // Calculate scale factor
    if(elementRatio > containerRatio) { // element is more "landscapish" than the container
        // Scale is calculated using width
        layoutClone.scaledDimensions.scale = containerDimensions.width / layoutClone.width;
    } else { // Same ratio or the container is the most "landscapish"
        // Scale is calculated using height
        layoutClone.scaledDimensions.scale = containerDimensions.height / layoutClone.height;
    }

    // Calculate new values for the element using the scale factor
    layoutClone.scaledDimensions.width = layoutClone.width * layoutClone.scaledDimensions.scale;
    layoutClone.scaledDimensions.height = layoutClone.height * layoutClone.scaledDimensions.scale;

    // Calculate top and left values to centre the element in the container
    layoutClone.scaledDimensions.top = containerDimensions.height / 2 - layoutClone.scaledDimensions.height / 2;
    layoutClone.scaledDimensions.left = containerDimensions.width / 2 - layoutClone.scaledDimensions.width / 2;
   
    // Get scaled background
    layoutClone.calculatedBackground = layoutClone.backgroundCss(layoutClone.scaledDimensions.width, layoutClone.scaledDimensions.height);

    // Regions Scalling
    for(let region in layoutClone.regions) {
        
        layoutClone.regions[region].scaledDimensions = {};

        // Loop through the container properties and scale them according to the layout scale from the original
        for(let property in layoutClone.regions[region].dimensions) {
            if(layoutClone.regions[region].dimensions.hasOwnProperty(property)) {
                layoutClone.regions[region].scaledDimensions[property] = layoutClone.regions[region].dimensions[property] * layoutClone.scaledDimensions.scale;
            }
        }

    }

    return layoutClone;
};

/**
 * Render Navigator and the layout
 * @param {Object} layout - the layout object to be rendered
 */
Navigator.prototype.render = function(layout) {

    // Apply navigator scale to the layout
    const scaledLayout = this.scaleLayout(layout, this.DOMObject);

    // Compile layout template with data
    const html = navigatorLayoutTemplate(scaledLayout);

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Make regions draggable and resizable if navigator's on edit mode
    // Get layout container
    const layoutContainer = this.DOMObject.find('#' + layout.id);

    // Find all the regions and enable drag and resize
    this.DOMObject.find('#regions .designer-region').resizable({
        containment: layoutContainer,
        disabled: !this.editMode
    }).draggable({
        containment: layoutContainer,
        disabled: !this.editMode
    }).on("resizestop dragstop",
        function(event, ui) {

            const scale = scaledLayout.scaledDimensions.scale;
            
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
        lD.manager.saveAllChanges().then(function(res) {
            
            lD.toggleNavigatorEditing(false);
        }).catch(function(jXHR, textStatus, errorThrown) {
            toastr.error(errorThrown, 'Save all changes failed!');
            console.log(jXHR, textStatus, errorThrown);
        });;
        });

    this.navbarContainer.find('#undo-btn').click(function() {
        lD.manager.revertChange();
    });

    this.navbarContainer.find('#add-btn').click(function() {
        lD.manager.saveAllChanges().then(function() {
            lD.layout.addElement('region');
        }).catch(function(jXHR, textStatus, errorThrown) {
            toastr.error(errorThrown, 'Save all changes failed!');
            console.log(jXHR, textStatus, errorThrown);
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
                    
                            }).catch(function(jXHR, textStatus, errorThrown) {
                                toastr.error(errorThrown, 'Remove all changes failed!');
                                console.log(jXHR, textStatus, errorThrown);
                            });
                        }).catch(function(jXHR, textStatus, errorThrown) {
                            toastr.error(errorThrown, 'Save all changes failed!');
                            console.log(jXHR, textStatus, errorThrown);
                        });
                    }
                }
            });

        }
    });
};

module.exports = Navigator;
