// MANAGER Module
/**
 * History manager, that stores all the changes and operations that can be applied to them (upload/revert)
 */

const Change = require('../core/change.js');
const managerTemplate = require('../templates/manager.hbs');

// Map from a operation to its inverse, and detail if the operation is done on the element or the layout
const inverseChangeMap = {
    transform: {
        inverse: 'transform',
        parseData: true
    },
    create: {
        inverse: 'delete'
    },
    saveForm: {
        inverse: 'saveForm'
    },
    addMedia: {
        inverse: 'delete'
    },
    addWidget: {
        inverse: 'delete'
    },
    order: {
        inverse: 'order'
    }
};

/**
 * History Manager
 */
let Manager = function(parent, container, visible) {

    this.parent = parent;
    
    this.DOMObject = container;

    this.extended = true;

    this.visible = visible;

    this.changeUniqueId = 0;

    this.changeHistory = []; // Array of changes

    this.toggleExtended = function() {  
        this.extended = !this.extended;

        // Render template container
        this.render();
    };

    // Return true if there are some not uploaded changes
    this.changesToUpload = function() {

        for(let index = this.changeHistory.length - 1;index >= 0;index--) {

            if(!this.changeHistory[index].uploaded) {
                return true;
            }
        }

        return false;
    };
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
 * @param {object=} [options.customRequestReplace = null] - Custom Request replace ( tag and replace )
*/
Manager.prototype.addChange = function(changeType, targetType, targetId, oldValues, newValues, {upload = true, addToHistory = true, updateTargetId = false, updateTargetType = null, customRequestPath = null, customRequestReplace = null} = {}) {

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
        return this.uploadChange(newChange, updateTargetId, updateTargetType, customRequestPath, customRequestReplace);
    } else {
        return Promise.resolve('Change added!');
    }

};

/**
 * Upload first change in the history array
 * 
 * @param {object} change 
 * @param {bool=} updateId 
 * @param {string=} updateType 
 * @param {object=} customRequestPath 
 * @param {object=} customRequestReplace 
 */
Manager.prototype.uploadChange = function(change, updateId, updateType, customRequestPath, customRequestReplace) {

    const self = this;
    const app = this.parent;

    // Test for empty history array
    if(!change || change.uploaded ) {
        return Promise.reject('Change already uploaded!');
    }

    change.uploading = true;

    const linkToAPI = (customRequestPath != null) ? customRequestPath : urlsForApi[change.target.type][change.type];

    let requestPath = linkToAPI.url;

    // Custom replace tag
    if(customRequestReplace) {
        requestPath = requestPath.replace(customRequestReplace.tag, customRequestReplace.replace);
    }

    // replace id if necessary/exists
    if(change.target) {
        let replaceId = change.target.id;

        // If the replaceId is not set or the change needs the main object Id
        if(replaceId == null || linkToAPI.useMainObjectId) {
            replaceId = app.mainObjectId;
        }

        requestPath = requestPath.replace(':id', replaceId);
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
                change.uploading = false;

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
            self.render();

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

    const self = this;

    const app = this.parent;

    // Get the last change in the array
    const lastChange = this.changeHistory[this.changeHistory.length - 1];

    const inverseOperation = inverseChangeMap[lastChange.type].inverse;
    const parseData = inverseChangeMap[lastChange.type].parseData;

    return new Promise(function(resolve, reject) {

        if(!lastChange.uploaded) { // Revert on the client side ( non uploaded change )

            // Get data to apply
            let data = lastChange.oldState;

            // Get element by type,from the main object
            let element = app.getElementByTypeAndId(
                lastChange.target.type, // Type
                lastChange.target.type + '_' + lastChange.target.id // Id
            );

            // If the operation needs data parsing
            if(parseData != undefined && parseData) {
                data = JSON.parse(data.regions)[0];
            }

            // Apply inverse operation to the element
            element[inverseOperation](data, false);

            // Remove change from history
            self.changeHistory.pop();

            resolve({
                type: inverseOperation,
                target: lastChange.target.type,
                message: 'Change reverted',
                localRevert: true
            });
        } else { // Revert using the API

            const linkToAPI = urlsForApi[lastChange.target.type][inverseOperation];
            let requestPath = linkToAPI.url;

            // replace id if necessary/exists
            if(lastChange.target) {
                let replaceId = lastChange.target.id;

                // If the replaceId is not set or the change needs the layoutId, set it to the replace var
                if(replaceId == null || linkToAPI.useMainObjectId) {
                    replaceId = app.mainObjectId;
                }

                requestPath = requestPath.replace(':id', replaceId);
            }

            $.ajax({
                url: requestPath,
                type: linkToAPI.type,
                data: lastChange.oldState,
            }).done(function(data) {
                if(data.success) {

                    // Remove change from history
                    self.changeHistory.pop();

                    // If the operation is a deletion, unselect object before deleting
                    if(inverseOperation === 'delete') {

                        // Unselect selected object before deleting
                        app.selectObject();
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

    const self = this;

    // stop method if there are no changes to be saved
    if(!this.changesToUpload()) {
        return Promise.resolve('No changes to upload');
    }

    let promiseArray = [];
        
    for(let index = 0;index < self.changeHistory.length;index++) {

        const change = self.changeHistory[index];

        // skip already uploaded changes
        if(change.uploaded || change.uploading) {
            continue;
        }
        
        change.uploading = true;
        
        promiseArray.push(await self.uploadChange(change));

        // Render manager container to update the change
        self.render();
    }

    return Promise.all(promiseArray);
};

/**
 * Remove all the changes in the history array related to a specific object
 * @param  {string} targetType - Target object Type ( widget, region, layout, ... )
 * @param  {string} targetId - Target object ID
*/
Manager.prototype.removeAllChanges = function(targetType, targetId) {

    const self = this;

    return new Promise(function(resolve, reject) {
        
        for(let index = 0;index < self.changeHistory.length;index++) {

            const change = self.changeHistory[index];
            
            if(change.target.type === targetType && change.target.id === targetId) {

                self.changeHistory.splice(index, 1);

                // When change is removed, we need to decrement the index
                index--;
            }
        }

        // Render template container
        self.render();

        resolve('All Changes Removed');
    });
};


/**
 * Remove last change
*/
Manager.prototype.removeLastChange = function() {
    
    this.changeHistory.pop();
    this.render();
};

/**
 * Render Manager
 */
Manager.prototype.render = function() {

    if(this.visible == false) {
        return;
    }
    // Compile layout template with data
    const html = managerTemplate(this);

    // Append layout html to the main div
    this.DOMObject.html(html);

    //Make the history div draggable
    this.DOMObject.draggable();

    //Add toggle visibility event
    this.DOMObject.find('#layout-manager-header .title').click(this.toggleExtended.bind(this));
};

module.exports = Manager;