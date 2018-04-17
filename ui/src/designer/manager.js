// MANAGER Module
const Change = require('./change.js');

/**
 * Layout Editor Manager
 */
var Manager = function() {
    this.changeHistory = []; // Array of changes
};

/**
 * Save a editor change to the history array
 * @param  {string} type -Type of change ( resize, move )
 * @param  {string} targetType - Target object Type ( widget, region, layout, ... )
 * @param  {string} targetID - Target object ID
 * @param {object=} [oldValues] - Previous object change
 * @param {object=} [newValues] - New object change
*/
Manager.prototype.addChange = function(changeType, targetType, targetID, oldValues, newValues) {

    var changeId = this.changeHistory.length;
    
    var newChange = new Change (
        changeId, 
        changeType,
        targetType,
        targetID,
        oldValues,
        newValues
    );

    // Add change to the array
    this.changeHistory[changeId] = newChange;
};

/**
 * Revert change
*/
Manager.prototype.revertLastChange = function(changeId = null) {

    // Test for empty history array
    if(this.changeHistory.length <= 0 ) {
        return;
    }
    
    // if ID is null, get last element id
    if(changeId == null) {
        changeId = this.changeHistory.length - 1;
    }
    
    var success = this.changeHistory[changeId].revert();

    // if sucessful, remove last element and refresh designer
    if(success){
        this.changeHistory.pop();
        lD.refreshDesigner();
    }
};

module.exports = Manager;