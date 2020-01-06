/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2018 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

// Include public path for webpack
require('../../public_path');

// Include handlebars templates
const designerMainTemplate = require('../templates/designer.hbs');
const messageTemplate = require('../templates/message.hbs');
const loadingTemplate = require('../templates/loading.hbs');
const contextMenuTemplate = require('../templates/context-menu.hbs');
const deleteElementModalContentTemplate = require('../templates/delete-element-modal-content.hbs');

// Include modules
const Layout = require('../designer/layout.js');
const Navigator = require('../designer/navigator.js');
const Timeline = require('../designer/timeline.js');
const Viewer = require('../designer/viewer.js');
const PropertiesPanel = require('../designer/properties-panel.js');
const Manager = require('../core/manager.js');
const Toolbar = require('../core/toolbar.js');

// Common funtions/tools
const Common = require('../core/common.js');

// Include CSS
require('../css/designer.less');

// Create layout designer namespace (lD)
window.lD = {

    // Read Only mode
    readOnlyMode: false,

    // Attach common functions to layout designer
    common: Common,

    // Main object info
    mainObjectType: 'layout',
    mainObjectId: '',

    // Navigator
    navigator: {},
    navigatorEdit: {},

    // Layout
    layout: {},

    // Timeline
    timeline: {},

    // Manager
    manager: {},

    // Viewer
    viewer: {},

    // Designer DOM div
    designerDiv: $('#layout-editor'),

    // Selected object
    selectedObject: {},

    // Bottom toolbar
    toolbar: {},

    // Properties Panel
    propertiesPanel: {},
};

// Get Xibo app
window.getXiboApp = function() {
    return lD;
};

// Load Layout and build app structure
$(document).ready(function() {
    // Get layout id
    const layoutId = lD.designerDiv.attr("data-layout-id");
    
    lD.common.showLoadingScreen();

    // Append loading html to the main div
    lD.designerDiv.html(loadingTemplate());

    // Change toastr positioning
    toastr.options.positionClass = 'toast-top-center';

    // Load layout through an ajax request
    $.get(urlsForApi.layout.get.url + '?layoutId=' + layoutId + '&embed=regions,playlists,widgets,widget_validity,tags,permissions')
        .done(function(res) {

            if(res.data != null && res.data.length > 0) {

                lD.common.hideLoadingScreen();

                // Append layout html to the main div
                lD.designerDiv.html(designerMainTemplate());

                // Create layout
                lD.layout = new Layout(layoutId, res.data[0]);

                // Update main object id
                lD.mainObjectId = lD.layout.layoutId;

                // Initialize navigator
                lD.navigator = new Navigator(
                    // Small container
                    lD.designerDiv.find('#layout-navigator')
                );

                // Initialize timeline
                lD.timeline = new Timeline(
                    lD.designerDiv.find('#layout-timeline')
                );

                // Initialize manager
                lD.manager = new Manager(
                    lD.designerDiv.find('#layout-manager'),
                    false // (serverMode == 'Test') Turn of manager visibility for now
                );

                // Initialize viewer
                lD.viewer = new Viewer(
                    lD.designerDiv.find('#layout-viewer'),
                    lD.designerDiv.find('#layout-viewer-navbar')
                );

                // Initialize bottom toolbar ( with custom buttons )
                lD.toolbar = new Toolbar(
                    lD.designerDiv.find('#layout-editor-toolbar'),
                    // Custom main buttons
                    [
                        {
                            id: 'publishLayout',
                            title: null,
                            tooltip: layoutDesignerTrans.publishTitle,
                            logo: 'fa-check-square-o',
                            class: 'btn-success',
                            action: lD.showPublishScreen,
                            inactiveCheck: function() {
                                return (lD.layout.editable == false);
                            },
                            inactiveCheckClass: 'hidden',
                        }
                    ],
                    // Custom dropdown buttons
                    [
                        {
                            id: 'checkoutLayout',
                            title: layoutDesignerTrans.checkoutTitle,
                            logo: 'fa-edit',
                            class: 'btn-success',
                            action: lD.showCheckoutScreen,
                            inactiveCheck: function() {
                                return (lD.layout.editable == true);
                            },
                            inactiveCheckClass: 'hidden',
                        },
                        {
                            id: 'scheduleLayout',
                            title: layoutDesignerTrans.scheduleTitle,
                            logo: 'fa-clock-o',
                            class: 'btn-info',
                            action: lD.showScheduleScreen,
                            inactiveCheck: function() {
                                return (lD.layout.editable == true || lD.layout.scheduleNowPermission == false);
                            },
                            inactiveCheckClass: 'hidden',
                        },
                        {
                            id: 'saveTemplate',
                            title: layoutDesignerTrans.saveTemplateTitle,
                            logo: 'fa-floppy-o',
                            class: 'btn-warning',
                            action: lD.showSaveTemplateScreen,
                            inactiveCheck: function() {
                                return (lD.layout.editable == true);
                            },
                            inactiveCheckClass: 'hidden',
                        }
                    ],
                    // Custom actions
                    {
                        deleteSelectedObjectAction: lD.deleteSelectedObject,
                        deleteDraggedObjectAction: lD.deleteDraggedObject
                    },
                    // jumpList
                    {
                        searchLink: urlsForApi.layout.get.url,
                        designerLink: urlsForApi.layout.designer.url,
                        layoutId: lD.layout.layoutId,
                        layoutName: lD.layout.name,
                        callback: lD.reloadData
                    }
                );

                // Initialize properties panel
                lD.propertiesPanel = new PropertiesPanel(
                    lD.designerDiv.find('#properties-panel')
                );

                if(res.data[0].publishedStatusId != 2) {

                    var url = new URL(window.location.href);

                    if(url.searchParams.get('vM') == '1') {
                        // Enter view mode
                        lD.enterReadOnlyMode();
                    } else {
                        // Enter welcome screen
                        lD.welcomeScreen();
                    }
                }

                // Setup helpers
                formHelpers.setup(lD, lD.layout);

                // Load user preferences
                lD.loadAndSavePref('useLibraryDuration', 0);
                
                // Call layout status every minute
                lD.checkLayoutStatus();
                setInterval(lD.checkLayoutStatus, 1000 * 60); // Every minute

                // Default selected object is the layout
                lD.selectObject();
            } else {
                // Login Form needed?
                if(res.login) {
                    window.location.href = window.location.href;
                    location.reload(false);
                } else {
                    lD.showErrorMessage();
                }
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {

            // Output error to console
            console.error(jqXHR, textStatus, errorThrown);

            lD.showErrorMessage();
        }
    );

    // When in edit mode, enable click on background to close navigator
    lD.designerDiv.find('#layout-navigator-edit').click(function(event) {
        if(event.target.id === 'layout-navigator-edit') {
            lD.toggleNavigatorEditing(false);
        }
    });

    // Handle keyboard keys
    $('body').off('keydown').keydown(function(handler) {
        if($(handler.target).is($('body'))) {

            if(handler.key == 'Delete' && lD.readOnlyMode == false) {
                lD.deleteSelectedObject();
            }
        }
    });

    // Refresh some modules on window resize
    $(window).resize(_.debounce(function(e) {
        if(e.target === window) {

            // Refresh navigators and viewer
            lD.renderContainer(lD.navigator);
            lD.renderContainer(lD.navigatorEdit);
            lD.renderContainer(lD.viewer, lD.selectedObject);
        }
    }, 250));
});

/**
 * Select a layout object (layout/region/widget)
 * @param {object=} obj - Object to be selected
 * @param {bool=} forceSelect - Select object even if it was already selected
 */
lD.selectObject = function(obj = null, forceSelect = false) {

    // If there is a selected card, use the drag&drop simulate to add that item to a object
    if(!$.isEmptyObject(this.toolbar.selectedCard)) {

        // If selected card has the droppable type or "all"
        if([obj.data('type'), 'all'].indexOf($(this.toolbar.selectedCard).attr('drop-to')) !== -1) {

            // Get card object
            const card = this.toolbar.selectedCard[0];

            // Deselect cards and drop zones
            this.toolbar.deselectCardsAndDropZones();

            // Simulate drop item add
            this.dropItemAdd(obj, card);
        }

    } else {
        
        // Get object properties from the DOM ( or set to layout if not defined )
        const newSelectedId = (obj === null) ? this.layout.id : obj.attr('id');
        let newSelectedType = (obj === null) ? 'layout' : obj.data('type');

        const oldSelectedId = this.selectedObject.id;
        
        // Unselect the previous selectedObject object if still selected
        if( this.selectedObject.selected ) {

            switch(this.selectedObject.type) {
                case 'region':
                    if(this.layout.regions[this.selectedObject.id]) {
                        this.layout.regions[this.selectedObject.id].selected = false;
                    }
                    break;

                case 'widget':
                    if(this.layout.regions[this.selectedObject.regionId].widgets[this.selectedObject.id]) {
                        this.layout.regions[this.selectedObject.regionId].widgets[this.selectedObject.id].selected = false;
                    }
                    break;

                default:
                    break;
            }
            
        }
        
        // Set to the default object
        this.selectedObject = this.layout;
        this.selectedObject.type = 'layout';

        // If the selected object was different from the previous, select a new one
        if(oldSelectedId != newSelectedId || forceSelect) {

            // Save the new selected object
            if(newSelectedType === 'region') {

                // If we're not in the navigator edit and the region has widgets, select the first one
                if(!forceSelect && $.isEmptyObject(this.navigatorEdit) && !$.isEmptyObject(this.layout.regions[newSelectedId].widgets)) {
                    let widgets = this.layout.regions[newSelectedId].widgets;

                    // Select first widget
                    for(var widget in widgets) {
                        if(widgets.hasOwnProperty(widget)) {
                            if(widgets[widget].index == 1) {
                                widgets[widget].selected = true;
                                this.selectedObject = widgets[widget];
                                newSelectedType = 'widget';
                                break;
                            }
                        }
                    }
                } else {
                this.layout.regions[newSelectedId].selected = true;
                this.selectedObject = this.layout.regions[newSelectedId];
                }
            } else if(newSelectedType === 'widget') {
                this.layout.regions[obj.data('widgetRegion')].widgets[newSelectedId].selected = true;
                this.selectedObject = this.layout.regions[obj.data('widgetRegion')].widgets[newSelectedId];
            }

            this.selectedObject.type = newSelectedType;
        }

        // Refresh the designer containers
        this.refreshDesigner();
    }
};

/**
 * Refresh designer
 */
lD.refreshDesigner = function() {

    // Remove temporary data
    this.clearTemporaryData();

    // Render containers with layout ( default )
    this.renderContainer(this.navigator);
    this.renderContainer(this.navigatorEdit);
    this.renderContainer(this.timeline);
    this.renderContainer(this.toolbar);
    this.renderContainer(this.manager);

    // Render selected object in the following containers
    if(this.selectedObject.type == 'region') {
        this.renderContainer(this.navigatorEdit.regionPropertiesPanel, this.selectedObject);
        this.renderContainer(this.propertiesPanel, {});
    } else {
        this.renderContainer(this.propertiesPanel, this.selectedObject);
    }
    
    this.renderContainer(this.viewer, this.selectedObject);

    // Reload tooltips
    this.common.reloadTooltips(this.designerDiv);
};


/**
 * Reload API data and replace the layout structure with the new value
 * @param {object} layout - previous layout
 */
lD.reloadData = function(layout, refreshBeforeSelect = false) {

    const layoutId = (typeof layout.layoutId == 'undefined') ? layout : layout.layoutId;

    lD.common.showLoadingScreen();

    $.get(urlsForApi.layout.get.url + '?layoutId=' + layoutId + "&embed=regions,playlists,widgets,widget_validity,tags,permissions")
        .done(function(res) {
            
            lD.common.hideLoadingScreen();
            
            if(res.data != null && res.data.length > 0) {
                lD.layout = new Layout(layoutId, res.data[0]);

                // Update main object id
                lD.mainObjectId = lD.layout.layoutId;

                // To select an object that still doesn't exist
                if(refreshBeforeSelect) {
                    lD.refreshDesigner();
                }
                
                // Select the same object ( that will refresh the layout too )
                const selectObjectId = lD.selectedObject.id;
                lD.selectedObject = {};

                lD.selectObject($('#' + selectObjectId));
            
                // Reload the form helper connection
                formHelpers.setup(lD, lD.layout);

                // If there was a opened menu in the toolbar, open that tab
                if(lD.toolbar.openedMenu != -1) {
                    lD.toolbar.openTab(lD.toolbar.openedMenu, true);
                }

                // Check layout status
                lD.checkLayoutStatus();
            } else {
                // Login Form needed?
                if(res.login) {
                    window.location.href = window.location.href;
                    location.reload(false);
                } else {
                    lD.showErrorMessage();
                }
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {

            lD.common.hideLoadingScreen();

            // Output error to console
            console.error(jqXHR, textStatus, errorThrown);

            lD.showErrorMessage();
        }
    );
};

/**
 * Checkout layout
 */
lD.checkoutLayout = function() {

    const linkToAPI = urlsForApi.layout.checkout;
    let requestPath = linkToAPI.url;

    lD.common.showLoadingScreen();

    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', lD.layout.layoutId);

    $.ajax({
        url: requestPath,
        type: linkToAPI.type
    }).done(function(res) {

        lD.common.hideLoadingScreen();

        if(res.success) {
            toastr.success(res.message);

            // Turn off read only mode
            lD.readOnlyMode = false;

            // Hide read only message
            lD.designerDiv.find('#read-only-message').remove();
            
            // Reload layout
            lD.reloadData(res.data);

            bootbox.hideAll();
        } else {
            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload(false);
            } else {
                toastr.error(res.message);
            }
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        lD.common.hideLoadingScreen();

        // Output error to console
        console.error(jqXHR, textStatus, errorThrown);
    });
};

/**
 * Publish layout
 */
lD.publishLayout = function() {

    const linkToAPI = urlsForApi.layout.publish;
    let requestPath = linkToAPI.url;

    lD.common.showLoadingScreen();

    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', lD.layout.parentLayoutId);

    const serializedData = $('#layoutPublishForm').serialize();

    $.ajax({
        url: requestPath,
        type: linkToAPI.type,
        data: serializedData
    }).done(function(res) {

        lD.common.hideLoadingScreen();

        if(res.success) {
            
            toastr.success(res.message);

            // Redirect to the new published layout ( read only mode )
            window.location.href = urlsForApi.layout.designer.url.replace(':id', res.data.layoutId) + '?vM=1';
        } else {

            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload(false);
            } else {
                toastr.error(res.message);

                // Close dialog
                bootbox.hideAll();
            }
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        lD.common.hideLoadingScreen();
        
        // Output error to console
        console.error(jqXHR, textStatus, errorThrown);
    });
};

/**
 * Read Only Mode
 */
lD.welcomeScreen = function() {

    // Turn on read only mode
    lD.readOnlyMode = true;

    bootbox.dialog({
        message: layoutDesignerTrans.welcomeModalMessage,
        className: "welcome-screen-modal",
        closeButton: false,
        buttons: {
            checkout: {
                label: layoutDesignerTrans.checkoutTitle,
                className: "btn-success",
                callback: function(res) {

                    $(res.currentTarget).append('&nbsp;<i class="fa fa-cog fa-spin"></i>');

                    // Unselect objects ( select layout )
                    lD.selectObject();

                    lD.checkoutLayout();

                    // Prevent the modal to close ( close only when checkout layout resolves )
                    return false;
                }
            },
            view: {
                label: layoutDesignerTrans.viewModeTitle,
                className: "btn-default",
                callback: function(res) {
                    lD.enterReadOnlyMode();
                }
            }

        }
    }).attr('data-test', 'welcomeModal');
};

/**
 * Read Only Mode
 */
lD.enterReadOnlyMode = function() {
    // Add alert message to the layout designer
    lD.designerDiv.prepend('<div id="read-only-message" class="alert alert-info" role="alert"><strong>' + layoutDesignerTrans.readOnlyModeTitle + '</strong>&nbsp;' + layoutDesignerTrans.readOnlyModeMessage + '</div>');
    
    // Turn on read only mode
    lD.readOnlyMode = true;
};

/**
 * Render layout structure to container, if it exists
 * @param {object} container - Container for the layout to be rendered
 * @param {object=} element - Element to be rendered, if not used, render layout
 */
lD.renderContainer = function(container, element = {}) {
    // Check container to prevent rendering to an empty container
    if(!jQuery.isEmptyObject(container)) {

        // Render element if defined, layout otherwise
        if(!jQuery.isEmptyObject(element)) {
            container.render(element);
        } else {
            container.render(this.layout);
        }
    }
};

/**
 * Toggle editing functionality on Navigator
 * @param {boolean} enable - flag to toggle the editing
 */
lD.toggleNavigatorEditing = function(enable) {

    // Unselect objects ( select layout )
    this.selectObject();

    if(enable) {
        // Create a new navigator instance
        this.navigatorEdit = new Navigator(
            this.designerDiv.find('#layout-navigator-edit-content'),
            {
                edit: true,
                editNavbar: this.designerDiv.find('#layout-navigator-edit-navbar')
            }
        );

        // Show navigator edit div
        this.designerDiv.find('#layout-navigator-edit').css('display', 'block');

        // Render navigator
        this.renderContainer(this.navigatorEdit);

    } else {

        // Refresh designer
        this.reloadData(lD.layout);

        // Clean variable
        this.navigatorEdit = {};

        // Clean object HTML and hide div
        this.designerDiv.find('#layout-navigator-edit-content').empty();
        this.designerDiv.find('#layout-navigator-edit').css('display', 'none');

    }
};

/**
 * Layout loading error message
 */
lD.showErrorMessage = function() {
    // Output error on screen
    const htmlError = messageTemplate({
        messageType: 'danger',
        messageTitle: errorMessagesTrans.error,
        messageDescription: errorMessagesTrans.loadingLayout,
    });

    lD.designerDiv.html(htmlError);
};

/**
 * Layout checkout screen
 */
lD.showCheckoutScreen = function() {
    
    bootbox.dialog({
        title: layoutDesignerTrans.checkoutTitle + ' ' + lD.layout.name,
        message: layoutDesignerTrans.checkoutMessage,
        buttons: {
            checkout: {
                label: layoutDesignerTrans.checkoutTitle,
                className: "btn-success",
                callback: function(res) {

                    $(res.currentTarget).append('&nbsp;<i class="fa fa-cog fa-spin"></i>');

                    // Unselect objects ( select layout )
                    lD.selectObject();

                    lD.checkoutLayout();

                    // Prevent the modal to close ( close only when checkout layout resolves )
                    return false;
                }
            }
        }
    }).attr('data-test', 'checkoutModal');
};

/**
 * Layout publish screen
 */
lD.showPublishScreen = function() {
    lD.loadFormFromAPI('publishForm', lD.layout.parentLayoutId, "formHelpers.setupCheckboxInputFields($('#layoutPublishForm'), '#publishNow', '', '.publish-date-control')", "lD.publishLayout();");
};

/**
 * Layout schedule screen
 */
lD.showScheduleScreen = function() {
    lD.loadFormFromAPI('schedule', lD.layout.campaignId);
};

/**
 * Layout save template screen
 */
lD.showSaveTemplateScreen = function() {
    lD.loadFormFromAPI('saveTemplate', lD.layout.layoutId);
};

/**
 * Load form from the API
 */
lD.loadFormFromAPI = function(type, id = null, apiFormCallback = null, mainActionCallback = null) {

    const self = this;

    // Load form the API
    const linkToAPI = urlsForApi.layout[type];

    let requestPath = linkToAPI.url;

    // Replace ID
    if(id != null) {
        requestPath = requestPath.replace(':id', id);
    }
    
    // Create dialog
    var calculatedId = new Date().getTime();

    // Request and load element form
    $.ajax({
        url: requestPath,
        type: linkToAPI.type
    }).done(function(res) {

        if(res.success) {
            // Create buttons
            let generatedButtons = {
                cancel: {
                    label: translations.cancel,
                    className: 'btn-default'
                }
            };

            // Get buttons from form
            for(var button in res.buttons) {
                if(res.buttons.hasOwnProperty(button)) {
                    if(button != translations.cancel) {
                        let buttonType = 'btn-default';

                        if(button === translations.save || button === editorsTrans.publish) {
                            buttonType = 'btn-primary';
                        }

                        let url = res.buttons[button];

                        generatedButtons[button] = {
                            label: button,
                            className: buttonType,
                            callback: function(result) {
                                // Call global function by the function name
                                if (mainActionCallback != null) {
                                    eval(mainActionCallback);
                                } else {
                                    eval(url);
                                }

                                return false;
                            }
                        };
                    }
                }
            }

            // Create dialog
            let dialog = bootbox.dialog({
                className: 'second-dialog',
                title: res.dialogTitle,
                message: res.html,
                buttons: generatedButtons
            }).attr('id', calculatedId).attr('data-test', type + 'LayoutForm');

            dialog.data('extra', res.extra);

            // Form open callback
            if(res.callBack != undefined && typeof window[res.callBack] === 'function') {
                window[res.callBack](dialog);
            }

            // Call Xibo Init for this form
            XiboInitialise('#' + dialog.attr('id'));

            if (apiFormCallback != null) {
                eval(apiFormCallback);
            }

        } else {

            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload(false);
            } else {

                toastr.error(errorMessagesTrans.formLoadFailed);

                // Just an error we dont know about
                if(res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }
            }
        }

    }).catch(function(jqXHR, textStatus, errorThrown) {

        console.error(jqXHR, textStatus, errorThrown);
        toastr.error(errorMessagesTrans.formLoadFailed);
    });
};

/**
 * Revert last action
 */
lD.undoLastAction = function() {
    lD.common.showLoadingScreen('undoLastAction');

    lD.manager.revertChange().then((res) => { // Success

        lD.common.hideLoadingScreen('undoLastAction');

        toastr.success(res.message);

        // Refresh designer according to local or API revert
        if(res.localRevert) {
            lD.refreshDesigner();
        } else {
            lD.reloadData(lD.layout);
        }
    }).catch((error) => { // Fail/error

        lD.common.hideLoadingScreen('undoLastAction');

        // Show error returned or custom message to the user
        let errorMessage = '';

        if(typeof error == 'string') {
            errorMessage =  error;
        } else {
            errorMessage = error.errorThrown;
        }

        toastr.error(errorMessagesTrans.revertFailed.replace('%error%', errorMessage));
    });
};


/**
 * Delete selected object
 */
lD.deleteSelectedObject = function() {
    if(lD.selectedObject.type === 'region') {
        lD.deleteObject(lD.selectedObject.type, lD.selectedObject[lD.selectedObject.type+'Id']);
    } else if(lD.selectedObject.type === 'widget') {
        lD.deleteObject(
            lD.selectedObject.type,
            lD.selectedObject[lD.selectedObject.type + 'Id'],
            lD.layout.regions[lD.selectedObject.regionId].regionId
        );
    }
};

/**
 * Delete dragged object
 * @param {object} draggable - "jqueryui droppable" ui draggable object
 */
lD.deleteDraggedObject = function(draggable) {
    const objectType = draggable.data('type');
    let objectId = null;
    let objectAuxId = null;

    if(objectType === 'region') {
        objectId = lD.layout.regions[draggable.attr('id')].regionId;
    } else if(objectType === 'widget') {
        objectId = lD.layout.regions[draggable.data('widgetRegion')].widgets[draggable.data('widgetId')].widgetId;
        objectAuxId = lD.layout.regions[draggable.data('widgetRegion')].regionId;
    }

    lD.deleteObject(objectType, objectId, objectAuxId);
};

/**
 * Delete object
 * @param {object} objectToDelete - menu to load content for
 */
lD.deleteObject = function(objectType, objectId, objectAuxId = null) {

    const createDeleteModal = function(objectType, objectId, hasMedia = false, showDeleteFromLibrary = false) {

        bootbox.hideAll();

        const htmlContent = deleteElementModalContentTemplate({
            mainMessage: deleteMenuTrans.mainMessage.replace('%obj%', objectType),
            hasMedia: hasMedia,
            showDeleteFromLibrary: showDeleteFromLibrary,
            trans: deleteMenuTrans
        });

        bootbox.dialog({
            title: editorsTrans.deleteTitle.replace('%obj%', objectType),
            message: htmlContent,
            buttons: {
                cancel: {
                    label: editorsTrans.no,
                    className: 'btn-default'
                },
                confirm: {
                    label: editorsTrans.yes,
                    className: 'btn-danger',
                    callback: function() {

                        // Empty options object
                        let options = null;

                        // If delete media is checked, pass that as a param for delete
                        if($(this).find('input#deleteMedia').is(':checked')) {
                            options = {
                                deleteMedia: 1
                            };
                        }

                        lD.common.showLoadingScreen('deleteObject');

                        // Delete element from the layout
                        lD.layout.deleteElement(objectType, objectId, options).then((res) => { // Success

                            lD.common.hideLoadingScreen('deleteObject');

                            // Reset timeline zoom
                            lD.timeline.resetZoom();
                            
                            // Behavior if successful
                            toastr.success(res.message);
                            lD.reloadData(lD.layout);
                        }).catch((error) => { // Fail/error

                            lD.common.hideLoadingScreen('deleteObject');

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
            }
        }).attr('data-test', 'deleteObjectModal');
    };

    if(objectType === 'region') {
        createDeleteModal(objectType, objectId);
    } else if(objectType === 'widget') {

        const widgetToDelete = lD.getElementByTypeAndId('widget', 'widget_' + objectAuxId + '_' + objectId, 'region_' + objectAuxId);

        if(widgetToDelete.mediaIds.length == 0) {
            createDeleteModal(objectType, objectId);
        } else {
            lD.common.showLoadingScreen('checkMediaIsUsed');

            const linkToAPI = urlsForApi.media.isUsed;
            let requestPath = linkToAPI.url.replace(':id', widgetToDelete.mediaIds[0]);

            // Request with count as being 2, for the published layout and draft
            $.get(requestPath + '?count=1')
                .done(function(res) {
                    if(res.success) {
                        createDeleteModal(objectType, objectId, true, !res.data.isUsed);
                    } else {
                        if(res.login) {
                            window.location.href = window.location.href;
                            location.reload(false);
                        } else {
                            toastr.error(res.message);
                        }
                    }

                    lD.common.hideLoadingScreen('checkMediaIsUsed');

                }).fail(function(jqXHR, textStatus, errorThrown) {

                    lD.common.hideLoadingScreen('checkMediaIsUsed');

                    // Output error to console
                    console.error(jqXHR, textStatus, errorThrown);
                });
        }
    }
};

/**
 * Add action to take after dropping a draggable item
 * @param {object} droppable - Target drop object
 * @param {object} draggable - Dragged object
 * @param {object =} [options] - Options
 * @param {object} [options.positionToAdd = null] - Position object {top, left}
 */
lD.dropItemAdd = function(droppable, draggable, {positionToAdd = null} = {}) {

    const droppableId = $(droppable).attr('id');
    const droppableType = $(droppable).data('type');
    const draggableType = $(draggable).data('type');
    const draggableSubType = $(draggable).data('subType');
    
    if(draggableType == 'media') { // Adding media from search tab to a region

        // Get playlist Id
        const playlistId = lD.layout.regions[droppableId].playlists.playlistId;
        const mediaId = $(draggable).data('mediaId');

        lD.addMediaToPlaylist(playlistId, mediaId);

    } else if(draggableType == 'module') { // Add widget/module

        // Get playlist Id
        const playlistId = lD.layout.regions[droppableId].playlists.playlistId;

        // Get regionSpecific property
        const moduleData = $(draggable).data();

        // Select region ( and avoid deselect if region was already selected )
        lD.selectObject($(droppable), true);

        lD.addModuleToPlaylist(playlistId, draggableSubType, moduleData);
    } else if(draggableType == 'tool') { // Add tool

        if(droppableType == 'layout') { // Add to layout

            // Select layout
            lD.selectObject();

            if(draggableSubType == 'region') { // Add region to layout

                lD.common.showLoadingScreen('addRegionToLayout'); 

                lD.manager.saveAllChanges().then((res) => {

                    toastr.success(editorsTrans.allChangesSaved);

                    lD.layout.addElement('region', positionToAdd).then((res) => { // Success

                        lD.common.hideLoadingScreen('addRegionToLayout'); 

                        // Behavior if successful 
                        toastr.success(res.message);

                        lD.selectedObject.id = 'region_' + res.data.regionId;
                        lD.reloadData(lD.layout, true);
                    }).catch((error) => { // Fail/error

                        lD.common.hideLoadingScreen('addRegionToLayout'); 

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

                    lD.common.hideLoadingScreen('addRegionToLayout'); 

                    toastr.error(errorMessagesTrans.saveAllChangesFailed);
                });

            }
        } else if(droppableType == 'widget') { // Add to widget

            // Get widget
            const widgetId = $(droppable).attr('id');
            const widgetRegionId = $(droppable).data('widgetRegion');
            const widget = lD.getElementByTypeAndId('widget', widgetId, widgetRegionId);

            // Select widget ( and avoid deselect if region was already selected )
            lD.selectObject($(droppable), true);

            if(draggableSubType == 'audio') {
                widget.editAttachedAudio();
            } else if(draggableSubType == 'expiry') { 
                widget.editExpiry();
            } else if(draggableSubType == 'transitionIn') { 
                widget.editTransition('in');
            } else if(draggableSubType == 'transitionOut') { 
                widget.editTransition('out');
            } else if(draggableSubType == 'permissions') {
                widget.editPermissions();
            }
        } else if(droppableType == 'region') { // Add to region

            //Get region
            const regionId = $(droppable).attr('id');
            const region = lD.getElementByTypeAndId('region', regionId);

            region.editPropertyForm('Permissions');
        }
    }
};

/**
 * Add module to playlist
 * @param {number} playlistId 
 * @param {string} moduleType 
 * @param {object} moduleData 
 */
lD.addModuleToPlaylist = function (playlistId, moduleType, moduleData) {

    if(moduleData.regionSpecific == 0) { // Upload form if not region specific

        const validExt = moduleData.validExt.replace(/,/g, "|");

        lD.openUploadForm({
            trans: uploadTrans,
            upload: {
                maxSize: moduleData.maxSize,
                maxSizeMessage: moduleData.maxSizeMessage,
                validExtensionsMessage: translations.validExtensions + ': ' + moduleData.validExt,
                validExt: validExt
            },
            playlistId: playlistId
        }, 
        {
            viewLibrary: {
                label: uploadTrans.viewLibrary,
                className: "btn-white",
                callback: function() {
                    lD.toolbar.openNewTabAndSearch(moduleType);
                }
            },
            main: {
                label: translations.done,
                className: "btn-primary",
                callback: function() {
                    lD.timeline.resetZoom();
                    lD.reloadData(lD.layout);
                }
            }
        });

    } else { // Add widget to a region

        const linkToAPI = urlsForApi.playlist.addWidget;

        let requestPath = linkToAPI.url;

        lD.common.showLoadingScreen('addModuleToPlaylist');

        // Replace type
        requestPath = requestPath.replace(':type', moduleType);

        // Replace playlist id
        requestPath = requestPath.replace(':id', playlistId);

        lD.manager.addChange(
            'addWidget',
            'playlist', // targetType 
            playlistId,  // targetId
            null,  // oldValues
            null, // newValues
            {
                updateTargetId: true,
                updateTargetType: 'widget',
                customRequestPath: {
                    url: requestPath,
                    type: linkToAPI.type
                }
            }
        ).then((res) => { // Success

            lD.common.hideLoadingScreen('addModuleToPlaylist');

            // Behavior if successful 
            toastr.success(res.message);

            lD.timeline.resetZoom();

            // The new selected object as the id based on the previous selected region
            lD.selectedObject.id = 'widget_' + lD.selectedObject.regionId + '_' + res.data.widgetId;
            lD.reloadData(lD.layout, true);
            
        }).catch((error) => { // Fail/error

            lD.common.hideLoadingScreen('addModuleToPlaylist');

            // Show error returned or custom message to the user
            let errorMessage = '';

            if(typeof error == 'string') {
                errorMessage = error;
            } else {
                errorMessage = error.errorThrown;
            }

            // Remove added change from the history manager
            lD.manager.removeLastChange();

            // Show toast message
            toastr.error(errorMessagesTrans.addModuleFailed.replace('%error%', errorMessage));
        });
    }  
};
/**
 * Add media from library to a playlist
 * @param {number} playlistId 
 * @param {number} mediaId 
 */
lD.addMediaToPlaylist = function(playlistId, mediaId) {

    // Get media Id
    let mediaToAdd = {
        media: [
            mediaId
        ]
    };

    // Check if library duration options exists and add it to the query
    if(lD.useLibraryDuration != undefined) {
        mediaToAdd.useDuration = (lD.useLibraryDuration == "1");
    }

    lD.common.showLoadingScreen('addMediaToPlaylist');

    // Create change to be uploaded
    lD.manager.addChange(
        'addMedia',
        'playlist', // targetType 
        playlistId,  // targetId
        null,  // oldValues
        mediaToAdd, // newValues
        {
            updateTargetId: true,
            updateTargetType: 'widget'
        }
    ).then((res) => { // Success

        lD.common.hideLoadingScreen('addMediaToPlaylist');

        // Behavior if successful 
        toastr.success(res.message);

        // The new selected object as the id based on the previous selected region
        lD.selectedObject.id = 'widget_' + res.data.regionId + '_' + res.data.newWidgets[0].widgetId;

        lD.timeline.resetZoom();
        lD.reloadData(lD.layout, true);
    }).catch((error) => { // Fail/error

        lD.common.hideLoadingScreen('addMediaToPlaylist');

        // Show error returned or custom message to the user
        let errorMessage = '';

        if(typeof error == 'string') {
            errorMessage = error;
        } else {
            errorMessage = error.errorThrown;
        }

        // Show toast message
        toastr.error(errorMessagesTrans.addMediaFailed.replace('%error%', errorMessage));
    });
};

/**
 * Open Upload Form
 * @param {object} templateOptions
 * @param {object} buttons
 */
lD.openUploadForm = function(templateOptions, buttons) {

    // Close the current dialog
    bootbox.hideAll();

    var template = Handlebars.compile($("#template-file-upload").html());

    // Handle bars and open a dialog
    bootbox.dialog({
        message: template(templateOptions),
        title: uploadTrans.uploadMessage,
        buttons: buttons,
        animate: false,
        updateInAllChecked: uploadFormUpdateAllDefault,
        deleteOldRevisionsChecked: uploadFormDeleteOldDefault
    }).attr('data-test', 'uploadFormModal');

    lD.openUploadFormModelShown($(".modal-body").find("form"));
};

/**
 * Modal shown
 * @param {object} form
 */
lD.openUploadFormModelShown = function(form) {

    // Configure the upload form
    var url = libraryAddUrl;

    // Initialize the jQuery File Upload widget:
    form.fileupload({
        url: url,
        disableImageResize: true
    });

    // Upload server status check for browsers with CORS support:
    if($.support.cors) {
        $.ajax({
            url: url,
            type: 'HEAD'
        }).fail(function() {
            $('<span class="alert alert-error"/>')
                .text('Upload server currently unavailable - ' + new Date())
                .appendTo(form);
        });
    }

    // Enable iframe cross-domain access via redirect option:
    form.fileupload(
        'option',
        'redirect',
        window.location.href.replace(
            /\/[^\/]*$/,
            '/cors/result.html?%s'
        )
    );

    form.bind('fileuploadsubmit', function(e, data) {
        var inputs = data.context.find(':input');
        if(inputs.filter('[required][value=""]').first().focus().length) {
            return false;
        }
        data.formData = inputs.serializeArray().concat(form.serializeArray());

        inputs.filter("input").prop("disabled", true);
    }).bind('fileuploadstart', function(e, data) {
        // Show progress data
        form.find('.fileupload-progress .progress-extended').show();
        form.find('.fileupload-progress .progress-end').hide();
    }).bind('fileuploadprogressall', function(e, data) {
        // Hide progress data and show processing
        if(data.total > 0 && data.loaded == data.total) {
            form.find('.fileupload-progress .progress-extended').hide();
            form.find('.fileupload-progress .progress-end').show();
        }
    }).bind('fileuploadadded fileuploadcompleted fileuploadfinished', function(e, data) {
        // Get uploaded and downloaded files and toggle Done button
        var filesToUploadCount = form.find('tr.template-upload').length;
        var $button = form.parents('.modal:first').find('button[data-bb-handler="main"]');

        if(filesToUploadCount == 0) {
            $button.removeAttr('disabled');
        } else {
            $button.attr('disabled', 'disabled');
        }
    });
    
};

/**
 * Clear Temporary Data ( Cleaning cached variables )
 */
lD.clearTemporaryData = function() {
    // Fix for remaining ckeditor elements or colorpickers
    $('.colorpicker').remove();
    $('.cke').remove();

    // Fix for remaining ckeditor elements or colorpickers
    lD.designerDiv.find('.colorpicker-element').colorpicker('destroy');

    // Hide open tooltips
    lD.designerDiv.find('[data-toggle="tooltip"]').tooltip('hide');

    // Remove text callback editor structure variables
    formHelpers.destroyCKEditor();
};

/**
 * Get element from the main object ( Layout )
 * @param {string} type
 * @param {number} id
 * @param {number} auxId
 */
lD.getElementByTypeAndId = function(type, id, auxId) {

    let element = {};

    if(type === 'layout') {
        element = lD.layout;
    } else if(type === 'region') {
        element = lD.layout.regions[id];
    } else if(type === 'widget') {
        element = lD.layout.regions[auxId].widgets[id];
    }

    return element;
};

/**
 * Call layout status
 */
lD.checkLayoutStatus = function() {
    
    const linkToAPI = urlsForApi.layout.status;
    let requestPath = linkToAPI.url;

    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', lD.layout.layoutId);

    $.ajax({
        url: requestPath,
        type: linkToAPI.type
    }).done(function(res) {

        if(!res.success) {
            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload(false);
            } else {
                // Just an error we dont know about
                if(res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }
            }
        } else {
            // Update layout status
            lD.layout.updateStatus(res.extra.status, res.html, res.extra.statusMessage);
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        // Output error to console
        console.error(jqXHR, textStatus, errorThrown);
    });
};

/**
 * Open object context menu
 * @param {object} obj - Target object
 * @param {object=} position - Page menu position
 */
lD.openContextMenu = function(obj, position = {x: 0, y: 0}) {

    let objId = $(obj).attr('id');
    let objType = $(obj).data('type');
    let objRegionId = null;

    if(objType == 'widget') {
        objRegionId = $(obj).data('widgetRegion');
    }

    // Get object
    let layoutObject = lD.getElementByTypeAndId(objType, objId, objRegionId);

    // Create menu and append to the designer div ( using the object extended with translations )
    lD.designerDiv.append(contextMenuTemplate(Object.assign(layoutObject, {trans: contextMenuTrans})));
    
    // Set menu position ( and fix page limits )
    let contextMenuWidth = lD.designerDiv.find('.context-menu').outerWidth();
    let contextMenuHeight = lD.designerDiv.find('.context-menu').outerHeight();

    let positionLeft = ((position.x + contextMenuWidth) > $(window).width()) ? (position.x - contextMenuWidth) : position.x;
    let positionTop = ((position.y + contextMenuHeight) > $(window).height()) ? (position.y - contextMenuHeight) : position.y;

    lD.designerDiv.find('.context-menu').offset({top: positionTop, left: positionLeft});

    // Initialize tooltips
    lD.common.reloadTooltips(lD.designerDiv.find('.context-menu'));

    // Click overlay to close menu
    lD.designerDiv.find('.context-menu-overlay').click((ev)=> {

        if($(ev.target).hasClass('context-menu-overlay')) {
            lD.designerDiv.find('.context-menu-overlay').remove();
        }
    });

    // Handle buttons
    lD.designerDiv.find('.context-menu .context-menu-btn').click((ev) => {
        let target = $(ev.currentTarget);

        if(target.data('action') == 'Delete') {
            let regionIdAux = '';
            if(objRegionId != null) {
                regionIdAux= objRegionId.split('region_')[1]
            }

            lD.deleteObject(objType, layoutObject[objType + 'Id'], regionIdAux);
        } else if(target.data('action') == 'Move') {
            // Move widget in the timeline
            lD.timeline.moveWidgetInRegion(layoutObject.regionId, layoutObject.id, target.data('actionType'));
        } else {
            layoutObject.editPropertyForm(target.data('property'), target.data('propertyType'));
        }

        // Remove context menu
        lD.designerDiv.find('.context-menu-overlay').remove();
    });
};

/**
 * Load user preference
 */
lD.loadAndSavePref = function(prefToLoad, defaultValue = 0) {

    // Load using the API
    const linkToAPI = urlsForApi.user.getPref;

    // Request elements based on filters
    let self = this;
    $.ajax({
        url: linkToAPI.url + '?preference=' + prefToLoad,
        type: linkToAPI.type
    }).done(function(res) {

        if(res.success) {
            if(res.data.option == prefToLoad) {
                lD[prefToLoad] = res.data.value;
            } else {
                lD[prefToLoad] = defaultValue;
            }
        } else {
            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload(false);
            } else {
                // Just an error we dont know about
                if(res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }
            }
        }

    }).catch(function(jqXHR, textStatus, errorThrown) {
        console.error(jqXHR, textStatus, errorThrown);
        toastr.error(errorMessagesTrans.userLoadPreferencesFailed);
    });
};

/**
 * Reset tour
 */
lD.resetTour = function() {
    layoutDesignerTour.restart();
    layoutDesignerTour.goTo(0);
    toastr.info(editorsTrans.resetTourNotification);
};
