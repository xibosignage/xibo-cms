// NAVIGATOR Module

// Load templates
const navigatorLayoutTemplate = require('../templates/navigator-layout.hbs');
const loadingTemplate = require('../templates/loading.hbs');

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
let Navigator = function(parent, container, {edit = false, editNavbar = null} = {}) {
    this.parent = parent;
    this.DOMObject = container;
    this.navbarContainer = editNavbar;
    this.layoutRenderScale = 1;
};

/**
 * Render Navigator and the layout
 */
Navigator.prototype.render = function() {
    const self = this;
    const app = this.parent;

    // Show loading template
    this.DOMObject.html(loadingTemplate());

    // Reset Navbar if exists
    if(this.navbarContainer != null && this.navbarContainer != undefined) {
        this.navbarContainer.removeClass();
        this.navbarContainer.html('');
    }
    
    // Apply navigator scale to the layout
    const scaledLayout = app.layout.scale(this.DOMObject);
    
    // Save render scale
    this.layoutRenderScale = scaledLayout.scaledDimensions.scale;

    // Append layout html to the main div
    this.DOMObject.html(navigatorLayoutTemplate(Object.assign(
        {},
        scaledLayout
    )));

    // Make regions draggable and resizable if navigator's on edit mode
    // Get layout container
    const layoutContainer = this.DOMObject.find('#' + app.layout.id);

    // Find all the regions and enable drag and resize
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

                const scale = lD.navigator.layoutRenderScale;
                const transform = {
                    'width': parseFloat(($(this).width() / scale).toFixed(2)),
                    'height': parseFloat(($(this).height() / scale).toFixed(2)),
                    'top': parseFloat(($(this).position().top / scale).toFixed(2)),
                    'left': parseFloat(($(this).position().left / scale).toFixed(2))
                };

                if($(this).attr('id') == lD.selectedObject.id) {

                    app.layout.regions[$(this).attr('id')].transform(transform, false);

                    if(typeof window.regionChangesForm === 'function') {
                        window.regionChangesForm.bind(self.DOMObject)(transform);
                    }
                }
            }
        );
    });

    // Enable select for each layout/region
    this.DOMObject.find('.selectable').click(function(e) {
        e.stopPropagation();

        // If there was a region select in edit mode, save properties panel
        if(lD.selectedObject.type == 'region') {
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
                // Check if elements was dropped already
                if(ui.helper.hasClass('dropped')) {
                    return false;
                }

                // Add item
                lD.dropItemAdd(event.target, ui.draggable[0]);

                // Mark as dropped
                ui.helper.addClass('dropped');
            }
        });

        // Handle right click context menu
        this.DOMObject.find('.designer-region').contextmenu(function(ev) {

            if($(ev.currentTarget).is('.deletable, .permissionsModifiable')) {

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
        if(lD.selectedObject.type != 'layout' && !this.DOMObject.hasClass('selectable') && !['edit-btn'].includes(e.target.id)) {
            if(lD.selectedObject.type == 'region') {
                self.saveRegionPropertiesPanel();
            }

            lD.selectObject();
        }
    }.bind(this));

    // Handle fullscreen button
    this.DOMObject.parent().find('#fullscreenBtn').off().click(function() {
        this.toggleFullscreen();
    }.bind(this));
};

Navigator.prototype.saveRegionPropertiesPanel = function() {
    const app = this.parent;
    const form = $(app.propertiesPanel.DOMObject).find('form');

    // If form not loaded, prevent changes
    if(form.length == 0) {
        return;
    }
    
    const element = app.selectedObject;
    const formNewData = form.serialize();

    // If form is valid, and it changed, submit it ( add change )
    if(form.valid() && app.propertiesPanel.formSerializedLoadData != formNewData) {

        app.common.showLoadingScreen();

        // Add a save form change to the history array, with previous form state and the new state
        lD.manager.addChange(
            "saveForm",
            element.type, // targetType
            element[element.type + 'Id'], // targetId
            app.propertiesPanel.formSerializedLoadData, // oldValues
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

/**
 * Toggle fullscreen
 */
Navigator.prototype.toggleFullscreen = function() {
    this.DOMObject.parents('#layout-navigator').toggleClass('fullscreen');
    this.parent.editorContainer.toggleClass('fullscreen-mode');

    this.render();
};

module.exports = Navigator;
