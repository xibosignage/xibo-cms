// CHANGE Module
/**
 * A change stores a operation state
*/

/**
 * Change object
 * @param  {number} id -Change id
 * @param  {string} type -Type of change ( tranform, properties,... )
 * @param  {string} targetType - Target object Type ( widget, region, layout, ... )
 * @param  {string} targetID - Target object ID
 * @param  {object} target - Target object ( widget, region, layout, ...
 * @param {object=} [oldState] - Previous change properties
 * @param {object=} [newState] - Change properties, to be saved
*/
let Change = function(id, type, targetType, targetID, oldState, newState){
    this.id = id;
    this.type = type;
    this.target = {
        id: targetID,
        type: targetType
    };
    this.oldState = oldState;
    this.newState = newState;
    this.timeStamp = Math.round((new Date()).getTime() / 1000);

    // Flag to check if the change was successfully uploaded
    this.uploaded = false;

    // Flag to check if the change was already marked for upload
    this.uploading = false;
};

module.exports = Change;