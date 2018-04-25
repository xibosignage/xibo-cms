// MANAGER Module
const Change = require('./change.js');
const managerTemplate = require('../templates/manager.hbs');

/**
 * Layout Editor Manager
 */
let Manager = function(container) {

    this.DOMObject = container;

    this.changeHistory = []; // Array of changes
};

/**
 * Save a editor change to the history array
 * @param  {string} type -Type of change ( resize, move )
 * @param  {string} targetType - Target object Type ( widget, region, layout, ... )
 * @param  {string} targetID - Target object ID
 * @param {object=} [oldValues] - Previous object change
 * @param {object=} [newValues] - New object change
 * @param {bool=} [upload] - Upload change in real time
*/
Manager.prototype.addChange = function(changeType, targetType, targetID, oldValues, newValues, upload = true) {
    
    const changeId = this.changeHistory.length;
    
    const newChange = new Change (
        changeId, 
        changeType,
        targetType,
        targetID,
        oldValues,
        newValues
    );

    // Add change to the array
    this.changeHistory[changeId] = newChange;

    // Upload change in "real time"
    if(lD.uploadChangesInRealTime && upload) {
        this.uploadChange(changeId);
    }

    // Render template container
    this.render();
};

/**
 * Revert change by ID or the last one in the history array
 * @param  {number=} changeId -Change ID to be reverted
*/
Manager.prototype.revertLastChange = function(changeId = null) {

    // Test for empty history array
    if(this.changeHistory.length <= 0 ) {
        return false;
    }
    
    // if ID is null, get last element id
    if(changeId == null) {
        changeId = this.changeHistory.length - 1;
    }
    
    const success = this.changeHistory[changeId].revert();

    // if sucessful, remove last element and refresh designer
    if(success){
        this.changeHistory.pop();
        lD.reloadData(lD.layout);
    }

    return success;
};

/**
 * Upload first change in the history array
*/
Manager.prototype.uploadChange = function(changeId) {

    const change = this.changeHistory[changeId];

    // Test for empty history array
    if(!change || change.uploaded) {
        toastr.info('Change already uploaded!');
        return false;
    }

    toastr.info('Uploading ' + change.target.type + ' change: ' + change.type);

    // upload change and pass the callback so that the manager can process the result
    change.upload().then(function(res) {
        
        if(res.success) {
            toastr.success('Change uploaded!');
            lD.reloadData(lD.layout);
        } else {
            console.log(res);
            
            toastr.error('Change upload failed!');
        }
        // Render template container
        this.render();
    }.bind(this));
};

/**
 * Save all the changes in the history array
*/
Manager.prototype.saveAllChanges = async function() {

    if(this.changeHistory.length <= 0) {
        return;
    }

    toastr.info('Uploading changes!');

    let saveAllResult = true;

    for(let index = 0;index < this.changeHistory.length;index++) {

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
        });
        
        // Render template container
        this.render();
    }

    if(saveAllResult) {
        toastr.success('All Changes Saved!');
    }

    lD.reloadData(lD.layout);
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