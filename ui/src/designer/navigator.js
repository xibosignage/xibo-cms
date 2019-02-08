// NAVIGATOR Module

// Load templates
const navigatorLayoutTemplate = require('../templates/navigator-layout.hbs');
const navigatorLayoutNavbarTemplate = require('../templates/navigator-layout-edit-navbar.hbs');

const regionDefaultValues = {
    width: 250,
    height: 250
};

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

    // Set background color as a darken version of the complementary color
    const darkerValue = 50;
    let complementaryColorRGB = $c.hex2rgb($c.complement(layout.backgroundColor));

    if(complementaryColorRGB.R === undefined || complementaryColorRGB.G === undefined || complementaryColorRGB.B === undefined) {
        this.DOMObject.css('background', layout.backgroundColor);
    } else {
        // Darken color
        complementaryColorRGB.R = (complementaryColorRGB.R - darkerValue < 0) ? 0 : complementaryColorRGB.R - darkerValue;
        complementaryColorRGB.G = (complementaryColorRGB.G - darkerValue < 0) ? 0 : complementaryColorRGB.G - darkerValue;
        complementaryColorRGB.B = (complementaryColorRGB.B - darkerValue < 0) ? 0 : complementaryColorRGB.B - darkerValue;

        // Use a complentary colour for the navigator background
        this.DOMObject.css('background', $c.rgb2hex(complementaryColorRGB.R, complementaryColorRGB.G, complementaryColorRGB.B));
    }

    // Find all the regions and enable drag and resize
    if(this.editMode) {
        this.DOMObject.find('#regions .designer-region.editable').resizable({
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
    }

    // Enable select for each layout/region
    this.DOMObject.find('.selectable').click(function(e) {
        e.stopPropagation();
        lD.selectObject($(this));
    });

    if(lD.readOnlyMode === false) {
        this.DOMObject.find('[data-type="layout"]').droppable({
            accept: '[drop-to="layout"]',
            drop: function(event, ui) {



                // Calculate ratio
                let ratio = lD.layout.width / $(event.target).width();

                // Calculate drop position
                let dropPosition = {
                    top: ui.offset.top + ($(ui.helper).height() / 2),
                    left: ui.offset.left + ($(ui.helper).width() / 2)
                };

                // Calculate relative layout position
                let positionInLayoutScaled = {
                    top: dropPosition.top - $(event.target).offset().top,
                    left: dropPosition.left - $(event.target).offset().left
                };
                
                // Calculate real layout position
                let positionInLayout = {
                    top: parseInt(positionInLayoutScaled.top * ratio),
                    left: parseInt(positionInLayoutScaled.left * ratio),
                };

                // Prevent region to go beyond layout borders
                if(positionInLayout.top + regionDefaultValues.height > lD.layout.height) {
                    positionInLayout.top = lD.layout.height - regionDefaultValues.height;
                }

                if(positionInLayout.left + regionDefaultValues.width > lD.layout.width) {
                    positionInLayout.left = lD.layout.width - regionDefaultValues.width;
                }

                // Add item to the layout
                lD.dropItemAdd(event.target, ui.draggable[0], {positionToAdd: positionInLayout});
            }
        });

        this.DOMObject.find('.designer-region').droppable({
            greedy: true,
            accept: function(el) {
                return ($(this).hasClass('editable') && $(el).attr('drop-to') === 'region') ||
                    ($(this).hasClass('permissionsModifiable') && $(el).attr('drop-to') === 'all' && $(el).data('subType') === 'permissions');
            },
            drop: function(event, ui) {
                lD.dropItemAdd(event.target, ui.draggable[0]);
            }
        });

        // Handle edit button
        this.DOMObject.find('#edit-btn').click(function() {
            lD.toggleNavigatorEditing(true);
        }.bind(this));

        // Handle right click context menu
        let editMode = this.editMode;
        this.DOMObject.find('.designer-region').contextmenu(function(ev) {

            if(!editMode && $(ev.currentTarget).is('.deletable, .permissionsModifiable')) {

                // Open context menu
                lD.openContextMenu(ev.currentTarget, {
                    x: ev.pageX,
                    y: ev.pageY
                });
            }

            return false;
        });

    } else {
        // Hide edit button
        this.DOMObject.find('#edit-btn').hide();
    }

    // Handle click on viewer to select layout
    this.DOMObject.off().click(function(e) {
        if(lD.selectedObject.type != 'layout' && !this.editMode && !this.DOMObject.hasClass('selectable') && !['edit-btn'].includes(e.target.id)) {
            lD.selectObject();
        }
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
            selected: ((lD.selectedObject.isDeletable) ? '' : 'disabled'),
            undo: ((lD.manager.changeHistory.length > 0) ? '' : 'disabled')
        }
    ));

    // Navbar buttons
    this.navbarContainer.find('#close-btn').click(function() {
        lD.common.showLoadingScreen();

        lD.manager.saveAllChanges().then((res) => {   
            lD.common.hideLoadingScreen(); 
            lD.toggleNavigatorEditing(false);
        }).catch((err) => {
            lD.common.hideLoadingScreen();
            if(err) {
                toastr.error(errorMessagesTrans.saveAllChangesFailed + ' ' + err);
            } else {
                toastr.error(errorMessagesTrans.saveAllChangesFailed);
            }
        });
    });

    this.navbarContainer.find('#undo-btn').click(function() {
        lD.common.showLoadingScreen();

        lD.manager.revertChange().then((res) => { // Success

            lD.common.hideLoadingScreen();

            toastr.success(res.message);

            // Refresh designer according to local or API revert
            if(res.localRevert) {
                lD.refreshDesigner();
            } else {
                lD.reloadData(lD.layout);
            }
        }).catch((error) => { // Fail/error

            lD.common.hideLoadingScreen();

            // Show error returned or custom message to the user
            let errorMessage = '';

            if(typeof error == 'string') {
                errorMessage = error;
            } else {
                errorMessage = error.errorThrown;
            }

            toastr.error(errorMessagesTrans.revertFailed.replace('%error%', errorMessage));
        });
    });

    this.navbarContainer.find('#add-btn').click(function() {
        lD.common.showLoadingScreen();
        
        lD.manager.saveAllChanges().then((res) => {

            lD.layout.addElement('region').then((res) => { // Success

                lD.common.hideLoadingScreen(); 

                // Behavior if successful 
                toastr.success(res.message);
                lD.reloadData(lD.layout);
            }).catch((error) => { // Fail/error

                lD.common.hideLoadingScreen(); 
                // Show error returned or custom message to the user
                let errorMessage = '';

                if(typeof error == 'string') {
                    errorMessage = error;
                } else {
                    errorMessage = error.errorThrown;
                }

                toastr.error(errorMessagesTrans.createRegionFailed.replace('%error%', errorMessage));
            });
        }).catch((err) => {

            lD.common.hideLoadingScreen(); 
            toastr.error(errorMessagesTrans.saveAllChangesFailed);
        });
    });

    this.navbarContainer.find('#delete-btn').click(function() {

        if(lD.selectedObject.isDeletable) {

            bootbox.confirm({
                title: editorsTrans.deleteTitle.replace('%obj%', 'region'),
                message: editorsTrans.deleteConfirm,
                buttons: {
                    confirm: {
                        label: editorsTrans.yes,
                        className: 'btn-danger'
                    },
                    cancel: {
                        label: editorsTrans.no,
                        className: 'btn-default'
                    }
                },
                callback: function(result) {
                    if(result) {

                        lD.common.showLoadingScreen();

                                // Delete element from the layout
                                lD.layout.deleteElement(lD.selectedObject.type, lD.selectedObject.regionId).then((res) => { // Success
                            
                            lD.common.hideLoadingScreen();

                                    // Behavior if successful 
                                    toastr.success(res.message);
                                    lD.reloadData(lD.layout);
                                }).catch((error) => { // Fail/error

                            lD.common.hideLoadingScreen();
                            
                                    // Show error returned or custom message to the user
                                    let errorMessage = '';

                                    if(typeof error == 'string') {
                                        errorMessage = error;
                                    } else {
                                        errorMessage = error.errorThrown;
                                    }

                                    toastr.error(errorMessagesTrans.deleteFailed.replace('%error%', errorMessage));
                                });
                    }
                }
            }).attr('data-test', 'deleteRegionModal');
        }
    });
};

module.exports = Navigator;
