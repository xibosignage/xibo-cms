/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
const Layout = require('../layout-editor/layout.js');
const Navigator = require('../layout-editor/navigator.js');
const Timeline = require('../layout-editor/timeline.js');
const Viewer = require('../layout-editor/viewer.js');
const PropertiesPanel = require('../editor-core/properties-panel.js');
const Drawer = require('../layout-editor/drawer.js');
const Manager = require('../editor-core/manager.js');
const Toolbar = require('../editor-core/toolbar.js');
const Topbar = require('../editor-core/topbar.js');
const Bottombar = require('../editor-core/bottombar.js');

// Common funtions/tools
const Common = require('../editor-core/common.js');

// Include CSS
require('../style/common.scss');
require('../style/designer.scss');
require('../style/toolbar.scss');
require('../style/topbar.scss');
require('../style/bottombar.scss');

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

    // Layout
    layout: {},

    // Timeline
    timeline: {},

    // Manager
    manager: {},

    // Viewer
    viewer: {},

    // Designer DOM div
    editorContainer: $('#layout-editor'),

    // Selected object
    selectedObject: {},

    // Bottom toolbar
    toolbar: {},

    // Top toolbar
    topbar: {},

    // Properties Panel
    propertiesPanel: {},

    // Drawer
    drawer: {},

    folderId: '',

    navigatorMode: false,
};

// Get Xibo app
window.getXiboApp = function() {
    return lD;
};

// Load Layout and build app structure
$(document).ready(function() {
    // Get layout id
    const layoutId = lD.editorContainer.attr("data-layout-id");

    lD.common.showLoadingScreen();

    // Append loading html to the main div
    lD.editorContainer.html(loadingTemplate());

    // Change toastr positioning
    toastr.options.positionClass = 'toast-top-center';

    // Load layout through an ajax request
    $.get(urlsForApi.layout.get.url + '?layoutId=' + layoutId + '&embed=regions,playlists,widgets,widget_validity,tags,permissions,actions')
        .done(function(res) {

            if(res.data != null && res.data.length > 0) {
                // Append layout html to the main div
                lD.editorContainer.html(
                    designerMainTemplate(
                        {
                            trans: layoutEditorTrans,
                            exitURL: urlsForApi.layout.list.url
                        }
                    )
                );

                // Create layout
                lD.layout = new Layout(layoutId, res.data[0]);

                // Update main object id
                lD.mainObjectId = lD.layout.layoutId;

                // get Layout folder id
                lD.folderId = lD.layout.folderId;

                // Initialize timeline
                lD.timeline = new Timeline(
                    lD,
                    lD.editorContainer.find('#layout-timeline')
                );

                // Initialize manager
                lD.manager = new Manager(
                    lD,
                    lD.editorContainer.find('#layout-manager'),
                    false // (serverMode == 'Test') Turn of manager visibility for now
                );

                // Initialize viewer
                lD.viewer = new Viewer(
                    lD,
                    lD.editorContainer.find('#layout-viewer')
                );

                // Initialise drawer
                lD.drawer = new Drawer(
                    lD,
                    lD.editorContainer.find('#actions-drawer'),
                    res.data[0].drawers
                );

                // Initialize bottom toolbar ( with custom buttons )
                lD.toolbar = new Toolbar(
                    lD,
                    lD.editorContainer.find('#layout-editor-toolbar'),
                    // Custom actions
                    {
                        deleteSelectedObjectAction: lD.deleteSelectedObject,
                        deleteDraggedObjectAction: lD.deleteDraggedObject
                    }
                );

                // Initialize top topbar
                lD.topbar = new Topbar(
                    lD,
                    lD.editorContainer.find('#layout-editor-topbar'),
                    // Custom dropdown options
                    [
                        {
                            id: 'publishLayout',
                            title: layoutEditorTrans.publishTitle,
                            logo: 'fa-check-square-o',
                            class: 'btn-success',
                            action: lD.showPublishScreen,
                            inactiveCheck: function() {
                                return (lD.layout.editable == false);
                            },
                            inactiveCheckClass: 'd-none',
                        },
                        {
                            id: 'checkoutLayout',
                            title: layoutEditorTrans.checkoutTitle,
                            logo: 'fa-edit',
                            class: 'btn-success',
                            action: lD.checkoutLayout,
                            inactiveCheck: function() {
                                return (lD.layout.editable == true);
                            },
                            inactiveCheckClass: 'd-none',
                        },
                        {
                            id: 'discardLayout',
                            title: layoutEditorTrans.discardTitle,
                            logo: 'fa-times-circle-o',
                            action: lD.showDiscardScreen,
                            inactiveCheck: function() {
                                return (lD.layout.editable == false);
                            },
                            inactiveCheckClass: 'd-none',
                        },
                        {
                            id: 'deleteLayout',
                            title: layoutEditorTrans.deleteTitle,
                            logo: 'fa-times-circle-o',
                            class: 'btn-danger',
                            action: lD.showDeleteScreen,
                            inactiveCheck: function() {
                                return (lD.layout.editable == true || lD.layout.deletePermission == false);
                            },
                            inactiveCheckClass: 'd-none',
                        },
                        {
                            id: 'scheduleLayout',
                            title: layoutEditorTrans.scheduleTitle,
                            logo: 'fa-clock-o',
                            action: lD.showScheduleScreen,
                            inactiveCheck: function() {
                                return (lD.layout.editable == true || lD.layout.scheduleNowPermission == false);
                            },
                            inactiveCheckClass: 'd-none',
                        },
                        {
                            id: 'saveTemplate',
                            title: layoutEditorTrans.saveTemplateTitle,
                            logo: 'fa-floppy-o',
                            action: lD.showSaveTemplateScreen,
                            inactiveCheck: function() {
                                return (lD.layout.editable == true);
                            },
                            inactiveCheckClass: 'd-none',
                        },
                        {
                            id: 'unlockLayout',
                            title: layoutEditorTrans.unlockTitle,
                            logo: 'fa-unlock',
                            class: 'btn-info show-on-lock',
                            action: lD.showUnlockScreen
                        }
                    ],
                    // Custom actions
                    {},
                    // jumpList
                    {
                        searchLink: urlsForApi.layout.get.url,
                        designerLink: urlsForApi.layout.designer.url,
                        layoutId: lD.layout.layoutId,
                        layoutName: lD.layout.name,
                        callback: lD.reloadData
                    },
                    true // Show Options
                );

                // Initialize bottom toolbar ( with custom buttons )
                lD.bottombar = new Bottombar(
                    lD,
                    lD.editorContainer.find('#layout-editor-bottombar')
                );

                // Initialize properties panel
                lD.propertiesPanel = new PropertiesPanel(
                    lD,
                    lD.editorContainer.find('#properties-panel')
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
                lD.selectedObject = lD.layout;
                lD.selectedObject.type = 'layout';

                // Refresh the designer containers
                lD.refreshDesigner(true);

                // Load preferences
                lD.loadPrefs();
            } else {
                // Login Form needed?
                if(res.login) {
                    window.location.href = window.location.href;
                    location.reload();
                } else {
                    lD.showErrorMessage();
                }
            }

            lD.common.hideLoadingScreen();
        }).fail(function(jqXHR, textStatus, errorThrown) {

            // Output error to console
            console.error(jqXHR, textStatus, errorThrown);

            lD.showErrorMessage();
        }
    );

    // Handle keyboard keys
    $('body').off('keydown').keydown(function(handler) {
        if($(handler.target).is($('body'))) {

            if(handler.key == 'Delete' && lD.readOnlyMode == false) {
                lD.deleteSelectedObject();
            }
        }
    });

    // Refresh some modules on window resize
    $(window).on('resize.designer', _.debounce(function(e) {
        if(e.target === window) {

            // Refresh navigators or viewer
            if(lD.navigatorMode) {
                lD.renderContainer(lD.navigator);
            } else {
                lD.renderContainer(lD.viewer, lD.selectedObject);
            }

            lD.renderContainer(lD.timeline);
            lD.renderContainer(lD.drawer);
        }
    }, 250));

    if (window.addEventListener) {
        window.addEventListener("message", lD.handleMessage);
    } else {
        window.attachEvent("onmessage", lD.handleMessage);
    }
});

/**
 * Select a layout object (layout/region/widget)
 * @param {object=} obj - Object to be selected
 * @param {bool=} forceSelect - Select object even if it was already selected
 * @param {object =} [options] - selectObject options
 * @param {number=} [options.positionToAdd = null] - Order position for widget
 */
lD.selectObject = function(obj = null, forceSelect = false, {positionToAdd = null} = {}) {
    // Clear rogue tooltips
    lD.common.clearTooltips();

    // If there is a selected card, use the drag&drop simulate to add that item to a object
    if(!$.isEmptyObject(this.toolbar.selectedCard)) {

        // If selected card has the droppable type or "all"
        if([obj.data('type'), 'all'].indexOf($(this.toolbar.selectedCard).attr('drop-to')) !== -1) {

            // Get card object
            const card = this.toolbar.selectedCard[0];

            // Deselect cards and drop zones
            this.toolbar.deselectCardsAndDropZones();

            // Simulate drop item add
            this.dropItemAdd(obj, card, {positionToAdd: positionToAdd});
        }

    } else if(!$.isEmptyObject(this.toolbar.selectedQueue)) { // If there's a selected queue, use the drag&drop simulate to add those items to a object
        if(obj.data('type') == 'region') {
            const droppableId = $(obj).attr('id');
            let playlistId;

            if(droppableId == 'actions-drawer-content') {
                playlistId = lD.layout.drawer.playlists.playlistId;
            } else {
                playlistId = lD.layout.regions[droppableId].playlists.playlistId;
            }

            lD.importFromProvider(this.toolbar.selectedQueue).then((res) =>  {
                // Add media queue to playlist
                lD.addMediaToPlaylist(playlistId, res, positionToAdd);
            }).catch(function() {
                toastr.error(errorMessagesTrans.importingMediaFailed);
            });
        }

        // Deselect cards and drop zones
        this.toolbar.deselectCardsAndDropZones();
    } else {
        // Get object properties from the DOM ( or set to layout if not defined )
        const newSelectedId = (obj === null) ? this.layout.id : obj.attr('id');
        let newSelectedType = (obj === null) ? 'layout' : obj.data('type');
        let newSelectedParentType = (obj === null) ? 'layout' : obj.data('parentType');

        const oldSelectedId = this.selectedObject.id;
        const oldSelectedType = this.selectedObject.type;

        // Unselect the previous selectedObject object if still selected
        if( this.selectedObject.selected ) {
            switch(this.selectedObject.type) {
                case 'region':
                    if(this.layout.regions[this.selectedObject.id]) {
                        this.layout.regions[this.selectedObject.id].selected = false;
                    }
                    break;

                case 'widget':
                    if(this.selectedObject.drawerWidget) {
                        if(this.layout.drawer.widgets[this.selectedObject.id]) {
                            this.layout.drawer.widgets[this.selectedObject.id].selected = false;
                        }
                    } else {
                        if(this.layout.regions[this.selectedObject.regionId].widgets[this.selectedObject.id]) {
                            this.layout.regions[this.selectedObject.regionId].widgets[this.selectedObject.id].selected = false;
                        }
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
        if(oldSelectedId != newSelectedId || oldSelectedType != newSelectedType || forceSelect) {
            // Save the new selected object
            if(newSelectedType === 'region') {
                // If we're not in the navigator edit and the region has widgets, select the first one
                if(!forceSelect && !this.navigatorMode && !$.isEmptyObject(this.layout.regions[newSelectedId].widgets)) {
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
                    if(this.navigatorMode) {
                        // In navigator mode, the region is marked as selected, viewer mode we only
                        // use the region as selected Object to handle widget selection
                        this.layout.regions[newSelectedId].selected = true;
                    }

                    this.selectedObject = this.layout.regions[newSelectedId];
                }
            } else if(newSelectedType === 'widget') {
                // Close navigator mode when selecting a widget
                if(lD.navigatorMode) {
                    lD.toggleNavigatorEditing(false, false);
                }

                if(newSelectedParentType === 'drawer') {
                    this.layout.drawer.widgets[newSelectedId].selected = true;
                    this.selectedObject = this.layout.drawer.widgets[newSelectedId];
                } else {
                    this.layout.regions[obj.data('widgetRegion')].widgets[newSelectedId].selected = true;
                    this.selectedObject = this.layout.regions[obj.data('widgetRegion')].widgets[newSelectedId];
                }
            }

            this.selectedObject.type = newSelectedType;
        }

        // Refresh the designer containers
        lD.refreshDesigner();
    }
};

/**
 * Refresh designer
 * @param {boolean} [renderToolbar=false] - Render toolbar
 */
lD.refreshDesigner = function(renderToolbar = false) {
    // Remove temporary data
    this.clearTemporaryData();

    // Render containers with layout ( default )
    (renderToolbar) && this.renderContainer(this.toolbar);
    this.renderContainer(this.topbar);

    // Refresh bottom bar if no object is selected ( to avoid looping )
    (this.selectedObject.type === "layout") && this.renderContainer(this.bottombar, this.selectedObject);

    this.renderContainer(this.manager);
    this.renderContainer(this.propertiesPanel, this.selectedObject);
    this.renderContainer(this.navigator, this.selectedObject);
    this.renderContainer(this.viewer, this.selectedObject);
    this.renderContainer(this.timeline);
    this.renderContainer(this.drawer);
};

/**
 * Reload API data and replace the layout structure with the new value
 * @param {object} layout - previous layout
 */
lD.reloadData = function(layout, refreshBeforeSelect = false, captureThumbnail = false) {

    const layoutId = (typeof layout.layoutId == 'undefined') ? layout : layout.layoutId;

    lD.common.showLoadingScreen();

    $.get(urlsForApi.layout.get.url + '?layoutId=' + layoutId + "&embed=regions,playlists,widgets,widget_validity,tags,permissions,actions")
        .done(function(res) {
            if(res.data != null && res.data.length > 0) {
                lD.layout = new Layout(layoutId, res.data[0]);

                // Update main object id
                lD.mainObjectId = lD.layout.layoutId;
                // get Layout folder id
                lD.folderId = lD.layout.folderId;

                // To select an object that still doesn't exist
                if(refreshBeforeSelect) {
                    lD.refreshDesigner();
                    // Higlight widget on refresh
                    if(lD.selectedObject.type == 'widget') {
                        lD.timeline.highlightOnLoad = lD.selectedObject;
                    }

                    // Make the timeline scroll to the new widget on load
                    lD.timeline.scrollOnLoad = lD.selectedObject;
                }

                // Select the same object ( that will refresh the layout too )
                const selectObjectId = lD.selectedObject.id;
                lD.selectedObject = {};

                lD.selectObject($('#' + selectObjectId));

                // Reload the form helper connection
                formHelpers.setup(lD, lD.layout);

                // Check layout status
                lD.checkLayoutStatus();

                // Add thumbnail
                captureThumbnail && lD.uploadThumbnail();
            } else {
                // Login Form needed?
                if(res.login) {
                    window.location.href = window.location.href;
                    location.reload();
                } else {
                    lD.showErrorMessage();
                }
            }

            lD.common.hideLoadingScreen();
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

    // Deselect previous selected object
    lD.selectObject();

    $.ajax({
        url: requestPath,
        type: linkToAPI.type
    }).done(function(res) {
        if(res.success) {
            bootbox.hideAll();

            toastr.success(res.message);

            // Turn off read only mode
            lD.readOnlyMode = false;

            // Hide read only message
            lD.editorContainer.removeClass('view-mode');
            lD.editorContainer.find('#read-only-message').remove();

            // Reload layout
            lD.reloadData(res.data, false, true);

            // Refresh toolbar
            lD.toolbar.render();

            lD.common.hideLoadingScreen();
        } else {
            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload();
            } else {
                toastr.error(res.message);
            }

            lD.common.hideLoadingScreen();
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

        if(res.success) {
            bootbox.hideAll();

            toastr.success(res.message);

            // Redirect to the new published layout ( read only mode )
            window.location.href = urlsForApi.layout.designer.url.replace(':id', res.data.layoutId) + '?vM=1';
        } else {
            lD.common.hideLoadingScreen();

            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload();
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
 * Discard layout
 */
lD.discardLayout = function() {
    const linkToAPI = urlsForApi.layout.discard;
    let requestPath = linkToAPI.url;

    lD.common.showLoadingScreen();

    // Deselect previous selected object
    lD.selectObject();

    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', lD.layout.parentLayoutId);

    const serializedData = $('#layoutDiscardForm').serialize();

    $.ajax({
        url: requestPath,
        type: linkToAPI.type,
        data: serializedData
    }).done(function(res) {

        if(res.success) {
            bootbox.hideAll();

            toastr.success(res.message);

            // Redirect to the layout grid
            window.location.href = urlsForApi.layout.list.url;
        } else {

            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload();
            } else {
                toastr.error(res.message);

                // Close dialog
                bootbox.hideAll();
            }
        }

        lD.common.hideLoadingScreen();
    }).fail(function(jqXHR, textStatus, errorThrown) {
        lD.common.hideLoadingScreen();

        // Output error to console
        console.error(jqXHR, textStatus, errorThrown);
    });
}


/**
 * Discard layout
 */
 lD.deleteLayout = function() {
    const linkToAPI = urlsForApi.layout.delete;
    let requestPath = linkToAPI.url;

    lD.common.showLoadingScreen();

    // Deselect previous selected object
    lD.selectObject();

    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', lD.layout.layoutId);

    const serializedData = $('#layoutDeleteForm').serialize();

    $.ajax({
        url: requestPath,
        type: linkToAPI.type,
        data: serializedData
    }).done(function(res) {

        if(res.success) {
            bootbox.hideAll();

            toastr.success(res.message);

            // Redirect to the layout grid
            window.location.href = urlsForApi.layout.list.url;
        } else {

            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload();
            } else {
                toastr.error(res.message);

                // Close dialog
                bootbox.hideAll();
            }
        }

        lD.common.hideLoadingScreen();
    }).fail(function(jqXHR, textStatus, errorThrown) {
        lD.common.hideLoadingScreen();

        // Output error to console
        console.error(jqXHR, textStatus, errorThrown);
    });
}

/**
 * Read Only Mode
 */
lD.welcomeScreen = function() {

    // Turn on read only mode
    lD.readOnlyMode = true;

    bootbox.dialog({
        message: layoutEditorTrans.welcomeModalMessage,
        className: "welcome-screen-modal",
        size: 'large',
        closeButton: false,
        buttons: {
            checkout: {
                label: layoutEditorTrans.checkoutTitle,
                className: "btn-success btn-bb-checkout",
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
                label: layoutEditorTrans.viewModeTitle,
                className: "btn-white btn-bb-view",
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
    lD.editorContainer.addClass('view-mode');

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
 * @param {boolean} reload - flag to force reload data
 */
lD.toggleNavigatorEditing = function(enable, reload = true) {

    // Unselect objects ( select layout )
    this.selectObject();

    if(enable) {
        // Fix to prevent going into region edit mode on a publish layout
        if(lD.readOnlyMode) {
            return;
        }

        lD.navigatorMode = true;

        // Create a new navigator instance
        this.navigator = new Navigator(
            lD,
            this.editorContainer.find('#layout-navigator-content')
        );

        // Show navigator edit div
        this.editorContainer.find('#layout-navigator').css('display', 'block');

        // Hide viewer div
        this.editorContainer.find('#layout-viewer-container').css('display', 'none');

        // Render navigator
        this.renderContainer(this.navigator, this.selectedObject);

        // Render bottombar
        this.renderContainer(this.bottombar, this.selectedObject);

        toastr.info(layoutEditorTrans.regionEditModeMessage);
    } else {
        lD.navigatorMode = false;

        // Reload designer
        if(reload) {
            this.reloadData(lD.layout);
        }

        // Clean variable
        this.navigator = {};

        // Clean object HTML and hide div
        this.editorContainer.find('#layout-navigator-content').empty();
        this.editorContainer.find('#layout-navigator').css('display', 'none');

        // Show viewer div
        this.editorContainer.find('#layout-viewer-container').css('display', 'block');
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

    lD.editorContainer.html(htmlError);
};

/**
 * Layout checkout screen
 */
lD.showCheckoutScreen = function() {

    bootbox.dialog({
        title: layoutEditorTrans.checkoutTitle + ' ' + lD.layout.name,
        message: layoutEditorTrans.checkoutMessage,
        size: 'large',
        buttons: {
            checkout: {
                label: layoutEditorTrans.checkoutTitle,
                className: "btn-success btn-bb-checkout",
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
    // Deselect all objects before opening the form
    lD.selectObject();

    lD.loadFormFromAPI('publishForm', lD.layout.parentLayoutId, "formHelpers.setupCheckboxInputFields($('#layoutPublishForm'), '#publishNow', '', '.publish-date-control'); lD.uploadThumbnail($('#layoutPublishForm #publishPreview'));", "lD.publishLayout();");
};

/**
 * Layout publish screen
 */
lD.showDiscardScreen = function() {
    lD.loadFormFromAPI('discardForm', lD.layout.parentLayoutId, '', 'lD.discardLayout();');
};

/**
 * Layout schedule screen
 */
lD.showScheduleScreen = function() {
    lD.loadFormFromAPI('schedule', lD.layout.campaignId);
};

/**
 * Layout delete screen
 */
 lD.showDeleteScreen = function() {
    lD.loadFormFromAPI('deleteForm', lD.layout.layoutId, '', 'lD.deleteLayout();', [editorsTrans.retire]);
};

/**
 * Layout save template screen
 */
lD.showSaveTemplateScreen = function() {
    lD.loadFormFromAPI('saveTemplate', lD.layout.layoutId, "initJsTreeAjax('#container-folder-form-tree', 'templateAddForm', true, 600);");
};

/**
 * Load form from the API
 */
lD.loadFormFromAPI = function(type, id = null, apiFormCallback = null, mainActionCallback = null, buttonsToRemove = []) {
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
                    className: 'btn-white'
                }
            };

            // Get buttons from form
            for(var button in res.buttons) {
                if(res.buttons.hasOwnProperty(button)) {
                    if (res.buttons[button] != "XiboDialogClose()") {
                        let buttonType = 'btn-white';
                        let mainButtonAction = false;

                        if(button === translations.save || button === editorsTrans.publish || button === editorsTrans.discard || button === editorsTrans.yes) {
                            buttonType = 'btn-primary';
                            mainButtonAction = true;
                        }

                        let url = res.buttons[button];

                        // Only add button if it's not in the buttons to remove list
                        if (buttonsToRemove.indexOf(button) == -1) {
                            generatedButtons[button] = {
                                label: button,
                                className: buttonType + ' btn-bb-' + button,
                                callback: function() {
                                    // Call global function by the function name
                                    if (mainActionCallback != null && mainButtonAction) {
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
            }

            // Create dialog
            let dialog = bootbox.dialog({
                className: 'second-dialog',
                title: res.dialogTitle,
                message: res.html,
                size: 'large',
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
                location.reload();
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
        toastr.success(res.message);

        // Refresh designer according to local or API revert
        if(res.localRevert) {
            lD.refreshDesigner();
        } else {
            lD.reloadData(lD.layout);
        }

        lD.common.hideLoadingScreen('undoLastAction');
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
            (lD.selectedObject.drawerWidget) ? lD.layout.drawer.regionId : lD.layout.regions[lD.selectedObject.regionId].regionId
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
        if(draggable.data('parentType') == 'drawer') {
            objectId = lD.layout.drawer.widgets[draggable.data('widgetId')].widgetId;
            objectAuxId = lD.layout.drawer.regionId;
        } else {
            objectId = lD.layout.regions[draggable.data('widgetRegion')].widgets[draggable.data('widgetId')].widgetId;
            objectAuxId = lD.layout.regions[draggable.data('widgetRegion')].regionId;
        }
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
            size: 'large',
            buttons: {
                cancel: {
                    label: editorsTrans.no,
                    className: 'btn-white btn-bb-cancel'
                },
                confirm: {
                    label: editorsTrans.yes,
                    className: 'btn-danger btn-bb-confirm',
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
                            // Reset timeline zoom
                            lD.timeline.resetZoom();

                            // Behavior if successful
                            toastr.success(res.message);
                            lD.reloadData(lD.layout);

                            lD.common.hideLoadingScreen('deleteObject');
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

        if(widgetToDelete.isRegionSpecific()) {
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
                            location.reload();
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
 * @param {object/number=} [options.positionToAdd = null] - Position object {top, left} for region, and order position for widget
 */
lD.dropItemAdd = function(droppable, draggable, {positionToAdd = null} = {}) {
    const droppableId = $(droppable).attr('id');
    const draggableType = $(draggable).data('type');
    const draggableSubType = $(draggable).data('subType');

    // If the draggable is from another toolbar, stop adding
    if($(draggable).parents('#layout-editor-toolbar').length === 0) {
        return;
    }

    let playlistId;

    if(draggableType == 'media') { // Adding media from search tab to a region

        // Get playlist Id
        if(droppableId == 'actions-drawer-content') {
            playlistId = lD.layout.drawer.playlists.playlistId;
        } else {
            playlistId = lD.layout.regions[droppableId].playlists.playlistId;
        }

        const mediaId = $(draggable).data('mediaId');

        if($(draggable).hasClass('from-provider')) {
            lD.importFromProvider([$(draggable).data('providerData')]).then((res) =>  {
                lD.addMediaToPlaylist(playlistId, res, positionToAdd);
            }).catch(function() {
                toastr.error(errorMessagesTrans.importingMediaFailed);
            });
        } else {
            lD.addMediaToPlaylist(playlistId, mediaId, positionToAdd);
        }
    } else if(draggableType == 'module') { // Add widget/module

        // Get regionSpecific property
        const moduleData = $(draggable).data();

        // Get playlist Id
        if(droppableId == 'actions-drawer-content') {
            playlistId = lD.layout.drawer.playlists.playlistId;
        } else {
            playlistId = lD.layout.regions[droppableId].playlists.playlistId;

            // Select region ( and avoid deselect if region was already selected )
            lD.selectObject($(droppable), true);
        }

        lD.addModuleToPlaylist(playlistId, draggableSubType, moduleData, positionToAdd);
    }
};

/**
 * Get the class name for the upload dialog, used by form-helpers.
 * @return {null}
 */
lD.getUploadDialogClassName = function() {
    return null;
};

/**
 * Add module to playlist
 * @param {number} playlistId
 * @param {string} moduleType
 * @param {object} moduleData
 * @param {number=} addToPosition
 */
lD.addModuleToPlaylist = function(playlistId, moduleType, moduleData, addToPosition = null) {

    if(moduleData.regionSpecific == 0) { // Upload form if not region specific

        const validExt = moduleData.validExt.replace(/,/g, "|");

        // Close the current dialog
        bootbox.hideAll();

        openUploadForm({
            url: libraryAddUrl,
            title: uploadTrans.uploadMessage,
            animateDialog: false,
            initialisedBy: "layout-designer-upload",
            buttons: {
                viewLibrary: {
                    label: uploadTrans.viewLibrary,
                    className: "btn-white btn-bb-viewlibrary",
                    callback: function() {
                        lD.toolbar.openNewTabAndSearch(moduleType);
                    }
                },
                main: {
                    label: translations.done,
                    className: "btn-primary btn-bb-main",
                    callback: function() {
                        lD.timeline.resetZoom();
                        lD.reloadData(lD.layout);
                    }
                }
            },
            templateOptions: {
                trans: uploadTrans,
                upload: {
                    maxSize: moduleData.maxSize,
                    maxSizeMessage: moduleData.maxSizeMessage,
                    validExtensionsMessage: translations.validExtensions.replace("%s", moduleData.validExt),
                    validExt: validExt
                },
                playlistId: playlistId,
                displayOrder: addToPosition,
                currentWorkingFolderId: lD.folderId,
                showWidgetDates: true,
                folderSelector: true
            }
        }).attr('data-test', 'uploadFormModal');

    } else { // Add widget to a region

        const linkToAPI = urlsForApi.playlist.addWidget;

        let requestPath = linkToAPI.url;

        lD.common.showLoadingScreen('addModuleToPlaylist');

        // Replace type
        requestPath = requestPath.replace(':type', moduleType);

        // Replace playlist id
        requestPath = requestPath.replace(':id', playlistId);

        // Set position to add if selected
        let addOptions = null;
        if(addToPosition != null) {
            addOptions = {
                displayOrder: addToPosition
            };
        }

        lD.manager.addChange(
            'addWidget',
            'playlist', // targetType
            playlistId,  // targetId
            null,  // oldValues
            addOptions, // newValues
            {
                updateTargetId: true,
                updateTargetType: 'widget',
                customRequestPath: {
                    url: requestPath,
                    type: linkToAPI.type
                }
            }
        ).then((res) => { // Success
            // Behavior if successful
            toastr.success(res.message);

            lD.timeline.resetZoom();

            // The new selected object as the id based on the previous selected region
            lD.selectedObject.id = 'widget_' + lD.selectedObject.regionId + '_' + res.data.widgetId;
            lD.selectedObject.type = 'widget';
            lD.reloadData(lD.layout, true);

            lD.common.hideLoadingScreen('addModuleToPlaylist');
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
 * @param {Array.<number>} media
 * @param {number=} addToPosition
 */
lD.addMediaToPlaylist = function(playlistId, media, addToPosition = null) {
    // Get media Id
    let mediaToAdd = {};

    if(Array.isArray(media)) {
        if(media.length == 0) {
            return;
        }
        mediaToAdd = {
            media: media
        };
    } else {
        mediaToAdd = {
            media: [
                media
            ]
        };
    }

    // Check if library duration options exists and add it to the query
    if(lD.useLibraryDuration != undefined) {
        mediaToAdd.useDuration = (lD.useLibraryDuration == "1");
    }

    lD.common.showLoadingScreen('addMediaToPlaylist');

    // Set position to add if selected
    if(addToPosition != null) {
        mediaToAdd.displayOrder = addToPosition;
    }

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
        // Behavior if successful
        toastr.success(res.message);

        // The new selected object as the id based on the previous selected region
        lD.selectedObject.id = 'widget_' + res.data.regionId + '_' + res.data.newWidgets[0].widgetId;
        lD.selectedObject.type = 'widget';

        lD.timeline.resetZoom();
        lD.reloadData(lD.layout, true);

        lD.common.hideLoadingScreen('addMediaToPlaylist');
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
 * Clear Temporary Data ( Cleaning cached variables )
 */
lD.clearTemporaryData = function() {
    // Fix for remaining ckeditor elements or colorpickers
    $('.colorpicker').remove();
    $('.cke').remove();

    // Fix for remaining ckeditor elements or colorpickers
    destroyColorPicker(lD.editorContainer.find('.colorpicker-element'));

    // Clean and hide inline editor controls
    lD.editorContainer.find('#inline-editor-templates').html('');

    // Hide open tooltips
    lD.editorContainer.find('.tooltip').remove();

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
    } else if(type === 'drawer') {
        element = lD.layout.drawer;
    } else if(type === 'widget') {
        if(lD.layout.drawer.id != undefined && (lD.layout.drawer.id == auxId || auxId == 'drawer')) {
            element = lD.layout.drawer.widgets[id];
        } else {
            element = lD.layout.regions[auxId].widgets[id];
        }
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
                location.reload();
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

            if((Array.isArray(res.extra.isLocked) && res.extra.isLocked.length == 0)) {
                // isLocked is not defined
                lD.toggleLockedMode(false);

                // Remove locked class to main container
                lD.editorContainer.removeClass('locked');
            } else {
                // Add locked class to main container
                lD.editorContainer.addClass('locked');

                // Toggle locked mode according to the user flag
                lD.toggleLockedMode(res.extra.isLocked.lockedUser, moment(res.extra.isLocked.expires, systemDateFormat).format(jsDateFormat));
            }
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        // Output error to console
        console.error(jqXHR, textStatus, errorThrown);
    });
};

/**
 * Call layout status
 */
lD.openPlaylistEditor = function(playlistId, region) {

    let requestPath = playlistEditorUrl;

    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', playlistId);

    // Deselect previous selected object
    lD.selectObject();

    $.ajax({
        url: requestPath,
        type: 'GET'
    }).done(function(res) {

        if(!res.success) {
            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload();
            } else {
                // Just an error we dont know about
                if(res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }
            }
        } else {
            // Create or load container
            let $editor = ($('#editor-container').length > 0) ? $('#editor-container') : $('<div/>').attr('id', 'editor-container').appendTo(lD.editorContainer.parent());

            // Populate container
            $editor.html(res.html);

            // Hide layout designer toolbar
            lD.toolbar.DOMObject.hide();

            // Attach region id to editor data
            $editor.data('regionObj', region);

            // On close, remove container and refresh designer
            $editor.find('.editor-modal-close').attr('onclick', '').on('click', function() {

                // Close playlist editor
                pE.close();

                // Remove region id from data
                $editor.removeData('regionObj');

                // Show layout designer toolbar
                lD.toolbar.DOMObject.show();

                // Set the first run flag of the toolbar as true to reload the changed from the playlistEditor toolbar
                lD.toolbar.firstRun = true;

                // Reload data
                lD.reloadData(lD.layout);
            });
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
    lD.editorContainer.append(contextMenuTemplate(Object.assign(layoutObject, {trans: contextMenuTrans})));

    // Set menu position ( and fix page limits )
    let contextMenuWidth = lD.editorContainer.find('.context-menu').outerWidth();
    let contextMenuHeight = lD.editorContainer.find('.context-menu').outerHeight();

    let positionLeft = ((position.x + contextMenuWidth) > $(window).width()) ? (position.x - contextMenuWidth) : position.x;
    let positionTop = ((position.y + contextMenuHeight) > $(window).height()) ? (position.y - contextMenuHeight) : position.y;

    lD.editorContainer.find('.context-menu').offset({top: positionTop, left: positionLeft});

    // Click overlay to close menu
    lD.editorContainer.find('.context-menu-overlay').click((ev)=> {

        if($(ev.target).hasClass('context-menu-overlay')) {
            lD.editorContainer.find('.context-menu-overlay').remove();
        }
    });

    // Handle buttons
    lD.editorContainer.find('.context-menu .context-menu-btn').click((ev) => {
        let target = $(ev.currentTarget);

        if(target.data('action') == 'Delete') {
            let regionIdAux = '';
            if(objRegionId != null) {
                regionIdAux= objRegionId.split('region_')[1];
            }

            lD.deleteObject(objType, layoutObject[objType + 'Id'], regionIdAux);
        } else if(target.data('action') == 'Move') {
            // Move widget in the timeline
            lD.timeline.moveWidgetInRegion(layoutObject.regionId, layoutObject.id, target.data('actionType'));
        } else {
            layoutObject.editPropertyForm(target.data('property'), target.data('propertyType'));
        }

        // Remove context menu
        lD.editorContainer.find('.context-menu-overlay').remove();
    });
};

/**
 * Load user preference
 */
lD.loadAndSavePref = function(prefToLoad, defaultValue = 0) {

    // Load using the API
    const linkToAPI = urlsForApi.user.getPref;

    // Request elements based on filters
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
                    console.warn(res);
                } else {
                    console.warn(res.message);
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
    if(localStorage.tour_playing == undefined) {
        if(cmsTours.layoutEditorTour.ended()) {
            cmsTours.layoutEditorTour.restart();
        } else {
            cmsTours.layoutEditorTour.start();
        }
    }
    toastr.info(editorsTrans.resetTourNotification);
};

/**
 * Locked mode
 */
lD.toggleLockedMode = function(enable = true, expiryDate = '') {
    if(enable && !lD.readOnlyMode) {

        // Enable overlay
        let $customOverlay = lD.editorContainer.find('#lockedOverlay');
        let $lockedMessage = $customOverlay.find('#lockedLayoutMessage');

        const lockedMainMessage = layoutEditorTrans.lockedModeMessage.replace('[expiryDate]', expiryDate);

        if($customOverlay.length == 0) {
            $customOverlay = $('.custom-overlay').clone();
            $customOverlay.attr('id', 'lockedOverlay').addClass('locked').show();
            $customOverlay.appendTo(lD.editorContainer);

            // Create the read only alert message
            $lockedMessage = $('<div id="lockedLayoutMessage" class="alert alert-warning text-center" role="alert"></div>');

            // Prepend the element to the custom overlay
            $customOverlay.after($lockedMessage);
        }

        // Update locked overlay message content
        $lockedMessage.html('<strong>' + layoutEditorTrans.lockedModeTitle + '</strong>&nbsp;' + lockedMainMessage);

        // Add locked class to main container
        lD.editorContainer.addClass('locked-for-user');
    } else {
        // Remove overlay
        lD.editorContainer.find('#lockedOverlay').remove();

        // Remove message
        lD.editorContainer.find('#lockedLayoutMessage').remove();

        // Remove locked class from main container
        lD.editorContainer.removeClass('locked-for-user');
    }
};

/**
 * Layout unlock screen
 */
lD.showUnlockScreen = function() {

    bootbox.dialog({
        title: layoutEditorTrans.unlockTitle,
        message: layoutEditorTrans.unlockMessage,
        size: 'large',
        buttons: {
            unlock: {
                label: layoutEditorTrans.unlockTitle,
                className: "btn-info btn-bb-unlock",
                callback: function(res) {

                    $(res.currentTarget).append('&nbsp;<i class="fa fa-cog fa-spin"></i>');

                    lD.unlockLayout();

                    // Prevent the modal to close ( close only when checkout layout resolves )
                    return false;
                }
            }
        }
    }).attr({
        'data-test': 'unlockLayoutModal',
        'id': 'unlockLayoutModal'
    });
};


/**
 * Unlock layout
 */
lD.unlockLayout = function() {

    const linkToAPI = urlsForApi.layout.unlock;
    let requestPath = linkToAPI.url;

    lD.common.showLoadingScreen();

    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', lD.layout.layoutId);

    $.ajax({
        url: requestPath,
        type: linkToAPI.type
    }).done(function(res) {
        if(res.success) {
            bootbox.hideAll();

            // Redirect to the layout grid
            window.location.href = urlsForApi.layout.list.url;
        } else {
            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload();
            } else {
                toastr.error(res.message);
            }

            lD.common.hideLoadingScreen();
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        lD.common.hideLoadingScreen();

        // Output error to console
        console.error(jqXHR, textStatus, errorThrown);
    });
};

/**
 * Check history and return last step description
 */
lD.checkHistory = function() {
    // Check if there are some changes
    let undoActive = lD.manager.changeHistory.length > 0;
    let undoActiveTitle = '';

    // Get last action text for popup
    if(undoActive) {
        let lastAction = lD.manager.changeHistory[lD.manager.changeHistory.length - 1];
        if(typeof historyManagerTrans != "undefined" && historyManagerTrans.revert[lastAction.type] != undefined) {
            undoActiveTitle = historyManagerTrans.revert[lastAction.type].replace('%target%', lastAction.target.type);
        } else {
            undoActiveTitle = '[' + lastAction.target.type + '] ' + lastAction.type;
        }
    }

    return {
        undoActive: undoActive,
        undoActiveTitle: undoActiveTitle
    };
};

/**
 * Toggle panel and refresh view containers
 * @param {jquery object} $panel
 */
lD.togglePanel = function($panel, forceToggle) {
    $panel.toggleClass('opened', forceToggle);

    // Refresh navigators and viewer
    if (lD.navigatorMode) {
        lD.renderContainer(lD.navigator);
    } else {
        lD.renderContainer(lD.viewer, lD.selectedObject);
    }
};

/**
 * Toggle panel and refresh view containers
 * @param {Array.<number, object>} items - list of items to add, either just an id or a provider object
 */
lD.importFromProvider = function(items) {
    let requestItems = [];
    let itemsResult = items;

    itemsResult.forEach(element => {
        if(isNaN(element)) {
            requestItems.push(element);
        }
    });

    const linkToAPI = urlsForApi.library.connectorImport;
    let requestPath = linkToAPI.url;

    // Run ajax request and save promise
    return new Promise(function(resolve, reject) {
        // If none of the items are from a provider, return the original array
        if(requestItems.length == 0) {
            resolve(itemsResult);
        }

        lD.common.showLoadingScreen();

        $.ajax({
            url: requestPath,
            type: linkToAPI.type,
            dataType: 'json',
            data: {
                folderId: lD.layout.folderId,
                items: requestItems,
            }
        }).done(function(res) {
            if(res.success) {
                lD.common.hideLoadingScreen();

                res.data.forEach((newElement) => {
                    let addFlag = true;
                    if(newElement.isError) {
                        addFlag = false;
                        toastr.error(newElement.error, newElement.item.id);
                    }

                    itemsResult.forEach((oldElement, key) => {
                        if(isNaN(oldElement) && newElement.item.id == oldElement.id) {
                            itemsResult[key] = (addFlag) ? newElement.media.mediaId : null;
                        }
                    });
                });

                // Filter null results
                itemsResult = itemsResult.filter(el => el);

                resolve(itemsResult);
            } else {
                lD.common.hideLoadingScreen();

                // Login Form needed?
                if(data.login) {
                    window.location.href = window.location.href;
                    location.reload();
                } else {
                    // Just an error we dont know about
                    if(data.message == undefined) {
                        reject(data);
                    } else {
                        reject(data.message);
                    }
                }
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            lD.common.hideLoadingScreen();

            // Reject promise and return an object with all values
            reject({jqXHR, textStatus, errorThrown});
        });
    });
};

/**
 * Take and upload a thumbnail
 * @param {object} targetToAttach DOM object to attach the thumbnail to
 */
lD.uploadThumbnail = function(targetToAttach) {
  if ($(targetToAttach).length > 0) {
    $(targetToAttach).append($('<div class="thumb-preview" style="padding: 2rem 0; font-weight: bold;">').html('Loading Preview...'));
    $(targetToAttach).removeClass('d-none');
  }
  const linkToAPI = urlsForApi.layout.addThumbnail;
  let requestPath = linkToAPI.url.replace(':id', lD.layout.layoutId);
  $.ajax({
    url: requestPath,
    type: 'POST',
    success: function() {
      // Attach to target
      if ($(targetToAttach).length > 0) {
        $(targetToAttach).find('.thumb-preview').replaceWith($('<img style="max-width: 150px; max-height: 100%;">').attr('src', requestPath));
      }
    }
  });
};

/**
 * Add a new region to the layout
 */
lD.addRegion = function () {
    lD.common.showLoadingScreen();

    if(lD.selectedObject.type == 'region') {
        lD.navigator.saveRegionPropertiesPanel();
        lD.selectObject();
    }

    lD.layout.addElement('region').then((res) => { // Success

        lD.common.hideLoadingScreen();

        // Behavior if successful
        toastr.success(res.message);

        // Reload with the new added element
        lD.selectedObject.id = 'region_' + res.data.regionId;
        lD.selectedObject.type = 'region';
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
};

/**
 * Handle messages coming other windows (iframe)
 * @param {object} event
 */
lD.handleMessage = function(event) {
    var messageFromSender = event.data;
    if (messageFromSender == "viewerStoppedPlaying") {
        // Refresh designer
        lD.refreshDesigner();

        // Show tooltip on play button
        lD.bottombar.showPlayMessage();
    }
}


/**
 * Load user preferences
 */
 lD.loadPrefs = function() {
    // Load using the API
    const linkToAPI = urlsForApi.user.getPref;

    // Request elements based on filters
    const self = this;
    $.ajax({
        url: linkToAPI.url + '?preference=editor',
        type: linkToAPI.type,
    }).done(function(res) {
        if (res.success) {
            const loadedData = JSON.parse(res.data.value);

            // Timeline
            const $timeLineToggle = self.timeline.DOMObject.parents('.toggle-panel');
            if(loadedData.timelineStatus != undefined && loadedData.timelineStatus != $timeLineToggle.hasClass('opened')) {
                self.togglePanel($timeLineToggle, loadedData.timelineStatus);
            }

            // Properties Panel
            const propertiesPanelToggle = self.propertiesPanel.DOMObject.parents('.toggle-panel');
            if(loadedData.propertiesPanelStatus != undefined && loadedData.propertiesPanelStatus != propertiesPanelToggle.hasClass('opened')) {
                self.togglePanel(propertiesPanelToggle, loadedData.propertiesPanelStatus);
            }

            self.common.displayTooltips = (loadedData.displayTooltips == 1 || loadedData.displayTooltips == undefined);
        } else {
            // Login Form needed?
            if (res.login) {
                window.location.href = window.location.href;
                location.reload();
            } else {
                // Just an error we dont know about
                if (res.message == undefined) {
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
 * Save user preferences
 * @param {bool=} [clearPrefs = false] - Force reseting user prefs
 */
 lD.savePrefs = function(clearPrefs = false) {
    // Get current values
    let timelineStatus = this.timeline.DOMObject.parents('.toggle-panel').hasClass('opened');
    let propertiesPanelStatus = this.propertiesPanel.DOMObject.parents('.toggle-panel').hasClass('opened');

    // Clear values to defaults
    if (clearPrefs) {
        timelineStatus = false;
        propertiesPanelStatus = true;
    }

    const dataToSave = {
        preference: [
            {
                option: 'editor',
                value: JSON.stringify({
                    timelineStatus: timelineStatus,
                    propertiesPanelStatus: propertiesPanelStatus
                }),
            },
        ],
    };

    // Save using the API
    const linkToAPI = urlsForApi.user.savePref;

    // Request elements based on filters
    $.ajax({
        url: linkToAPI.url,
        type: linkToAPI.type,
        data: dataToSave,
    }).done(function(res) {
        if (!res.success) {
            // Login Form needed?
            if (res.login) {
                window.location.href = window.location.href;
                location.reload();
            } else {
                toastr.error(errorMessagesTrans.userSavePreferencesFailed);

                // Just an error we dont know about
                if (res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }
            }
        }
    }).catch(function(jqXHR, textStatus, errorThrown) {
        console.error(jqXHR, textStatus, errorThrown);
        toastr.error(errorMessagesTrans.userSavePreferencesFailed);
    });
};

lD.enableInlineModeEditing = function() {
    lD.editorContainer.addClass('inline-edit-mode');

    // Click overlay to save changes
    lD.editorContainer.find('.custom-overlay').off().on('click', function() {
        lD.propertiesPanel.save(lD.selectedObject);
    });
};
