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
const playlistEditorTemplate = require('../templates/playlist-editor.hbs');
const messageTemplate = require('../templates/message.hbs');
const loadingTemplate = require('../templates/loading.hbs');
const dropZoneTemplate = require('../templates/drop-zone.hbs');
const contextMenuTemplate = require('../templates/context-menu.hbs');
const deleteElementModalContentTemplate = require('../templates/delete-element-modal-content.hbs');
const deleteMultiElementModalContentTemplate = require('../templates/delete-multi-element-modal-content.hbs');

// Include modules
const Playlist = require('../playlist-editor/playlist.js');
const PlaylistTimeline = require('../playlist-editor/playlist-timeline.js');
const Toolbar = require('../core/toolbar.js');
const PropertiesPanel = require('../designer/properties-panel.js');
const Manager = require('../core/manager.js');

// Include CSS
if(typeof lD == 'undefined') {
    // Include the layout designer code if we're in the playlist editor only
    require('../style/common.scss');
    require('../style/designer.scss');
    require('../style/toolbar.scss');
    require('../style/topbar.scss');
}

require('../style/playlist-editor.scss');

// Common funtions/tools
const Common = require('../core/common.js');

// Create layout designer namespace (pE)
window.pE = {

    // Attach common functions to layout designer
    common: Common,

    // Main object info
    mainObjectType: 'playlist',
    mainObjectId: '',

    // Playlist
    playlist: {},

    // Editor DOM div
    editorContainer: {},

    // Timeline
    timeline: {},

    // Properties Panel
    propertiesPanel: {},

    // Manager
    manager: {},

    // Selected object
    selectedObject: {},

    // Bottom toolbar
    toolbar: {}
};

// Load Playlist and build app structure
pE.loadEditor = function() {

    pE.common.showLoadingScreen();

    // Save and change toastr positioning
    pE.toastrPosition = toastr.options.positionClass;
    toastr.options.positionClass = 'toast-top-center';

    // Get DOM main object
    pE.editorContainer = $('#playlist-editor');

    // If the editor is being loaded from within the layout designer, change the region specific flag
    pE.regionSpecificQuery = '';

    if(typeof lD != 'undefined') {
        pE.regionSpecificQuery = '&regionSpecific=1';
        pE.mainRegion = pE.editorContainer.parents('#editor-container').data('regionObj');
    }

    // Get playlist id
    const playlistId = pE.editorContainer.attr("playlist-id");
    
    // Update main object id
    pE.mainObjectId = playlistId;

    // Show loading template
    pE.editorContainer.html(loadingTemplate());

    // Load playlist through an ajax request
    $.get(urlsForApi.playlist.get.url + '?playlistId=' + playlistId + '&embed=widgets,widget_validity,tags,permissions' + pE.regionSpecificQuery)
        .done(function(res) {

            if(res.data != null && res.data.length > 0) {

                // Append layout html to the main div
                pE.editorContainer.html(playlistEditorTemplate());

                // Initialise dropabble containers
                pE.editorContainer.find('#playlist-timeline, #dropzone-container').droppable({
                    accept: '[drop-to="region"]',
                    drop: function(event, ui) {
                        pE.playlist.addElement(event.target, ui.draggable[0]);
                    }
                }).attr('data-type', 'region');

                // Editor container select ( faking drag and drop ) to add a element to the playlist
                pE.editorContainer.find('#playlist-timeline, #dropzone-container').click(function(e) {
                    if(!$.isEmptyObject(pE.toolbar.selectedCard) || !$.isEmptyObject(pE.toolbar.selectedQueue)) {
                        e.stopPropagation();
                        pE.selectObject($(this));
                    }
                });

                // Initialize timeline and create data structure
                pE.playlist = new Playlist(playlistId, res.data[0]);

                // Initialize properties panel
                pE.propertiesPanel = new PropertiesPanel(
                    pE,
                    pE.editorContainer.find('#playlist-properties-panel')
                );

                // Initialize timeline
                pE.timeline = new PlaylistTimeline(
                    pE.editorContainer.find('#playlist-timeline')
                );

                // Append manager to the modal container
                $("#layout-manager").appendTo("#playlist-editor");

                // Initialize manager
                if(typeof lD != 'undefined') {
                    pE.manager = lD.manager;
                } else {
                    pE.manager = new Manager(
                        pE,
                        $('#playlist-editor').find('#layout-manager'),
                        false //(serverMode == 'Test') Turn of manager visibility for now
                    );
                }

                // Append toolbar to the modal container
                $("#playlist-editor-toolbar").appendTo("#playlist-editor");

                // Initialize bottom toolbar
                pE.toolbar = new Toolbar(
                    pE,
                    $('#playlist-editor').find('#playlist-editor-toolbar'),
                    {
                        deleteSelectedObjectAction: pE.deleteSelectedObject
                    },
                    true
                );
                pE.toolbar.parent = pE;

                // Default selected 
                pE.selectObject();

                // Setup helpers
                formHelpers.setup(pE, pE.playlist);

                // Handle keyboard keys
                $('body').off('keydown').keydown(function(handler) {
                    if(!$(handler.target).is($('input'))) {

                        if(handler.key == 'Delete') {
                            pE.deleteSelectedObject();
                        }
                    }
                });

                // Load user preferences
                pE.loadAndSavePref('useLibraryDuration', 0);

                pE.common.hideLoadingScreen();

            } else {
                // Login Form needed?
                if(res.login) {
                    window.location.href = window.location.href;
                    location.reload(false);
                } else {
                    pE.showErrorMessage();
                }
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {

            // Output error to console
            console.error(jqXHR, textStatus, errorThrown);

            pE.showErrorMessage();
        });
};

// Get Xibo app
window.getXiboApp = function() {
    return pE;
};

/**
 * Select a playlist object (playlist/widget)
 * @param {object=} obj - Object to be selected
 * @param {bool=} forceUnselect - Clean selected object
 * @param {object =} [options] - selectObject options
 * @param {number=} [options.positionToAdd = null] - Order position for widget
 */
pE.selectObject = function(obj = null, forceUnselect = false, {positionToAdd = null} = {}) {

    // If there is a selected card, use the drag&drop simulate to add that item to a object
    if(!$.isEmptyObject(this.toolbar.selectedCard)) {

        if([obj.data('type'), 'all'].indexOf($(this.toolbar.selectedCard).attr('drop-to')) !== -1) {

            // Get card object
            const card = this.toolbar.selectedCard[0];

            // Deselect cards and drop zones
            this.toolbar.deselectCardsAndDropZones();

            // Simulate drop item add
            this.dropItemAdd(obj, card, {positionToAdd: positionToAdd});
        }

    } else if(!$.isEmptyObject(this.toolbar.selectedQueue) && $(this.toolbar.selectedQueue).data('to-add')) { // If there's a selected queue, use the drag&drop simulate to add those items to a object
        if(obj.data('type') == 'region') {
            let mediaQueueArray = [];

            // Get queue elements
            this.toolbar.selectedQueue.find('.queue-element').each(function() {
                mediaQueueArray.push($(this).attr('id'));
            });

            // Add media queue to playlist
            this.playlist.addMedia(mediaQueueArray, positionToAdd);

            // Destroy queue
            this.toolbar.destroyQueue(this.toolbar.openedMenu);
        }

        // Deselect cards and drop zones
        this.toolbar.deselectCardsAndDropZones();
    } else {
        let newSelectedId = {};
        let newSelectedType = {};

        // Unselect the previous selectedObject object if still selected
        if(this.selectedObject.selected) {
            if(this.selectedObject.type == 'widget') {
                if(this.playlist.widgets[this.selectedObject.id]) {
                    this.playlist.widgets[this.selectedObject.id].selected = false;
                }
            }
        }

        // If there's no selected object, select a default one ( or nothing if widgets are empty)
        if(obj == null || typeof obj.data('type') == 'undefined') {

            if($.isEmptyObject(pE.playlist.widgets) || forceUnselect) {
                this.selectedObject = {};
            } else {
                // Select first widget
                let newId = Object.keys(this.playlist.widgets)[0];

                this.playlist.widgets[newId].selected = true;
                this.selectedObject.type = 'widget';
                this.selectedObject = this.playlist.widgets[newId];

            }
        } else {

            // Get object properties from the DOM ( or set to layout if not defined )
            newSelectedId = obj.attr('id');
            newSelectedType = obj.data('type');

            // Select new object
            if(newSelectedType === 'widget') {
                this.playlist.widgets[newSelectedId].selected = true;
                this.selectedObject = this.playlist.widgets[newSelectedId];
            }

            this.selectedObject.type = newSelectedType;
        }

        // Refresh the designer containers
        this.refreshDesigner();
    }
};

/**
 * Add action to take after dropping a draggable item
 * @param {object} droppable - Target drop object
 * @param {object} draggable - Target Card
 * @param {object =} [options] - Options
 * @param {object/number=} [options.positionToAdd = null] - order position for widget
 */
pE.dropItemAdd = function(droppable, card, {positionToAdd = null} = {}) {
    this.playlist.addElement(droppable, card, positionToAdd);
};

/**
 * Revert last action
 */
pE.undoLastAction = function() {

    pE.common.showLoadingScreen();

    pE.manager.revertChange().then((res) => { // Success

        pE.common.hideLoadingScreen();

        toastr.success(res.message);

        // Refresh designer according to local or API revert
        if(res.localRevert) {
            pE.refreshDesigner();
        } else {
            pE.reloadData();
        }
    }).catch((error) => { // Fail/error

        pE.common.hideLoadingScreen();

        // Show error returned or custom message to the user
        let errorMessage = '';

        if(typeof error == 'string') {
            errorMessage = error;
        } else {
            errorMessage = error.errorThrown;
        }

        toastr.error(errorMessagesTrans.revertFailed.replace('%error%', errorMessage));
    });
};

/**
 * Delete selected object
 */
pE.deleteSelectedObject = function() {
    if(pE.editorContainer.hasClass('multi-select')) {
        // Get selected widgets
        let selectedWidgetsIds = [];

        pE.timeline.DOMObject.find('.playlist-widget.multi-selected').each(function() {
            selectedWidgetsIds.push($(this).data('widgetId'));

        });

        pE.deleteMultipleObjects('widget', selectedWidgetsIds);
    } else {
        pE.deleteObject(pE.selectedObject.type, pE.selectedObject[pE.selectedObject.type + 'Id']);
    }
};

/**
 * Delete object
 * @param {string} objectType
 * @param {number} objectId
 */
pE.deleteObject = function(objectType, objectId) {
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

                        pE.common.showLoadingScreen('deleteObject');

                        // Delete element from the layout
                        pE.playlist.deleteElement(objectType, objectId, options).then((res) => { // Success

                            pE.common.hideLoadingScreen('deleteObject');

                            // Behavior if successful 
                            toastr.success(res.message);
                            pE.reloadData();
                        }).catch((error) => { // Fail/error

                            pE.common.hideLoadingScreen('deleteObject');

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

    if(objectType === 'widget') {

        const widgetToDelete = pE.getElementByTypeAndId('widget', 'widget_' + objectId);

        if(widgetToDelete.isRegionSpecific()) {
            createDeleteModal(objectType, objectId);
        } else {
            pE.common.showLoadingScreen('checkMediaIsUsed');

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

                    pE.common.hideLoadingScreen('checkMediaIsUsed');

                }).fail(function(jqXHR, textStatus, errorThrown) {

                    pE.common.hideLoadingScreen('checkMediaIsUsed');

                    // Output error to console
                    console.error(jqXHR, textStatus, errorThrown);
                });
        }
    }
};

/**
 * Delete multiple objects
 * @param {object[]} objectsToDelete
 */
pE.deleteMultipleObjects = function(objectsType, objectIds) {
    const createMultiDeleteModal = function(objectArray) {
        bootbox.hideAll();

        const htmlContent = deleteMultiElementModalContentTemplate({
            mainMessage: deleteMenuTrans.deleteMultipleObjects,
            objectArray: objectArray,
            trans: deleteMenuTrans
        });

        // Create buttons object
        let buttons = {
            cancel: {
                label: editorsTrans.no,
                className: 'btn-default'
            }
        };

        // Select all button ( if there are 2 or more checkboxes )
        if($(htmlContent).find('input[type="checkbox"]').length > 1) {
            buttons.selectAll = {
                label: editorsTrans.selectAll,
                className: 'btn-warning',
                callback: function() {
                    $(this).find('input[type="checkbox"]').prop('checked', true);
                    return false;
                }
            };
        }
        
        buttons.confirm = {
            label: editorsTrans.yes,
            className: 'btn-danger',
            callback: function() {
                const $objects = $(this).find('.multi-delete-element');
                let deletedElements = 0;
                let index = 0;

                // Show modal
                pE.common.showLoadingScreen('deleteObjects');

                // Leave multi select mode
                pE.toolbar.toggleMultiselectMode(false);

                const deleteObject = function() {
                    const $element = $($objects[index]);

                    // Empty options object
                    let options = null;
                    const objectId = $element.data('id');
                    const objectType = $element.data('type');

                    // If delete media is checked, pass that as a param for delete
                    if($element.find('input.deleteMedia').is(':checked')) {
                        options = {
                            deleteMedia: 1
                        };
                    }

                    // Delete element from the playlist
                    pE.playlist.deleteElement(objectType, objectId, options).then((res) => { // Success
                        // Behavior if successful 
                        toastr.success(res.message)
                        
                        deletedElements++;

                        if(deletedElements == $objects.length) {
                            // Hide loading screen
                            pE.common.hideLoadingScreen('deleteObjects');
                            
                            // Reload data
                            pE.reloadData();

                            // Hide/close modal
                            bootbox.hideAll();
                        } else {
                            index++;
                            deleteObject();
                        }
                        
                    }).catch((error) => { // Fail/error

                        pE.common.hideLoadingScreen('deleteObjects');

                        // Show error returned or custom message to the user
                        let errorMessage = '';

                        if(typeof error == 'string') {
                            errorMessage = error;
                        } else {
                            errorMessage = error.errorThrown;
                        }

                        toastr.error(errorMessagesTrans.deleteFailed.replace('%error%', errorMessage));

                        // Reload data
                        pE.reloadData();

                        // Hide/close modal
                        bootbox.hideAll();
                    });
                };

                deleteObject();

                return false;
            }
        };

        bootbox.dialog({
            title: editorsTrans.deleteMultipleTitle,
            message: htmlContent,
            buttons: buttons
        }).attr('data-test', 'deleteObjectModal');
    };

    if(objectsType === 'widget') {

        pE.common.showLoadingScreen('checkMediaIsUsed');
        let arrayOfWidgets = [];
        let index = 0;

        const getWidgetStatus = function() {
            let widgetId = objectIds[index];
            let widgetToDelete = pE.getElementByTypeAndId('widget', 'widget_' + widgetId);
            let linkToAPI = urlsForApi.media.isUsed;
            let requestPath = linkToAPI.url.replace(':id', widgetToDelete.mediaIds[0]);
    
            if(widgetToDelete.isRegionSpecific()) {
                arrayOfWidgets.push({
                    'objectId': widgetId,
                    'objectType': 'widget',
                    'objectName': widgetToDelete.widgetName,
                    'hasMedia': false,
                    'dataUsed': false
                });
    
                if(arrayOfWidgets.length == objectIds.length) {
                    createMultiDeleteModal(arrayOfWidgets);
                    pE.common.hideLoadingScreen('checkMediaIsUsed');
                } else {
                    index++;
                    getWidgetStatus();
                }
            } else {
                // Request with count as being 2, for the published layout and draft
                $.get(requestPath + '?count=1')
                    .done(function(res) {
                        if(res.success) {
                            arrayOfWidgets.push({
                                'objectId': widgetId,
                                'objectType': 'widget',
                                'objectName': widgetToDelete.widgetName,
                                'hasMedia': true,
                                'dataUsed': res.data.isUsed
                            });
    
                            if(arrayOfWidgets.length == objectIds.length) {
                                createMultiDeleteModal(arrayOfWidgets);
                                pE.common.hideLoadingScreen('checkMediaIsUsed');
                            } else {
                                index++;
                                getWidgetStatus();
                            }
                        } else {
                            if(res.login) {
                                window.location.href = window.location.href;
                                location.reload(false);
                            } else {
                                toastr.error(res.message);
                            }
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
    
                        pE.common.hideLoadingScreen('checkMediaIsUsed');
    
                        // Output error to console
                        console.error(jqXHR, textStatus, errorThrown);
                    });
            }
        };

        // Start getting widget status
        getWidgetStatus();
    }
};

/**
 * Refresh designer
 */
pE.refreshDesigner = function() {

    // Remove temporary data
    this.clearTemporaryData();

    // Render containers
    this.renderContainer(this.toolbar);
    this.renderContainer(this.manager);

    // If there was a opened menu in the toolbar, open that tab
    if(this.toolbar.openedMenu != -1) {
        this.toolbar.openMenu(this.toolbar.openedMenu, true);
    }

    // Render widgets container only if there are widgets on the playlist, if not draw drop area
    if(!$.isEmptyObject(pE.playlist.widgets)) {

        // Render timeline
        this.renderContainer(this.timeline);

        // Select the object that was previously selected if it's not selected and exists on the timeline
        if(this.playlist.widgets[this.selectedObject.id] !== undefined && !this.playlist.widgets[this.selectedObject.id].selected) {
            this.selectObject(this.timeline.DOMObject.find('#' + this.selectedObject.id));
        } else if(this.playlist.widgets[this.selectedObject.id] === undefined) {
            //Prevent nothing selected
            this.selectObject();
        } else {
            // Render properties panel
            this.renderContainer(this.propertiesPanel, this.selectedObject);
        }

        this.editorContainer.find('#editing-container').show();
        this.editorContainer.find('#dropzone-container').hide();
    } else {
        this.editorContainer.find('#dropzone-container').html(dropZoneTemplate());

        this.editorContainer.find('#editing-container').hide();
        this.editorContainer.find('#dropzone-container').show();
        
        // If playlist is empty, open the widget tab
        if(this.toolbar.openedMenu == -1) {
            this.toolbar.openMenu(2, true);
        }
    }
};

/**
 * Render layout structure to container, if it exists
 * @param {object} container - Container for the layout to be rendered
 * @param {object=} element - Element to be rendered, if not used, render layout
 */
pE.renderContainer = function(container, element = {}) {
    // Check container to prevent rendering to an empty container
    if(!jQuery.isEmptyObject(container)) {

        // Render element if defined, layout otherwise
        if(!jQuery.isEmptyObject(element)) {
            container.render(element);
        } else {
            container.render();
        }
    }
};

/**
 * Reload API data and replace the playlist structure with the new value
 */
pE.reloadData = function() {

    pE.common.showLoadingScreen();

    $.get(urlsForApi.playlist.get.url + '?playlistId=' + pE.playlist.playlistId + '&embed=widgets,widget_validity,tags,permissions' + pE.regionSpecificQuery)
        .done(function(res) {
            pE.common.hideLoadingScreen();

            if(res.data != null && res.data.length > 0) {
                pE.playlist = new Playlist(pE.playlist.playlistId, res.data[0]);

                pE.refreshDesigner();
            } else {
                if(res.login) {
                    window.location.href = window.location.href;
                    location.reload(false);
                } else {
                    pE.showErrorMessage();
                }
            }

            // Reload the form helper connection
            formHelpers.setup(pE, pE.playlist);
        }).fail(function(jqXHR, textStatus, errorThrown) {

            pE.common.hideLoadingScreen();

            // Output error to console
            console.error(jqXHR, textStatus, errorThrown);

            pE.showErrorMessage();
        });
};

/**
 * Layout loading error message
 */
pE.showErrorMessage = function() {
    // Output error on screen
    const htmlError = messageTemplate({
        messageType: 'danger',
        messageTitle: errorMessagesTrans.error,
        messageDescription: errorMessagesTrans.loadingPlaylist
    });

    pE.editorContainer.html(htmlError);
};

/**
 * Save playlist order
 */
pE.saveOrder = function() {

    const self = this;

    pE.common.showLoadingScreen('saveOrder');

    this.playlist.saveOrder(this.editorContainer.find('#timeline-container').find('.playlist-widget')).then((res) => { // Success

        pE.common.hideLoadingScreen('saveOrder');

        // Behavior if successful            
        toastr.success(res.message);

        self.reloadData();

    }).catch((error) => { // Fail/error

        pE.common.hideLoadingScreen('saveOrder');

        // Show error returned or custom message to the user
        let errorMessage = '';

        if(typeof error == 'string') {
            errorMessage = error;
        } else {
            errorMessage = error.errorThrown;
        }

        toastr.error(errorMessagesTrans.saveOrderFailed.replace('%error%', errorMessage));
    });
};

/**
 * Close playlist editor
 */
pE.close = function() {

    /**
     * Clear all object own properties
     * @param {object} objectToClean 
     */
    const deleteObjectProperties = function(objectToClean) {
        for(var x in objectToClean) if(objectToClean.hasOwnProperty(x)) delete objectToClean[x];
    };

    // Clear loaded vars
    this.mainObjectId = '';
    deleteObjectProperties(this.playlist);
    deleteObjectProperties(this.editorContainer);
    deleteObjectProperties(this.timeline);
    deleteObjectProperties(this.propertiesPanel);
    deleteObjectProperties(this.selectedObject);
    deleteObjectProperties(this.toolbar);

    // Remove resize event listener related to the toolbar
    $(window).off('.toolbar-' + this.mainObjectType);

    // Make sure all remaining objects are pure empty JS objects
    this.playlist = this.editorContainer = this.timeline = this.propertiesPanel = this.manager = this.selectedObject = this.toolbar = {};

    // Restore toastr positioning
    toastr.options.positionClass = this.toastrPosition;

    $('#editor-container').empty();
};

/**
 * Show loading screen
 */
pE.showLocalLoadingScreen = function() {
    // If there are no widgets, render the loading template in the drop zone
    if($.isEmptyObject(pE.playlist.widgets)) {
        pE.editorContainer.find('#dropzone-container').html(loadingTemplate());
    } else {
        pE.editorContainer.find('#playlist-timeline').html(loadingTemplate());
    }
};

/**
 * Clear Temporary Data ( Cleaning cached variables )
 */
pE.clearTemporaryData = function() {

    // Fix for remaining ckeditor elements or colorpickers
    pE.editorContainer.find('.colorpicker-element').colorpicker('destroy');

    // Hide open tooltips
    pE.editorContainer.find('.tooltip').remove();

    // Remove text callback editor structure variables
    formHelpers.destroyCKEditor();
};

/**
 * Get element from the main object ( playlist )
 * @param {string} type
 * @param {number} id
 */
pE.getElementByTypeAndId = function(type, id) {

    let element = {};

    if(type === 'playlist') {
        element = pE.playlist;
    } else if(type === 'widget') {
        element = pE.playlist.widgets[id];
    }

    return element;
};

/**
 * Get the class name for the upload dialog, used by form-helpers.
 * @return {null}
 */
pE.getUploadDialogClassName = function() {
    return "second-dialog";
};

/**
 * Open object context menu
 * @param {object} obj - Target object
 * @param {object=} position - Page menu position
 */
pE.openContextMenu = function(obj, position = {x: 0, y: 0}) {

    let objId = $(obj).attr('id');
    let objType = $(obj).data('type');

    // Get object
    let playlistObject = pE.getElementByTypeAndId(objType, objId);

    // Create menu and append to the designer div ( using the object extended with translations )
    pE.editorContainer.append(contextMenuTemplate(Object.assign(playlistObject, {trans: contextMenuTrans})));

    // Set menu position ( and fix page limits )
    let contextMenuWidth = pE.editorContainer.find('.context-menu').outerWidth();
    let contextMenuHeight = pE.editorContainer.find('.context-menu').outerHeight();

    let positionLeft = ((position.x + contextMenuWidth) > $(window).width()) ? (position.x - contextMenuWidth) : position.x;
    let positionTop = ((position.y + contextMenuHeight) > $(window).height()) ? (position.y - contextMenuHeight) : position.y;

    pE.editorContainer.find('.context-menu').offset({top: positionTop, left: positionLeft});

    // Click overlay to close menu
    pE.editorContainer.find('.context-menu-overlay').click((ev) => {

        if($(ev.target).hasClass('context-menu-overlay')) {
            pE.editorContainer.find('.context-menu-overlay').remove();
        }
    });

    // Handle buttons
    pE.editorContainer.find('.context-menu .context-menu-btn').click((ev) => {
        let target = $(ev.currentTarget);

        if(target.data('action') == 'Delete') {
            pE.deleteObject(objType, playlistObject[objType + 'Id']);
        } else {
            playlistObject.editPropertyForm(target.data('property'), target.data('propertyType'));
        }

        // Remove context menu
        pE.editorContainer.find('.context-menu-overlay').remove();
    });
};

/**
 * Load user preference
 */
pE.loadAndSavePref = function(prefToLoad, defaultValue = 0) {

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
                pE[prefToLoad] = res.data.value;
            } else {
                pE[prefToLoad] = defaultValue;
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
