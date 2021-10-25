// PLAYLIST Module
const Widget = require('../editor-core/widget.js');

/**
 * Playlist contructor
 * @param  {number} id - Playlist id
 * @param  {object} data - data to build the playlist object
 */
let Playlist = function(id, data) {

    // Playlist name
    this.name = data.name;

    //  properties
    this.playlistId = id;
    this.folderId = data.folderId;
    this.isEmpty = true;

    this.widgets = {};
    this.duration = null;
    this.folderId = data.folderId;

    // Create data structure based on the API data
    this.createDataStructure(data);

    // Calculate duration, looping, and all properties related to time
    this.calculateTimeValues();
};

/**
 * Create data structure
 */
Playlist.prototype.createDataStructure = function(data) {

    // Playlist duration calculated based on the longest region duration
    let playlistDuration = 0;

    // Widget's data
    const widgets = data.widgets;

    // Create widgets for this region
    for(let widget in widgets) {

        const newWidget = new Widget(
            widgets[widget].widgetId,
            widgets[widget]
        );

        if(newWidget.subType == 'image') {
            newWidget.previewTemplate = '<div class="tooltip playlist-widget-preview" role="tooltip"><div class="arrow"></div><div class="tooltip-inner-image"><img src=' + imageDownloadUrl.replace(':id', widgets[widget].mediaIds[0]) + '></div></div>';
        }


        newWidget.designerObject = pE;

        // calculate expire status
        newWidget.calculateExpireStatus();
        
        // Add newWidget to the playlist widget object
        this.widgets[newWidget.id] = newWidget;

        // Mark the playlist as not empty
        this.isEmpty = false;

        // Increase playlist Duration
        playlistDuration += newWidget.getTotalDuration();
    }

    // Set playlist duration
    this.duration = playlistDuration;
};


/**
 * Calculate timeline values ( duration, loops ) based on widget and region duration
 */
Playlist.prototype.calculateTimeValues = function() {

    // Widgets
    const widgets = this.widgets;
    let loopSingleWidget = false;
    let singleWidget = false;

    // If there is only one widget in the playlist, check the loop option for that region
    if(widgets.length === 1) {

        singleWidget = true;
        // Check the loop option
        for(let option in this.options) {
            if(this.options[option].option === 'loop' && this.options[option].value === '1') {
                this.loop = true;
                loopSingleWidget = true;
                break;
            }
        }
    } else if(parseFloat(this.duration) < parseFloat(this.duration)) {
        // if the region duration is less than the layout duration enable loop
        this.loop = true;
    }

    for(let widget in widgets) {
        let currWidget = widgets[widget];

        // If the widget needs to be extended
        currWidget.singleWidget = singleWidget;
        currWidget.loop = loopSingleWidget;
    }
};

/**
 * Add action to take after dropping a draggable item
 * @param {object} droppable - Target drop object
 * @param {object} draggable - Dragged object
 * @param {number=} addToPosition - Add to specific position in the widget list
 */
Playlist.prototype.addElement = function(droppable, draggable, addToPosition = null) {
    const draggableType = $(draggable).data('type');
    const draggableSubType = $(draggable).data('subType');

    // Get playlist Id
    const playlistId = this.playlistId;

    // Add dragged item to region
    if(draggableType == 'media') { // Adding media from search tab to a region
        if($(draggable).hasClass('from-provider')) {
            pE.importFromProvider([$(draggable).data('providerData')]).then((res) =>  {
                this.addMedia(res, addToPosition);
            }).catch(function() {
                toastr.error(errorMessagesTrans.importingMediaFailed);
            });
        } else {
            this.addMedia($(draggable).data('mediaId'), addToPosition);
        }
    } else if(draggableType == 'module') { // Add widget/module

        // Get regionSpecific property
        const regionSpecific = $(draggable).data('regionSpecific');

        // Upload form if not region specific
        if(regionSpecific == 0) {

            const validExt = $(draggable).data('validExt').replace(/,/g, "|");

            openUploadForm({
                url: libraryAddUrl,
                title: uploadTrans.uploadMessage,
                animateDialog: false,
                initialisedBy: "playlist-editor-upload",
                className: "second-dialog",
                buttons: {
                    viewLibrary: {
                        label: uploadTrans.viewLibrary,
                        className: "btn-white btn-bb-viewlibrary",
                        callback: function() {
                            pE.toolbar.openNewTabAndSearch(draggableSubType);
                        }
                    },
                    main: {
                        label: translations.done,
                        className: "btn-primary btn-bb-main",
                        callback: function() {
                            pE.reloadData();
                        }
                    }
                },
                templateOptions: {
                    trans: uploadTrans,
                    upload: {
                        maxSize: $(draggable).data().maxSize,
                        maxSizeMessage: $(draggable).data().maxSizeMessage,
                        validExtensionsMessage: translations.validExtensions.replace("%s", $(draggable).data('validExt')),
                        validExt: validExt
                    },
                    playlistId: playlistId,
                    displayOrder: addToPosition,
                    currentWorkingFolderId: pE.folderId,
                    showWidgetDates: true,
                    folderSelector: true
                }
            }).attr('data-test', 'uploadFormModal');

        } else { // Add widget to a region

            const linkToAPI = urlsForApi.playlist.addWidget;

            let requestPath = linkToAPI.url;

            pE.common.showLoadingScreen('addModuleToPlaylist');

            // Replace type
            requestPath = requestPath.replace(':type', draggableSubType);

            // Replace playlist id
            requestPath = requestPath.replace(':id', playlistId);

            // Set position to add if selected
            let addOptions = null;
            if(addToPosition != null) {
                addOptions = {
                    displayOrder: addToPosition
                };
            }

            pE.manager.addChange(
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

                pE.common.hideLoadingScreen('addModuleToPlaylist');

                // The new selected object
                pE.selectedObject.id = 'widget_' + res.data.widgetId;
                pE.selectedObject.type = 'widget';

                // Behavior if successful 
                toastr.success(res.message);

                pE.reloadData();

            }).catch((error) => { // Fail/error

                pE.common.hideLoadingScreen('addModuleToPlaylist');

                // Show error returned or custom message to the user
                let errorMessage = '';

                if(typeof error == 'string') {
                    errorMessage += error;
                } else {
                    errorMessage += error.errorThrown;
                }

                // Remove added change from the history manager
                pE.manager.removeLastChange();

                // Display message in form
                formHelpers.displayErrorMessage(dialog.find('form'), errorMessage, 'danger');

                // Show toast message
                toastr.error(errorMessage);
            });
        }
    } else if(draggableType == 'tool') { // Add tool

        const widgetId = $(droppable).attr('id');
        const widget = pE.getElementByTypeAndId('widget', widgetId);

        // Select widget ( and avoid deselect if region was already selected )
        pE.selectObject($(droppable), true);

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
    }
};

/**
 * Add media to the playlist
 * @param {Array.<number>} media
 * @param {number=} addToPosition
 */
Playlist.prototype.addMedia = function(media, addToPosition = null) {
    // Get playlist Id
    const playlistId = this.playlistId;

    // Get media Id
    let mediaToAdd = {};

    if(Array.isArray(media)) {
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
    if(pE.useLibraryDuration != undefined) {
        mediaToAdd.useDuration = (pE.useLibraryDuration == "1");
    }

    // Show loading screen in the dropzone
    pE.showLocalLoadingScreen();

    pE.common.showLoadingScreen();

    // Set position to add if selected
    if(addToPosition != null) {
        mediaToAdd.displayOrder = addToPosition;
    }

    // Create change to be uploaded
    pE.manager.addChange(
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

        pE.common.hideLoadingScreen();

        // The new selected object
        pE.selectedObject.id = 'widget_' + res.data.newWidgets[0].widgetId;
        pE.selectedObject.type = 'widget';

        // Behavior if successful
        toastr.success(res.message);
        pE.reloadData();
    }).catch((error) => { // Fail/error

        pE.common.hideLoadingScreen();

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
 * Delete an element in the playlist, by ID
 * @param {number} elementId - element id
 * @param {string} elementType - element type (widget, region, ...)
 * @param {object =} [options] - Delete submit params/options
 */
Playlist.prototype.deleteElement = function(elementType, elementId, options = null) {
    pE.common.showLoadingScreen();
    
    // Remove changes from the history array
    return pE.manager.removeAllChanges(elementType, elementId).then((res) => {

        pE.common.hideLoadingScreen();

        // Create a delete type change, upload it but don't add it to the history array
        return pE.manager.addChange(
            "delete",
            elementType, // targetType
            elementId, // targetId
            null, // oldValues
            options, // newValues
            {
                addToHistory: false // options.addToHistory
            }
        );

    }).catch(function() {
        pE.common.hideLoadingScreen();

        toastr.error(errorMessagesTrans.removeAllChangesFailed);
    });

};

/**
 * Save playlist order
 * @param {object} widgets - Widgets DOM objects array
 */
Playlist.prototype.saveOrder = function(widgets) {

    if($.isEmptyObject(pE.playlist.widgets)) {
        return Promise.resolve({
            message: errorMessagesTrans.noWidgetsNeedSaving
        });
    }

    // Get playlist's widgets previous order
    let oldOrder = {};
    let orderIndex = 1;
    for(var element in pE.playlist.widgets) {
        oldOrder[pE.playlist.widgets[element].widgetId] = orderIndex;
        orderIndex++;
    }

    // Get new order
    let newOrder = {};

    for(let index = 0;index < widgets.length;index++) {
        const widget = pE.getElementByTypeAndId('widget', $(widgets[index]).attr('id'));

        newOrder[widget.widgetId] = index + 1;
    }

    if(JSON.stringify(newOrder) === JSON.stringify(oldOrder)) {
        return Promise.resolve({
            message: errorMessagesTrans.listOrderNotChanged
        });
    }

    return pE.manager.addChange(
        "order",
        "playlist",
        this.playlistId,
        {
            widgets: oldOrder
        },
        {
            widgets: newOrder
        }
    ).catch((error) => {
        toastr.error(errorMessagesTrans.playlistOrderSave);
        console.log(error);
    });

};

module.exports = Playlist;