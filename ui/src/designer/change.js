// CHANGE Module
/**
 * Module that saves the previous and next change of an object ( as an operation )
 */

var reverseChange = {
    'transform': 'transform',
    'delete': 'restoreElement',
    'create': 'deleteElement'
};

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
var Change = function(id, type, targetType, targetID, oldState, newState){
    this.id = id;
    this.type = type;
    this.target = {
        id: targetID,
        type: targetType
    };
    this.oldState = oldState;
    this.newState = newState;
    this.timeStamp = Math.round((new Date()).getTime() / 1000);
};

/**
 * Upload change to the API ( and remove it if sucessful )
 * @returns {boolean} Operation successful bool
*/
Change.prototype.upload = function() {

    var resultStatus = false;

    console.log('Upload change');
    console.log(this);

    resultStatus = true;

    return resultStatus;
};

/**
 * Revert change
 * @returns {boolean} Operation successful bool
*/
Change.prototype.revert = function() {

    var element = {};
    var reverse = reverseChange[this.type];
    var revertResult = false;

    // Restore is a special case, we need to use the layout to restore the element
    if(reverse === 'restoreElement' || reverse === 'deleteElement') {
        revertResult = lD.layout[reverse](this.target.id, this.target.type, this.oldState);
    } else {
        // Get element by type
        if(this.target.type === "layout") {
            console.log('    ->TODO: Layout');
        } else if(this.target.type === "region") {
            element = lD.layout.regions[this.target.id];
        } else if(this.target.type === "widget") {
            console.log('    ->TODO: Widget');
        }

        console.log(reverseChange[this.type]);
        
        // Revert change by operation type
        revertResult = element[reverseChange[this.type]](this.oldState, false);
    }

    return revertResult;
};

module.exports = Change;