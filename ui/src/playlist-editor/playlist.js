// PLAYLIST Module
const Widget = require('../designer/widget.js');

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
    this.isEmpty = true;

    this.widgets = {};
    this.duration = null;

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
 */
Playlist.prototype.addElement = function(droppable, draggable) {

    const draggableType = $(draggable).data('type');
    const draggableSubType = $(draggable).data('subType');

    // Get playlist Id
    const playlistId = this.playlistId;

    // Add dragged item to region
    if(draggableType == 'media') { // Adding media from search tab to a region

        // Get media Id
        const mediaToAdd = {
            media: [
                $(draggable).data('mediaId')
            ]
        };

        // Show loading screen in the dropzone
        pE.showLocalLoadingScreen();

        pE.common.showLoadingScreen();

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
    } else if(draggableType == 'module') { // Add widget/module

        // Get regionSpecific property
        const regionSpecific = $(draggable).data('regionSpecific');

        if(regionSpecific == 0) { // Upload form if not region specific

            const validExt = $(draggable).data('validExt').replace(/,/g, "|");

            pE.openUploadForm({
                trans: uploadTrans,
                upload: {
                    maxSize: $(draggable).data().maxSize,
                    maxSizeMessage: $(draggable).data().maxSizeMessage,
                    validExtensionsMessage: translations.validExtensions + ': ' + $(draggable).data('validExt'),
                    validExt: validExt
                },
                playlistId: playlistId
            },
            {
                viewLibrary: {
                    label: uploadTrans.viewLibrary,
                    className: "btn-white",
                    callback: function() {
                        pE.toolbar.openNewTabAndSearch(draggableSubType);
                    }
                },
                main: {
                    label: translations.done,
                    className: "btn-primary",
                    callback: function() {
                        pE.reloadData();
                    }
                }
            });

        } else { // Add widget to a region

            const linkToAPI = urlsForApi.playlist.addWidget;

            let requestPath = linkToAPI.url;

            pE.common.showLoadingScreen('addModuleToPlaylist');

            // Replace type
            requestPath = requestPath.replace(':type', draggableSubType);

            // Replace playlist id
            requestPath = requestPath.replace(':id', playlistId);

            pE.manager.addChange(
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

                pE.common.hideLoadingScreen('addModuleToPlaylist');

                // The new selected object
                pE.selectedObject.id = 'widget_' + res.data.widgetId;

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
 * Delete an element in the playlist, by ID
 * @param {number} elementId - element id
 * @param {string} elementType - element type (widget, region, ...)
 */
Playlist.prototype.deleteElement = function(elementType, elementId) {

    pE.common.showLoadingScreen();

    // Remove changes from the history array
    return pE.manager.removeAllChanges(pE.selectedObject.type, pE.selectedObject[pE.selectedObject.type + 'Id']).then((res) => {

        pE.common.hideLoadingScreen();

        // Unselect selected object before deleting
        pE.selectObject(null, true);

        // Create a delete type change, upload it but don't add it to the history array
        return pE.manager.addChange(
            "delete",
            elementType, // targetType
            elementId, // targetId
            null, // oldValues
            null, // newValues
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