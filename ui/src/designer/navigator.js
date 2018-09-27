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

    // Add edit flag
    scaledLayout.edit = this.editMode;

    // Compile layout template with data
    const html = navigatorLayoutTemplate(scaledLayout);

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Make regions draggable and resizable if navigator's on edit mode
    // Get layout container
    const layoutContainer = this.DOMObject.find('#' + layout.id);

    // Use a complentary colour for the navigator background
    this.DOMObject.css('background', $c.complement(layout.backgroundColor));

    // Find all the regions and enable drag and resize
    if(this.editMode) {
    this.DOMObject.find('#regions .designer-region').resizable({
            containment: layoutContainer
    }).draggable({
            containment: layoutContainer
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
    } else {
        this.DOMObject.find('#regions .designer-region').draggable({
            start: function(event, ui) {
                $(this).draggable('instance').offset.click = {
                    left: Math.floor(ui.helper.outerWidth() / 2),
                    top: Math.floor(ui.helper.outerHeight() / 2)
                };
            },
            appendTo: $(lD.toolbar.DOMObject),
            scroll: false,
            cursor: 'crosshair',
            opacity: 0.6,
            zIndex: 100,
            helper: function(event) {
                return $('<div class="layout-region-deletable deletable">' + event.currentTarget.id + '</div>');
            }
        });
    }

    // Enable select for each layout/region
    this.DOMObject.find('.selectable').click(function(e) {
        e.stopPropagation();
        lD.selectObject($(this));
    });

    this.DOMObject.find('[data-type="layout"]').droppable({
        accept: '[drop-to="layout"]',
        drop: function(event, ui) {
            lD.dropItemAdd(event.target, ui.draggable[0]);
        }
    });

    this.DOMObject.find('.designer-region').droppable({
        accept: '[drop-to="region"]',
        drop: function(event, ui) {
            lD.dropItemAdd(event.target, ui.draggable[0]);
        }
    });

    // Handle edit button
    this.DOMObject.find('#edit-btn').click(function() {
        lD.toggleNavigatorEditing(true);
    }.bind(this));

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
        lD.manager.saveAllChanges().then((res) => {   
            lD.toggleNavigatorEditing(false);
        }).catch((err) => {
            if(err) {
                toastr.error('Save all changes failed: ' + err);
            } else {
                toastr.error('Save all changes failed!');
            }
        });
    });

    this.navbarContainer.find('#undo-btn').click(function() {
        lD.manager.revertChange().then((res) => { // Success

            toastr.success(res.message);

            // Refresh designer according to local or API revert
            if(res.localRevert) {
                lD.refreshDesigner();
            } else {
                lD.reloadData(lD.layout);
            }
        }).catch((error) => { // Fail/error

            console.error(error);

            // Show error returned or custom message to the user
            let errorMessage = 'Revert failed: ';

            if(typeof error == 'string') {
                errorMessage += error;
            } else {
                errorMessage += error.errorThrown;
            }

            toastr.error(errorMessage);
        });
    });

    this.navbarContainer.find('#add-btn').click(function() {
        lD.manager.saveAllChanges().then((res) => {

            toastr.success('All changes saved!');

            lD.layout.addElement('region').then((res) => { // Success

                // Behavior if successful 
                toastr.success(res.message);
                lD.reloadData(lD.layout);
            }).catch((error) => { // Fail/error
                // Show error returned or custom message to the user
                let errorMessage = 'Create region failed: ' + error;

                if(typeof error == 'string') {
                    errorMessage += error;
                } else {
                    errorMessage += error.errorThrown;
                }

                toastr.error(errorMessage);
            });
        }).catch((err) => {
            toastr.error('Save all changes failed!');
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
                                // Delete element from the layout
                                lD.layout.deleteElement(lD.selectedObject.type, lD.selectedObject.regionId).then((res) => { // Success
                                    // Behavior if successful 
                                    toastr.success(res.message);
                                    lD.reloadData(lD.layout);
                                }).catch((error) => { // Fail/error
                                    // Show error returned or custom message to the user
                                    let errorMessage = 'Delete element failed: ' + error;

                                    if(typeof error == 'string') {
                                        errorMessage += error;
                                    } else {
                                        errorMessage += error.errorThrown;
                                    }

                                    toastr.error(errorMessage);
                                });
                    }
                }
            }).attr('data-test', 'deleteRegionModal');
        }
    });
};

module.exports = Navigator;
