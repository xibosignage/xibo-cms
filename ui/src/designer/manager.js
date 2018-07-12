// MANAGER Module
/**
 * History manager, that stores all the changes and operations that can be applied to them (upload/revert)
 */

const Change = require('./change.js');
const managerTemplate = require('../templates/manager.hbs');

// Map from a operation to its inverse, and detail if the operation is done on the element or the layout
const inverseChangeMap = {
    transform: {
        inverse: 'transform',
        useElement: true
    },
    create: {
        inverse: 'delete',
        useElement: false
    },
    saveForm: {
        inverse: 'saveForm',
        useElement: true
    },
    addMedia: {
        inverse: 'delete',
        useElement: true
    },
    addWidget: {
        inverse: 'delete',
        useElement: true
    }
};

/**
 * Layout Editor Manager
 */
let Manager = function(container) {

    this.DOMObject = container;

    this.visible = true;

    this.changeUniqueId = 0;

    this.changeHistory = []; // Array of changes

    this.toggleVisibility = function() {
        
        lD.manager.visible = !lD.manager.visible;

        // Render template container
        lD.manager.render();
    };

    // Return true if there are some not uploaded changes
    this.changesToUpload = function() {

        for(let index = this.changeHistory.length - 1;index >= 0;index--) {

            if(!this.changeHistory[index].uploaded) {
                return true;
            }
        }

        return false;
    }
};

/**
 * Save a editor change to the history array
 * @param  {string} type -Type of change ( resize, move )
 * @param  {string} targetType - Target object Type ( widget, region, layout, ... )
 * @param  {string} targetID - Target object ID
 * @param {object=} [oldValues] - Previous object change
 * @param {object=} [newValues] - New object change
 * @param {object =} [options] - Navigator options
 * @param {bool=} [options.upload = true] - Upload change in real time
 * @param {bool=} [options.addToHistory = true] - Add change to the history array
 * @param {bool=} [options.updateTargetId = false] - Update change target id with the one returned from the API on upload
 * @param {string=} [options.updateTargetType = null] - Update change target type after upload with the value passed on this variable
 * @param {object=} [options.customRequestPath = null] - Custom Request Path ( url and type )
*/
Manager.prototype.addChange = function(changeType, targetType, targetId, oldValues, newValues, {upload = true, addToHistory = true, updateTargetId = false, updateTargetType = null, customRequestPath = null} = {}) {

    const changeId = this.changeUniqueId++;

    // create new change and add it to the array
    const newChange = new Change(
        changeId,
        changeType,
        targetType,
        targetId,
        oldValues,
        newValues
    );

    // Add change to the history array
    if(addToHistory) {
        this.changeHistory.push(newChange);

        // Render template container
        this.render();
    }

    // Upload change
    if(upload) {
        return this.uploadChange(newChange, updateTargetId, updateTargetType, customRequestPath);
    } else {
        return Promise.resolve('Change added!');
    }

};

/**
 * Upload first change in the history array
*/
Manager.prototype.uploadChange = function(change, updateId, updateType, customRequestPath) {

    // Test for empty history array
    if(!change || change.uploaded) {
        return Promise.reject('Change already uploaded!');
    }

    const linkToAPI = (customRequestPath != null) ? customRequestPath : urlsForApi[change.target.type][change.type];

    let requestPath = linkToAPI.url;

    // replace id if necessary/exists
    if(change.target) {
        requestPath = requestPath.replace(':id', change.target.id);
    }

    // Run ajax request and save promise
    return new Promise(function(resolve, reject) {
        $.ajax({
            url: requestPath,
            type: linkToAPI.type,
            data: change.newState,
        }).done(function(data) {
            if(data.success) {

                change.uploaded = true;

                // Update the Id of the change with the new element
                if(updateId) {
                    if(change.type === 'create' || change.type === 'addWidget') {
                        change.target.id = data.id;
                    } else if(change.type === 'addMedia') {
                        change.target.id = data.data.newWidgets[0].widgetId;
                    }
                }

                // If set: Update the type of change to enable the revert
                if(updateType != null) {
                    change.target.type = updateType;
                }

                // Resolve promise
                resolve(data);

            } else {

                // Login Form needed?
                if(data.login) {

                    window.location.href = window.location.href;
                    location.reload(false);
                } else {
                    // Just an error we dont know about
                    if(data.message == undefined) {
                        reject(data);
                    } else {
                        reject(data.message);
                    }
                }
            }

            // Render/Update Manager
            lD.manager.render();

        }.bind(change)).fail(function(jqXHR, textStatus, errorThrown) {
            // Output error to console
            console.error(jqXHR, textStatus, errorThrown);

            // Reject promise and return an object with all values
            reject({jqXHR, textStatus, errorThrown});
        });
    });
};


/**
 * Revert change by ID or the last one in the history array
*/
Manager.prototype.revertChange = function() {

    // Prevent trying to revert if there are no changes in history
    if(this.changeHistory.length <= 0) {
        return Promise.reject('There are no changes in history!');
    }

    // Get the last change in the array
    const lastChange = this.changeHistory[this.changeHistory.length - 1];

    const uploadedState = lastChange.uploaded;
    const inverseOperation = inverseChangeMap[lastChange.type].inverse;
    const useElement = inverseChangeMap[lastChange.type].useElement;

    let operationResultSuccess = false;

    return new Promise(function(resolve, reject) {

        if(!lastChange.uploaded) { // Revert on the client side

            //FIXME: Check if local revert is used more than with regions transform, if not, refactor this
            if(useElement) {
                // Get data to apply
                let data = lastChange.oldState;

                // Get element by type
                let element = {};

                if(lastChange.target.type === 'layout') {
                    element = lD.layout;
                } else if(lastChange.target.type === 'region') {
                    element = lD.layout.regions['region_' + lastChange.target.id];
                }

                // If the operation is a transform, parse data
                if(inverseOperation === 'transform') {
                    data = JSON.parse(data.regions)[0];
                }

                // Apply inverse operation to the element
                element[inverseOperation](data, false);

                // Remove change from history
                lD.manager.changeHistory.pop();

                resolve({
                    type: inverseOperation,
                    target: lastChange.target.type,
                    message: 'Change reverted',
                    localRevert: true
                });
            } else {
                const revertSuccess = lD.layout[inverseOperation](lastChange.target.id, lastChange.target.type, lastChange.oldState);

                if(revertSuccess) {

                    // Remove change from history
                    lD.manager.changeHistory.pop();

                    resolve({
                        type: inverseOperation,
                        target: lastChange.target.type,
                        message: 'Change reverted',
                        localRevert: true
                    });
                } else {
                    reject('Revert operation failed!');
                }
            }

        } else { // Revert using the API

            const linkToAPI = urlsForApi[lastChange.target.type][inverseOperation];
            let requestPath = linkToAPI.url;

            // replace id if necessary/exists
            requestPath = requestPath.replace(':id', lastChange.target.id);

            $.ajax({
                url: requestPath,
                type: linkToAPI.type,
                data: lastChange.oldState,
            }).done(function(data) {
                if(data.success) {

                    // Remove change from history
                    lD.manager.changeHistory.pop();

                    // Resolve promise
                    resolve(data);

                } else {
                    // Login Form needed?
                    if(data.login) {

                        window.location.href = window.location.href;
                        location.reload(false);
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
                // Output error to console
                console.error(jqXHR, textStatus, errorThrown);

                // Reject promise and return an object with all values
                reject({jqXHR, textStatus, errorThrown});
            });
        }
    });
};

/**
 * Save all the changes in the history array
*/
Manager.prototype.saveAllChanges = async function() {

    // stop method if there are no changes to be saved
    if(!this.changesToUpload()) {
        return Promise.resolve('No changes to upload');
    }

    let promiseArray = [];
        
    for(let index = 0;index < lD.manager.changeHistory.length;index++) {

        const change = lD.manager.changeHistory[index];

        // skip already uploaded changes
        if(change.uploaded) {
            continue;
        }
        
        promiseArray.push(await lD.manager.uploadChange(change));

        // Render manager container to update the change
        lD.manager.render();
    }

    return Promise.all(promiseArray);
};

/**
 * Remove all the changes in the history array related to a specific object
 * @param  {string} targetType - Target object Type ( widget, region, layout, ... )
 * @param  {string} targetId - Target object ID
*/
Manager.prototype.removeAllChanges = function(targetType, targetId) {

    return new Promise(function(resolve, reject) {
        
        for(let index = 0;index < lD.manager.changeHistory.length;index++) {

            const change = lD.manager.changeHistory[index];
            
            if(change.target.type === targetType && change.target.id === targetId) {

                lD.manager.changeHistory.splice(index, 1);

                // When change is removed, we need to decrement the index
                index--;
            }
        }

        // Render template container
        lD.manager.render();

        resolve('All Changes Removed');
    });
};


/**
 * Remove last change
*/
Manager.prototype.removeLastChange = function() {
    lD.manager.changeHistory.pop();
    lD.manager.render();
};

/**
 * Render Manager
 */
Manager.prototype.render = function() {
    // Compile layout template with data
    const html = managerTemplate(this);

    // Append layout html to the main div
    this.DOMObject.html(html);

    //Make the history div draggable
    this.DOMObject.draggable();

    //Add toggle visibility event
    this.DOMObject.find('#layout-manager-header .title').click(this.toggleVisibility);
};

module.exports = Manager;