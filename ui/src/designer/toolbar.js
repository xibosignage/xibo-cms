// NAVIGATOR Module

// Load templates
const ToolbarTemplate = require('../templates/toolbar.hbs');

// Add global helpers
window.formHelpers = require('../helpers/form-helpers.js');

const defaultMenuItems = [
    {
        name: 'widgets',
        title: 'Widgets',
        pagination: false,
        page: 0,
        content: [],
        state: ''
    }
];

/**
 * Bottom toolbar contructor
 * @param {object} container - the container to render the navigator to
 */
let Toolbar = function(container) {
    this.DOMObject = container;
    this.openedMenu = -1;
    this.menuItems = defaultMenuItems;
    this.menuIndex = 0;

    this.contentDimentions = {
        width: 90 // In percentage
    };

    this.cardDimensions = {
        width: 100, // In pixels
        height: 70, // In pixels
        margin: 2 // In pixels
    };
};

/**
 * Render toolbar
 */
Toolbar.prototype.render = function() {

    // Compile layout template with data
    const html = ToolbarTemplate({
        opened: (this.openedMenu != -1),
        menuItems: this.menuItems,
        undo: ((lD.manager.changeHistory.length > 0) ? '' : 'disabled'),
        tabsCount: (lD.toolbar.menuItems.length > 1),
        deletableSelectedObject: (lD.selectedObject.type === 'region' || lD.selectedObject.type === 'widget')
    });

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Handle buttons
    for(let index = 0;index < this.menuItems.length;index++) {
        const element = this.menuItems[index];
        this.DOMObject.find('#btn-menu-' + index).click(function() {
            this.openTab(index);
        }.bind(this));

        this.DOMObject.find('#close-btn-menu-' + index).click(function() {
            this.deleteTab(index);
        }.bind(this));

        this.DOMObject.find('#content-' + index + ' #pag-btn-left').click(function() {
            this.menuItems[index].page -= 1;
            this.loadContent(index);
        }.bind(this));

        this.DOMObject.find('#content-' + index + ' #pag-btn-right').click(function() {
            this.menuItems[index].page += 1;
            this.loadContent(index);
        }.bind(this));

    }
    this.DOMObject.find('#btn-menu-toggle').click(function() {
        this.openTab();
    }.bind(this));

    this.DOMObject.find('#btn-menu-new-tab').click(function() {
        this.createNewTab();
    }.bind(this));

    this.DOMObject.find('#publishLayout').click(function() {
        // Show publish screen
        lD.showPublishScreen(lD.layout);
    });

    this.DOMObject.find('#undoLastAction').click(function() {
        lD.manager.revertChange().then((res) => { // Success

            toastr.success(res.message);

            // Refresh designer according to local or API revert
            if(res.localRevert) {
                lD.refreshDesigner();
            } else {
                lD.reloadData(lD.layout);
            }
        }).catch((error) => { // Fail/error

            // Show error returned or custom message to the user
            let errorMessage = 'Revert failed: ';

            if(typeof error == 'string') {
                errorMessage += error;
            } else {
                errorMessage += error.errorThrown;
            }

            toastr.error(errorMessage);
        });
    });

    this.DOMObject.find('#deleteAllTabs').click(function() {
        lD.toolbar.deleteAllTabs();
    });

    this.DOMObject.find('#trashContainer').click(function() {
        lD.toolbar.deleteObject(lD.selectedObject.type, lD.selectedObject[lD.selectedObject.type+'Id']);
    }).droppable({
        drop: function(event, ui) {

            const objectType = ui.draggable.data('type');
            let objectId = null;

            if(objectType === 'region') {
                objectId = lD.layout.regions[ui.draggable.attr('id')].regionId;
            } else if(objectType === 'widget') {
                objectId = lD.layout.regions[ui.draggable.data('widgetRegion')].widgets[ui.draggable.data('widgetId')].widgetId;
            }

            lD.toolbar.deleteObject(objectType, objectId);
        }
    });

    this.DOMObject.find('.search-btn').click(function() {
        // Reset page
        lD.toolbar.menuItems[$(this).attr("data-search-id")].page = 0;

        // Load content for the search tab
        lD.toolbar.loadContent($(this).attr("data-search-id"));
    });

    // Set cards width/margin and draggable properties
    this.DOMObject.find('.toolbar-card').width(
        this.cardDimensions.width
    ).height(
        this.cardDimensions.height
    ).css(
        'margin', this.cardDimensions.margin
    ).draggable({
        cursor: "crosshair",
        cursorAt: {
            top: (this.cardDimensions.height + this.cardDimensions.margin) / 2,
            left: (this.cardDimensions.width + this.cardDimensions.margin) / 2
        },
        opacity: 0.3,
        helper: 'clone'
    });

    // Initialize tooltips
    this.DOMObject.find('[data-toggle="tooltip"]').tooltip();

};

/**
 * Load content
 * @param {object} objectToDelete - menu to load content for
 */
Toolbar.prototype.deleteObject = function(objectType, objectId) {

    if(objectType === 'region' || objectType === 'widget') {

        bootbox.confirm({
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

                    // Delete element from the layout
                    lD.layout.deleteElement(objectType, objectId).then((res) => { // Success

                        // Behavior if successful 
                        toastr.success(res.message);
                        lD.reloadData(lD.layout);
                    }).catch((error) => { // Fail/error
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
        });
    }
};

/**
 * Load content
 * @param {number} menu - menu to load content for
 */
Toolbar.prototype.loadContent = function(menu = -1) {

    // Calculate pagination
    const pagination = lD.toolbar.calculatePagination(menu);

    // Enable/Disable page down pagination button according to the page to display
    this.menuItems[menu].pagBtnLeftDisabled = (pagination.start == 0) ? 'disabled' : '';

    // Replace search button with a spinner icon
    this.DOMObject.find('.search-btn').html('<i class="fa fa-spinner fa-spin"></i>');

    if(menu == 0) {
        this.menuItems[menu].content = modulesList;

        for(let index = 0;index < this.menuItems[menu].content.length;index++) {
            const element = this.menuItems[menu].content[index];

            element.maxSize = libraryUpload.maxSize;
            element.maxSizeMessage = libraryUpload.maxSizeMessage;

            // Hide element if it's outside the "to display" region
            element.hideElement = (index < pagination.start || index >= (pagination.start + pagination.length));
        }

        // Enable/Disable page up pagination button according to the page to display and total elements
        this.menuItems[menu].pagBtnRightDisabled = ((pagination.start + pagination.length) >= this.menuItems[menu].content.length) ? 'disabled' : '';

        this.render();
    } else {

        // Load using the API
        const linkToAPI = urlsForApi['library']['get'];

        // Save filters
        this.menuItems[menu].filters.name.value = this.DOMObject.find('#media-search-form-' + menu + ' #input-name').val();
        this.menuItems[menu].filters.tag.value = this.DOMObject.find('#media-search-form-' + menu + ' #input-tag').val();
        this.menuItems[menu].filters.type.value = this.DOMObject.find('#media-search-form-' + menu + ' #input-type').val();

        // Create filter
        let customFilter = {
            retired: 0,
            assignable: 1,
            start: pagination.start,
            length: pagination.length,
            media: this.menuItems[menu].filters.name.value,
            tags: this.menuItems[menu].filters.tag.value,
            type: this.menuItems[menu].filters.type.value
        };

        // Change tab name to reflect the search query
        this.menuItems[menu].title = (customFilter.media != '') ? '"' + customFilter.media + '"' : 'Search ' + menu;

        if(customFilter.tags != '' && customFilter.tags != undefined) {
            this.menuItems[menu].title += ' {' + customFilter.tags + '} ';
        }

        if(customFilter.type != '') {
            this.menuItems[menu].title += ' [' + customFilter.type + '] ';
        }

        // Request elements based on filters
        $.ajax({
            url: linkToAPI.url,
            type: linkToAPI.type,
            data: customFilter
        }).done(function(res) {

            if(res.data.length == 0) {
                toastr.info('No results for the filter!', 'Search');
                lD.toolbar.menuItems[menu].content = null;
            } else {
                lD.toolbar.menuItems[menu].content = res.data;
            }

            // Enable/Disable page up pagination button according to the page to display and total elements
            lD.toolbar.menuItems[menu].pagBtnRightDisabled = ((pagination.start + pagination.length) >= res.recordsTotal) ? 'disabled' : '';

            lD.toolbar.render();
        }).catch(function(jqXHR, textStatus, errorThrown) {

            console.error(jqXHR, textStatus, errorThrown);
            toastr.error('Library load failed!');

            lD.toolbar.menuItems[menu].content = null;
        });
    }
}

/**
 * Open menu
 * @param {number} menu - menu to open index, -1 by default and to toggle
 */
Toolbar.prototype.openTab = function(menu = -1) {

    // Close previous opened menu
    if(menu == -1 && this.openedMenu != -1) {
        this.menuItems[this.openedMenu].state = '';
        this.openedMenu = -1;
    } else if(menu != -1) {

        // Close all menus
        for(let index = this.menuItems.length - 1;index >= 0;index--) {
            this.menuItems[index].state = '';
        }

        // If menu is the default/widget, load content
        if(menu == 0) {
            this.loadContent(0);
        }

        this.menuItems[menu].state = 'active';
        this.openedMenu = menu;
    }

    this.render();
}

/**
 * Create new tab
 */
Toolbar.prototype.createNewTab = function() {

    let moduleListFiltered = [];

    // Filter module list to create the types for the filter
    modulesList.forEach(element => {
        if(element.assignable == 1 && element.regionSpecific == 0) {
            moduleListFiltered.push(element);
        }
    });

    this.menuIndex += 1;

    this.menuItems.push({
        name: 'search',
        title: 'Search ' + this.menuIndex,
        search: true,
        page: 0,
        query: '',
        filters: {
            name: {
                name: 'Name',
                value: ''
            },
            tag: {
                name: 'Tag',
                value: ''
            },
            type: {
                name: 'Type',
                values: moduleListFiltered
            },
        },
        content: []
    });

    this.openTab(this.menuItems.length - 1);

    this.render();
}

/**
 * Delete tab
 * @param {number} menu
 */
Toolbar.prototype.deleteTab = function(menu) {

    this.menuItems.splice(menu, 1);
    this.openedMenu = -1;

    this.render();
}

/**
 * Delete all tabs
 */
Toolbar.prototype.deleteAllTabs = function() {

    for(let index = this.menuItems.length - 1;index > 0;index--) {
        this.menuItems.splice(index, 1);
    }
    this.openedMenu = -1;

    this.render();
}

/**
 * Calculate pagination
 * @param {number} menu
 */
Toolbar.prototype.calculatePagination = function(menu) {

    // Get page and number of elements
    const currentPage = this.menuItems[menu].page;

    // Calculate width
    const containerWidth = this.DOMObject.find('.toolbar-content').width() * (this.contentDimentions.width / 100);

    // Calculate number of elements to display
    const elementsToDisplay = Math.floor(containerWidth / (this.cardDimensions.width + this.cardDimensions.margin * 2));

    this.menuItems[menu].contentWidth = this.contentDimentions.width;

    return {
        start: currentPage * elementsToDisplay,
        length: elementsToDisplay
    };
}

/**
 * Add action to take after dropping a draggable item
 * @param {object} droppable - Target drop are object
 * @param {object} draggable - Dragged object
 */
Toolbar.prototype.dropItemAdd = function(droppable, draggable) {

    const droppableId = $(droppable).attr('id');
    const droppableType = $(droppable).data('type');

    const draggableType = $(draggable).data('type');

    // Get playlist Id
    const playlistId = lD.layout.regions[droppableId].playlists.playlistId;

    /**
     * Add dragged item to region
     */
    if(draggableType == 'media') { // Adding media from search tab to a region

        // Get media Id
        const mediaToAdd = {
            media: [
                $(draggable).data('mediaId')
            ]
        };

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
            lD.timeline.resetZoom();
            lD.reloadData(lD.layout);
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

            this.openUploadForm({
                trans: playlistTrans,
                upload: {
                    maxSize: $(draggable).data().maxSize,
                    maxSizeMessage: $(draggable).data().maxSizeMessage,
                    validExtensionsMessage: translations.validExtensions + ': ' + $(draggable).data('validExt'),
                    validExt: validExt
                },
                playlistId: playlistId
            }, {
                    main: {
                        label: translations.done,
                        className: "btn-primary",
                        callback: function() {
                            lD.timeline.resetZoom();
                            lD.reloadData(lD.layout);
                        }
                    }
                });

        } else { // Load add widget form for region specific

            // Get playlist Id
            const playlistId = lD.layout.regions[droppableId].playlists.playlistId;

            // Load form the API
            const linkToAPI = urlsForApi['playlist']['addWidgetForm'];

            let requestPath = linkToAPI.url;

            // Replace type
            requestPath = requestPath.replace(':type', draggableType);

            // Replace playlist id
            requestPath = requestPath.replace(':id', playlistId);

            // Select region ( and avoid deselect if region was already selected )
            lD.selectObject($(droppable), true);

            // Create dialog
            var calculatedId = new Date().getTime();

            let dialog = bootbox.dialog({
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

                                lD.manager.addChange(
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

                                    lD.timeline.resetZoom();
                                    lD.reloadData(lD.layout);
                                    
                                }).catch((error) => { // Fail/error

                                    // Show error returned or custom message to the user
                                    let errorMessage = '';

                                    if(typeof error == 'string') {
                                        errorMessage += error;
                                    } else {
                                        errorMessage += error.errorThrown;
                                    }

                                    // Remove added change from the history manager
                                    lD.manager.removeLastChange();

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
                    if(data.login) {

                        window.location.href = window.location.href;
                        location.reload(false);
                    } else {

                        toastr.error('Element form load failed!');

                        // Just an error we dont know about
                        if(data.message == undefined) {
                            console.error(data);
                        } else {
                            console.error(data.message);
                        }

                        dialog.modal('hide');
                    }
                }
            }).catch(function(jqXHR, textStatus, errorThrown) {

                console.error(jqXHR, textStatus, errorThrown);
                toastr.error('Element form load failed!');

                dialog.modal('hide')
            });
        }
    }
};

/**
 * Open Upload Form
 * @param {object} templateOptions
 * @param {object} buttons
 */
Toolbar.prototype.openUploadForm = function(templateOptions, buttons) {

    // Close the current dialog
    bootbox.hideAll();

    var template = Handlebars.compile($("#template-file-upload").html());

    // Handle bars and open a dialog
    bootbox.dialog({
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
Toolbar.prototype.openUploadFormModelShown = function(form) {

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
}

module.exports = Toolbar;