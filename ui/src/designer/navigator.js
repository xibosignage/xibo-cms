// NAVIGATOR Module

// Load templates
const navigatorLayoutTemplate = require('../templates/navigator-layout.hbs');
const navigatorLayoutNavbarTemplate = require('../templates/navigator-layout-edit-navbar.hbs');
const PropertiesPanel = require('../designer/properties-panel.js');

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
    this.regionPropertiesPanel = this.regionPropertiesPanel = new PropertiesPanel(
        this.DOMObject.parent().find('#layout-navigator-properties-panel')
    );

    this.layoutRenderScale = 1;
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

    const self = this;

    if(this.editMode) {
        if(lD.selectedObject.type == 'region') {
            this.openRegionPropertiesPanel();
        } else {
            this.closeRegionPropertiesPanel();
        }
    }

    // Apply navigator scale to the layout
    const scaledLayout = this.scaleLayout(layout, this.DOMObject);

    // Add edit flag
    scaledLayout.edit = this.editMode;

    // Save render scale
    this.layoutRenderScale = scaledLayout.scaledDimensions.scale;

    // Compile layout template with data
    const html = navigatorLayoutTemplate(scaledLayout);

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Make regions draggable and resizable if navigator's on edit mode
    // Get layout container
    const layoutContainer = this.DOMObject.find('#' + layout.id);

    // Find all the regions and enable drag and resize
    if(this.editMode) {
        this.DOMObject.find('#regions .designer-region.editable').each(function() {

            let editDisabled = (lD.selectedObject.id != $(this).attr('id'));

            $(this).resizable({
                containment: layoutContainer,
                disabled: editDisabled
            }).draggable({
                containment: layoutContainer,
                disabled: editDisabled
            }).on("resizestop dragstop",
                function(event, ui) {

                    const scale = lD.navigatorEdit.layoutRenderScale;
                    const transform = {
                        'width': parseFloat(($(this).width() / scale).toFixed(2)),
                        'height': parseFloat(($(this).height() / scale).toFixed(2)),
                        'top': parseFloat(($(this).position().top / scale).toFixed(2)),
                        'left': parseFloat(($(this).position().left / scale).toFixed(2))
                    };

                    if($(this).attr('id') == lD.selectedObject.id) {

                        layout.regions[$(this).attr('id')].transform(transform, false);

                        if(typeof window.regionChangesForm === 'function') {
                            window.regionChangesForm.bind(self.DOMObject)(transform);
                        }
                    }
                }
            );
        });
    }

    // Enable select for each layout/region
    this.DOMObject.find('.selectable').click(function(e) {
        e.stopPropagation();

        // If there was a region select in edit mode, save properties panel
        if(lD.selectedObject.type == 'region' && self.editMode) {
            self.saveRegionPropertiesPanel();
        }

        // Select object
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

        this.DOMObject.find('#close-btn-top').click(function() {
            lD.toggleNavigatorEditing(false);
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
            if(lD.selectedObject.type == 'region' && self.editMode) {
                self.saveRegionPropertiesPanel();
            }

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

    const self = this;

    // Return if navbar does not exist
    if(this.navbarContainer === null) {
        return;
    }

    // Get navigator trans
    let newNavigatorEditTrans = navigatorEditTrans;

    // Check if trash bin is active
    let trashBinActive = lD.selectedObject.isDeletable && (lD.readOnlyMode === undefined || lD.readOnlyMode === false);

    // Get text for bin tooltip
    if(trashBinActive) {
        newNavigatorEditTrans.trashBinActiveTitle = toolbarTrans.deleteObject.replace('%object%', lD.selectedObject.type);
    }

    // Check if there are some changes
    let undoActive = lD.manager.changeHistory.length > 0;

    // Get last action text for popup
    if(undoActive) {
        let lastAction = lD.manager.changeHistory[lD.manager.changeHistory.length - 1];
        if(typeof historyManagerTrans != "undefined" && historyManagerTrans.revert[lastAction.type] != undefined) {
            newNavigatorEditTrans.undoActiveTitle = historyManagerTrans.revert[lastAction.type].replace('%target%', lastAction.target.type);
        } else {
            newNavigatorEditTrans.undoActiveTitle = '[' + lastAction.target.type + '] ' + lastAction.type;
        }
    }

    this.navbarContainer.html(navigatorLayoutNavbarTemplate(
        {
            selected: ((lD.selectedObject.isDeletable) ? '' : 'disabled'),
            undo: ((lD.manager.changeHistory.length > 0) ? '' : 'disabled'),
            trans: navigatorEditTrans,
            regionSelected: (lD.selectedObject.type == 'region')
        }
    ));

    // Navbar buttons
    this.navbarContainer.find('#save-btn').click(function() {

        // If form is opened, save it
        if(lD.selectedObject.type == 'region') {
            self.saveRegionPropertiesPanel();
            self.closeRegionPropertiesPanel();
            lD.selectObject();
        }
    });

    this.navbarContainer.find('#close-btn').click(function() {
            lD.toggleNavigatorEditing(false);
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

        if(lD.selectedObject.type == 'region') {
            self.saveRegionPropertiesPanel();
            self.closeRegionPropertiesPanel();
            lD.selectObject();
        }

        lD.layout.addElement('region').then((res) => { // Success

            lD.common.hideLoadingScreen(); 

            // Behavior if successful 
            toastr.success(res.message);

            // Reload with the new added element
            lD.selectedObject.id = 'region_' + res.data.regionId;
            lD.reloadData(lD.layout, true);
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



Navigator.prototype.openRegionPropertiesPanel = function() {
    $('#layout-navigator-edit').addClass('region-selected');
};

Navigator.prototype.closeRegionPropertiesPanel = function() {
    $('#layout-navigator-edit').removeClass('region-selected');
};

Navigator.prototype.saveRegionPropertiesPanel = function() {
    const app = getXiboApp();
    const form = $(this.regionPropertiesPanel.DOMObject).find('form');
    const element = app.selectedObject;
    const formNewData = form.serialize();

    // If form is valid, and it changed, submit it ( add change )
    if(form.valid() && this.regionPropertiesPanel.formSerializedLoadData != formNewData) {

        app.common.showLoadingScreen();

        // Add a save form change to the history array, with previous form state and the new state
        lD.manager.addChange(
            "saveForm",
            element.type, // targetType
            element[element.type + 'Id'], // targetId
            this.regionPropertiesPanel.formSerializedLoadData, // oldValues
            formNewData, // newValues
            {
                customRequestPath: {
                    url: form.attr('action'),
                    type: form.attr('method')
                },
                upload: true // options.upload
            }
        ).then((res) => { // Success
            app.common.hideLoadingScreen();
            toastr.success(res.message);
        }).catch((error) => { // Fail/error

            app.common.hideLoadingScreen();

            // Show error returned or custom message to the user
            let errorMessage = '';

            if(typeof error == 'string') {
                errorMessage += error;
            } else {
                errorMessage += error.errorThrown;
            }
            // Remove added change from the history manager
            app.manager.removeLastChange();

            // Display message in form
            formHelpers.displayErrorMessage(form, errorMessage, 'danger');

            // Show toast message
            toastr.error(errorMessage);
        });
    }
};

module.exports = Navigator;
