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
        playlistDuration += newWidget.getDuration();
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
 * Add a new empty element to the playlist
 * @param {string} elementType - element type (widget, region, ...)
 */
Playlist.prototype.addElement = function(draggable) {
    const draggableType = $(draggable).data('type');

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
        pE.showLoadingScreen();

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

            // Behavior if successful            
            toastr.success(res.message);
            pE.reloadData();
        }).catch((error) => { // Fail/error

            // Show error returned or custom message to the user
            let errorMessage = 'Add media failed: ';

            if(typeof error == 'string') {
                errorMessage += error;
            } else {
                errorMessage += error.errorThrown;
            }

            toastr.error(errorMessage);
        });
    } else { // Add widget/module

        // Get regionSpecific property
        const regionSpecific = $(draggable).data('regionSpecific');

        if(regionSpecific == 0) { // Upload form if not region specific

            const validExt = $(draggable).data('validExt').replace(/,/g, "|");

            pE.openUploadForm({
                trans: playlistTrans,
                upload: {
                    maxSize: $(draggable).data().maxSize,
                    maxSizeMessage: $(draggable).data().maxSizeMessage,
                    validExtensionsMessage: translations.validExtensions + ': ' + $(draggable).data('validExt'),
                    validExt: validExt
                },
                playlistId: playlistId
            }, 
            {
                main: {
                    label: translations.done,
                    className: "btn-primary",
                    callback: function() {
                        pE.reloadData();
                    }
                }
            });

        } else { // Load add widget form for region specific

            // Load form the API
            const linkToAPI = urlsForApi.playlist.addWidgetForm;

            let requestPath = linkToAPI.url;

            // Replace type
            requestPath = requestPath.replace(':type', draggableType);

            // Replace playlist id
            requestPath = requestPath.replace(':id', playlistId);

            // Create dialog
            var calculatedId = new Date().getTime();

            let dialog = bootbox.dialog({
                className: 'second-dialog',
                title: 'Add ' + draggableType + ' widget',
                message: '<p><i class="fa fa-spin fa-spinner"></i> Loading...</p>',
                buttons: {
                    cancel: {
                        label: translations.cancel,
                        className: "btn-default"
                    },
                    done: {
                        label: translations.done,
                        className: "btn-primary test",
                        callback: function(res) {

                            // Run form open module optional function
                            if(typeof window[draggableType + '_form_add_submit'] === 'function') {
                                window[draggableType + '_form_add_submit'].bind(dialog)();
                            }

                            // If form is valid, submit it ( add change )
                            if($(dialog).find('form').valid()) {

                                const form = dialog.find('form');

                                // Show loading screen in the dropzone
                                pE.showLoadingScreen();

                                pE.manager.addChange(
                                    'addWidget',
                                    'playlist', // targetType 
                                    playlistId,  // targetId
                                    null,  // oldValues
                                    form.serialize(), // newValues
                                    {
                                        updateTargetId: true,
                                        updateTargetType: 'widget',
                                        customRequestPath: {
                                            url: form.attr('action'),
                                            type: form.attr('method')
                                        }
                                    }
                                ).then((res) => { // Success

                                    // Behavior if successful 
                                    toastr.success(res.message);

                                    dialog.modal('hide');

                                    pE.reloadData();

                                }).catch((error) => { // Fail/error

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

                            // Prevent the modal to close ( close only when addChange returns true )
                            return false;
                        }
                    }
                }
            }).attr("id", calculatedId);

            // Request and load element form
            $.ajax({
                url: requestPath,
                type: linkToAPI.type
            }).done(function(res) {

                if(res.success) {
                    // Add title
                    dialog.find('.modal-title').html(res.dialogTitle);

                    // Add body main content
                    dialog.find('.bootbox-body').html(res.html);

                    dialog.data('extra', res.extra);

                    // Call Xibo Init for this form
                    XiboInitialise("#" + dialog.attr("id"));

                    // Run form open module optional function
                    if(typeof window[draggableType + '_form_add_open'] === 'function') {
                        window[draggableType + '_form_add_open'].bind(dialog)();
                    }

                } else {

                    // Login Form needed?
                    if(res.login) {

                        window.location.href = window.location.href;
                        location.reload(false);
                    } else {

                        toastr.error('Element form load failed!');

                        // Just an error we dont know about
                        if(res.message == undefined) {
                            console.error(res);
                        } else {
                            console.error(res.message);
                        }

                        dialog.modal('hide');
                    }
                }
            }).catch(function(jqXHR, textStatus, errorThrown) {

                console.error(jqXHR, textStatus, errorThrown);
                toastr.error('Element form load failed!');

                dialog.modal('hide');
            });
        }
    }
};

/**
 * Delete an element in the playlist, by ID
 * @param {number} elementId - element id
 * @param {string} elementType - element type (widget, region, ...)
 */
Playlist.prototype.deleteElement = function(elementType, elementId) {

    // Remove changes from the history array
    return pE.manager.removeAllChanges(pE.selectedObject.type, pE.selectedObject[pE.selectedObject.type + 'Id']).then((res) =>  {

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
        toastr.error('Remove all changes failed!');
    });
    
};

/**
 * Save playlist order
 * @param {object} widgets - Widgets DOM objects array
 */
Playlist.prototype.saveOrder = function(widgets) {

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
        const widget = pE.getElementByTypeAndId('widget', $(widgets[index]).data('widgetId'));

        newOrder[widget.widgetId] = index + 1;
    }

    if(JSON.stringify(newOrder) === JSON.stringify(oldOrder) ) {
        return Promise.resolve({
            message: 'List order not Changed!'
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
        toastr.error('Playlist save order failed! ' + error);
    });

};

module.exports = Playlist;