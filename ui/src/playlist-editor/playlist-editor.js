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

// Include handlebars templates
const playlistEditorTemplate = require('../templates/playlist-editor.hbs');
const messageTemplate = require('../templates/message.hbs');
const loadingTemplate = require('../templates/loading.hbs');
const dropZoneTemplate = require('../templates/drop-zone.hbs');
const formButtonsTemplate = require('../templates/form-buttons.hbs');

// Include modules
const Playlist = require('../playlist-editor/playlist.js');
const PlaylistTimeline = require('../playlist-editor/playlist-timeline.js');
const Toolbar = require('../core/toolbar.js');
const PropertiesPanel = require('../designer/properties-panel.js');
const Manager = require('../core/manager.js');

// Include CSS
require('../css/designer.less');
require('../css/playlist-editor.less');

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
    playlist : {},

    // Editor DOM div
    editorDiv: {},

    // Timeline
    timeline: {},

    // Viewer
    //viewer: {},

    // Properties Panel
    propertiesPanel: {},

    // Manager
    manager: {},

    // Selected object
    selectedObject: {},

    // Bottom toolbar
    toolbar: {}
};


// Load Layout and build app structure
pE.loadEditor = function() {
    
    pE.common.showLoadingScreen();

    // Save and change toastr positioning
    pE.toastrPosition = toastr.options.positionClass;
    toastr.options.positionClass = 'toast-top-right';

    // Get DOM main object
    pE.editorDiv = $('#playlist-editor');

    // Get playlist id
    const playlistId = pE.editorDiv.attr("playlist-id");

    // Update main object id
    pE.mainObjectId = playlistId;

    // Append layout html to the main div
    pE.editorDiv.html(playlistEditorTemplate());

    // Initialize timeline and create data structure
    pE.playlist = new Playlist(playlistId, playlistData);

    // Initialize properties panel
    pE.propertiesPanel = new PropertiesPanel(
        pE.editorDiv.find('#playlist-properties-panel')
    );

    // Initialize timeline
    pE.timeline = new PlaylistTimeline(
        pE.editorDiv.find('#playlist-timeline')
    );

    // Append manager to the modal container
    $("#layout-manager").appendTo("#playlist-editor");

    // Initialize manager
    pE.manager = new Manager(
        $('#playlist-editor').find('#layout-manager'),
        (serverMode == 'Test')
    );

    // Append toolbar to the modal container
    $("#playlist-editor-toolbar").appendTo("#playlist-editor");

    // Initialize bottom toolbar
    pE.toolbar = new Toolbar(
        $('#playlist-editor').find('#playlist-editor-toolbar'),
        [{
            id: 'undoLastAction',
            title: playlistTrans.undo,
            logo: 'fa-undo',
            class: 'btn-warning',
            inactiveCheck: function() {
                return (pE.manager.changeHistory.length <= 0);
            },
            inactiveCheckClass: 'hidden',
            action: pE.undoLastAction
        }], // Custom buttons
        {
            deleteSelectedObjectAction: pE.deleteSelectedObject
        }
    );

    // Default selected 
    pE.selectObject();

    // Setup helpers
    formHelpers.setup(pE, pE.playlist);

    // Add widget to editor div
    pE.editorDiv.find('#playlist-editor-container').droppable({
        accept: '[drop-to="region"]',
        drop: function(event, ui) {
            pE.playlist.addElement(event.target, ui.draggable[0]);
        }
    }).attr('data-type', 'region');

    // Editor container select ( faking drag and drop ) to add a element to the playlist
    pE.editorDiv.find('#playlist-editor-container').click(function(e) {
        if(!$.isEmptyObject(pE.toolbar.selectedCard)) {
            e.stopPropagation();
            pE.selectObject($(this));
        }
    });

    // Handle keyboard keys
    $('body').off('keydown').keydown(function(handler) {
        if(!$(handler.target).is($('input'))) {

            if(handler.key == 'Delete') {
                pE.deleteSelectedObject();
            }
        }
    });

    pE.common.hideLoadingScreen();
};

// Get Xibo app
window.getXiboApp = function() {
    return pE;
};

/**
 * Select a playlist object (playlist/widget)
 * @param {object=} obj - Object to be selected
 * @param {bool=} forceUnselect - Clean selected object
 */
pE.selectObject = function(obj = null, forceUnselect = false) {

    // If there is a selected card, use the drag&drop simulate to add that item to a object
    if(!$.isEmptyObject(this.toolbar.selectedCard)) {

        if(obj.data('type') == $(this.toolbar.selectedCard).attr('drop-to')) {

            // Get card object
            const card = this.toolbar.selectedCard[0];

            // Deselect cards and drop zones
            this.toolbar.deselectCardsAndDropZones();

            // Simulate drop item add
            this.playlist.addElement(obj, card);
        }

    } else {
        let newSelectedId = {};
        let newSelectedType = {};

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

            // Unselect the previous selectedObject object if still selected
            if(this.selectedObject.selected) {

                if(this.selectedObject.type == 'widget') {

                    if(this.playlist.widgets[this.selectedObject.id]) {
                        this.playlist.widgets[this.selectedObject.id].selected = false;
                    }

                }
            }

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
        let errorMessage = 'Revert failed: ';

        if(typeof error == 'string') {
            errorMessage += error;
        } else {
            errorMessage += error.errorThrown;
        }

        toastr.error(errorMessage);
    });
};

/**
 * Delete selected object
 */
pE.deleteSelectedObject = function() {
    pE.deleteObject(pE.selectedObject.type, pE.selectedObject[pE.selectedObject.type + 'Id']);
};

/**
 * Delete object
 * @param {object} objectToDelete - menu to load content for
 */
pE.deleteObject = function(objectType, objectId) {

    if(objectType === 'widget') {

        bootbox.confirm({
            className: 'second-dialog',
            title: 'Delete ' + objectType,
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

                    pE.common.showLoadingScreen();

                    // Delete element from the layout
                    pE.playlist.deleteElement(objectType, objectId).then((res) => { // Success

                        pE.common.hideLoadingScreen();

                        // Behavior if successful 
                        toastr.success(res.message);
                        pE.reloadData();
                    }).catch((error) => { // Fail/error

                        pE.common.hideLoadingScreen();

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
        }).attr('data-test', 'deleteObjectModal');
        
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

    // Render widgets container only if there are widgets on the playlist, if not draw drop area
    if(!$.isEmptyObject(pE.playlist.widgets)) {
        // Render timeline and properties panel
        this.renderContainer(this.propertiesPanel, this.selectedObject);
        this.renderContainer(this.timeline);

        this.editorDiv.find('#editing-container').show();
        this.editorDiv.find('#dropzone-container').hide();
    } else {
        this.editorDiv.find('#dropzone-container').html(dropZoneTemplate());

        this.editorDiv.find('#editing-container').hide();
        this.editorDiv.find('#dropzone-container').show();
    }

    // Select the object that was previously selected is not selected and exists on the timeline and
    if(!this.playlist.widgets[this.selectedObject.id].selected) {
        this.selectObject(this.timeline.DOMObject.find('#' + this.selectedObject.id));
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

    const linkToAPI = urlsForApi.playlist.get;
    let requestPath = linkToAPI.url;

    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', pE.playlist.playlistId);

    pE.common.showLoadingScreen();

    $.get(
        requestPath
    ).done(function(res) {

        pE.common.hideLoadingScreen();

        if(res.success) {
            pE.playlist = new Playlist(pE.playlist.playlistId, res.data.playlist);

            pE.refreshDesigner();
        } else {
            pE.showErrorMessage();
        }
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
        messageTitle: 'ERROR',
        messageDescription: 'There was a problem loading the playlist!'
    });

    pE.editorDiv.html(htmlError);
};

/**
 * Save playlist order
 */
pE.saveOrder = function() {

    const self = this;

    pE.common.showLoadingScreen('saveOrder');
    
    this.playlist.saveOrder($('#timeline-container').find('.playlist-widget')).then((res) => { // Success
        
        pE.common.hideLoadingScreen('saveOrder');

        // Behavior if successful            
        toastr.success(res.message);

        self.reloadData();

    }).catch((error) => { // Fail/error

        pE.common.hideLoadingScreen('saveOrder');

        // Show error returned or custom message to the user
        let errorMessage = 'Save order failed: ' + error;

        if(typeof error == 'string') {
            errorMessage += error;
        } else {
            errorMessage += error.errorThrown;
        }

        toastr.error(errorMessage);
    });
};

/**
 * Close playlist editor
 */
pE.close = function() {
    
    // Restore toastr positioning
    toastr.options.positionClass = this.toastrPosition;

    $('#editor-container').empty();
};

/**
 * Close playlist editor
 */
pE.showLocalLoadingScreen = function() {
    // If there are no widgets, render the loading template in the drop zone
    if($.isEmptyObject(pE.playlist.widgets)) {
        pE.editorDiv.find('#dropzone-container').html(loadingTemplate());
    } else {
        pE.editorDiv.find('#playlist-timeline').html(loadingTemplate());
    }
};

/**
 * Clear Temporary Data ( Cleaning cached variables )
 */
pE.clearTemporaryData = function() {

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
 * Open Upload Form
 * @param {object} templateOptions
 * @param {object} buttons
 */
pE.openUploadForm = function(templateOptions, buttons) {

    var template = Handlebars.compile($("#template-file-upload").html());

    // Handle bars and open a dialog
    bootbox.dialog({
        className: 'second-dialog',
        message: template(templateOptions),
        title: playlistTrans.uploadMessage,
        buttons: buttons,
        animate: false,
        updateInAllChecked: uploadFormUpdateAllDefault,
        deleteOldRevisionsChecked: uploadFormDeleteOldDefault
    });

    this.openUploadFormModelShown($(".modal-body").find("form"));
};

/**
 * Modal shown
 * @param {object} form
 */
pE.openUploadFormModelShown = function(form) {

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
    });
};