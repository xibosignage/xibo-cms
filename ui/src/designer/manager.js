// MANAGER Module
const Change = require('./change.js');
const managerTemplate = require('../templates/manager.hbs');

/**
 * Layout Editor Manager
 */
let Manager = function(container) {

    this.DOMObject = container;

    this.visible = true;

    this.changeUniqueId = 0;

    this.changeHistory = []; // Array of changes

    this.toggleVisibility = function() {
        
        this.visible = !this.visible;

        // Render template container
        this.render();
    };

    // Return true if there are some not uploaded changes
    this.changesToUpload = function() {
        
        for(let index = this.changeHistory.length - 1;index >=0 ;index--) {

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
 * @param {bool=} [upload=true] - Upload change in real time
 * @param {bool=} [addToHistory=true] - Add change to the history array
*/
Manager.prototype.addChange = function(changeType, targetType, targetID, oldValues, newValues, upload = true, addToHistory = true) {
    
    const changeId = this.changeUniqueId++;
    
    // create new change and add it to the array
    const newChange = new Change (
            changeId,
            changeType,
            targetType,
            targetID,
            oldValues,
            newValues
    );

    // Add change to the history array
    if(addToHistory) {
        this.changeHistory.push(newChange);
    }

    // Upload change
    if(upload) {
        this.uploadChange(newChange);
    }

    // Render template container
    this.render();
};

/**
 * Revert change by ID or the last one in the history array
*/
Manager.prototype.revertChange = function() {

    // Prevent trying to revert if there are no changes in history
    if(this.changeHistory.length <= 0) {
        return false;
    }

    const lastChange = this.changeHistory[this.changeHistory.length-1];
    const uploadedState = lastChange.uploaded;

    toastr.info('Reverting ' + lastChange.target.type + ' change: ' + lastChange.type);

    //Client side revert
    lastChange.revert().then(function(res) {
        if(res) {
            this.changeHistory.pop();

            // Reload after a API revert
            if(uploadedState) {
                lD.reloadData(lD.layout);
            } else {
                lD.refreshDesigner();
            }


            toastr.success('Change reverted!');
        }

        // Render template container
        this.render();
    }.bind(this));

    return true;
};

/**
 * Upload first change in the history array
*/
Manager.prototype.uploadChange = function(change) {

    // Test for empty history array
    if(!change || change.uploaded) {
        toastr.info('Change already uploaded!');
        return false;
    }

    toastr.info('Uploading ' + change.target.type + ' change: ' + change.type);

    // upload change and pass the callback so that the manager can process the result
    change.upload().then(function(res) {
        
        if(res.success) {
            if(change.type === 'create') {
                change.target.id = res.id;
            }
            
            toastr.success('Change uploaded!');
            
            lD.reloadData(lD.layout);
            
        } else {
            toastr.error('Change upload failed!');
        }
        // Render template container
        this.render();
    }.bind(this)).catch(function(jXHR, textStatus, errorThrown) {
        toastr.error(errorThrown, 'Upload failed!');
        console.log(jXHR, textStatus, errorThrownHR);
    });
};

/**
 * Save all the changes in the history array
*/
Manager.prototype.saveAllChanges = async function() {

    // stop method if there are no changes to be saved
    if(!this.changesToUpload()) {
        return;
    }

    toastr.info('Uploading changes!');

    let saveAllResult = true;

    for(let index = 0; index < this.changeHistory.length; index++) {

        const change = this.changeHistory[index];

        // skip already uploaded changes
        if(change.uploaded) {
            continue;
        }

        await change.upload().then(function(res) {
            if(!res.success) {
                saveAllResult = false;
                toastr.error('Change upload failed!');
            }
        }).catch(function(jXHR, textStatus, errorThrown) {
            return Promise.reject('Not all results were saved');
        });

        // Render template container
        this.render();
    }

    if(saveAllResult) {
        toastr.success('All Changes Saved!');
        lD.reloadData(lD.layout);
        return Promise.resolve('All Changes Saved');
    }
};


/**
 * Remove all the changes in the history array related to a specific object
 * @param  {string} targetType - Target object Type ( widget, region, layout, ... )
 * @param  {string} targetId - Target object ID
*/
Manager.prototype.removeAllChanges = async function(targetType, targetId) {

    toastr.info('Remove changes!');
    let removals = 0;

    
    for(let index = 0; index < this.changeHistory.length; index++) {

        const change = this.changeHistory[index];

        if(change.target.type === targetType && change.target.id === targetId) {

            this.changeHistory.splice(index, 1);

            removals++;
            index--;
        }

    }

    // Render template container
    this.render();

    toastr.success('All Changes Removed!');
    return Promise.resolve('All Changes Removed');
};

/**
 * Render Manager
 */
Manager.prototype.render = function() {
    
    // Compile layout template with data
    const html = managerTemplate(this);

    // Append layout html to the main div
    this.DOMObject.html(html);
};

module.exports = Manager;