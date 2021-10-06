// REGION Module

/**
 * Region contructor
 * @param {number} id - region id
 * @param {object} data - data from the API request
 * @param {object=} [options] - Region options
 * @param {string} [options.backgroundColor="#aaa"] - Color for the background
 */
let Region = function(id, data, {backgroundColor = '#aaa'} = {}) {
    this.id = 'region_' + id;
    this.regionId = id;
    this.type = 'region';
    this.name = data.name;

    this.playlists = data.regionPlaylist;

    this.backgroundColor = backgroundColor;
    this.selected = false;
    this.loop = false; // Loop region widgets

    this.isEmpty = true; // If the region has widgets or not

    // widget structure
    this.widgets = {};

    this.options = data.regionOptions;

    // Permissions
    this.isEditable = data.isEditable;
    this.isDeletable = data.isDeletable;
    this.isPermissionsModifiable = data.isPermissionsModifiable;

    // set real dimentions
    this.dimensions = {
        width: data.width,
        height: data.height,
        top: data.top,
        left: data.left
    };

    this.zIndex = data.zIndex;

    /**
     * Return the value if the region is selectd or not for the CSS
     */
    this.selectedFlag = function() {
        for(let widget in this.widgets) {
            if (this.widgets[widget].selected === true) {
                return 'selected-widget';
            }
        }

        return (this.selected) ? 'selected-region' : '';
    };

};

/**
 * Transform a region using the new values and the layout's scaling and save the values to the structure
 * @param {object=} [transform] - Transformation values
 * @param {number} [transform.width] - New width ( for resize tranformation )
 * @param {number} [transform.height] - New height ( for resize tranformation )
 * @param {number} [transform.top] - New top position ( for move tranformation )
 * @param {number} [transform.left] - New left position ( for move tranformation )
 * @param {bool=} saveToHistory - Flag to save or not to the change history
 */
Region.prototype.transform = function(transform, saveToHistory = true) {
    
    // add transform change to history manager
    if(saveToHistory) {

        // save old/previous values
        const oldValues = [{
            'width': this.dimensions.width,
            'height': this.dimensions.height,
            'top': this.dimensions.top,
            'left': this.dimensions.left,
            'regionid': this.regionId
        }];

        const newValues = [{
            'width': transform.width,
            'height': transform.height,
            'top': transform.top,
            'left': transform.left,
            'regionid': this.regionId
        }];

        // Add a tranform change to the history array, but send the upload flag as false, so that the change is temporarily local
        lD.manager.addChange(
            "transform",
            "region",
            this.regionId,
            {
                regions: JSON.stringify(oldValues)
            },
            {
                regions: JSON.stringify(newValues)
            },
            {
                upload: false // options.upload
            }
        ).catch((error) => { 
            toastr.error(errorMessagesTrans.transformRegionFailed);
            console.log(error);
        });
    }

    // Apply changes to the region ( updating values )
    this.dimensions.width = transform.width;
    this.dimensions.height = transform.height;
    this.dimensions.top = transform.top;
    this.dimensions.left = transform.left;
};

/**
 * Edit property by type
 * @param {string} property - property to edit
 */
Region.prototype.editPropertyForm = function(property) {

    const self = this;

    const app = lD;

    // Load form the API
    const linkToAPI = urlsForApi.region['get' + property];

    let requestPath = linkToAPI.url;

    // Replace widget id
    requestPath = requestPath.replace(':id', this.regionId);

    // Create dialog
    var calculatedId = new Date().getTime();

    // Create dialog
    let dialog = bootbox.dialog({
        className: 'second-dialog',
        title: editorsTrans.loadPropertyForObject.replace('%prop%', property).replace('%obj%', 'region'),
        message: '<p><i class="fa fa-spin fa-spinner"></i>' + editorsTrans.loading + '...</p>',
        size: 'large',
        buttons: {
            cancel: {
                label: translations.cancel,
                className: "btn-white btn-bb-cancel"
            },
            done: {
                label: translations.done,
                className: "btn-primary test btn-bb-done",
                callback: function(res) {

                    app.common.showLoadingScreen();

                    let dataToSave = '';
                    let options = {
                        addToHistory: false // options.addToHistory
                    };

                    // Get data to save
                    if(property === 'Permissions') {
                        dataToSave = formHelpers.permissionsFormBeforeSubmit(dialog);
                        options.customRequestPath = {
                            url: dialog.find('.permissionsGrid').data('url'),
                            type: 'POST'
                        };
                    } else {
                        dataToSave = form.serialize();
                    }

                    app.manager.addChange(
                        'save' + property,
                        'widget', // targetType 
                        self.regionId,  // targetId
                        null,  // oldValues
                        dataToSave, // newValues
                        options
                    ).then((res) => { // Success

                        app.common.hideLoadingScreen();

                        // Behavior if successful 
                        toastr.success(res.message);

                        dialog.modal('hide');

                        app.reloadData(app.layout);

                    }).catch((error) => { // Fail/error

                        app.common.hideLoadingScreen();

                        // Show error returned or custom message to the user
                        let errorMessage = '';

                        if(typeof error == 'string') {
                            errorMessage += error;
                        } else {
                            errorMessage += error.errorThrown;
                        }

                        // Display message in form
                        formHelpers.displayErrorMessage(dialog.find('form'), errorMessage, 'danger');

                        // Show toast message
                        toastr.error(errorMessage);
                    });
                }

            }
        }
    }).attr('id', calculatedId).attr('data-test', 'region' + property + 'Form');

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

            if(property == 'Permissions') {
                formHelpers.permissionsFormAfterOpen(dialog);
            }

            // Call Xibo Init for this form
            XiboInitialise('#' + dialog.attr('id'));

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

                dialog.modal('hide');
            }
        }

    }).catch(function(jqXHR, textStatus, errorThrown) {

        console.error(jqXHR, textStatus, errorThrown);
        toastr.error(errorMessagesTrans.formLoadFailed);

        dialog.modal('hide');
    });
};

module.exports = Region;