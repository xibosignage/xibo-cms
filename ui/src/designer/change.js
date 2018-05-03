// CHANGE Module
/**
 * Module that saves the previous and next change of an object ( as an operation )
 */

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
    }
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
    
    this.uploadedState = function() {
        return (this.uploaded) ? 'uploaded' : '';
    }
};

/**
 * Upload change to the API ( and remove it if successful )
*/
Change.prototype.upload = function() {

    const linkToAPI = urlsForApi[this.target.type][this.type];
    let requestPath = linkToAPI.url;
  
    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', this.target.id);

    var promise = $.ajax({
        url: requestPath,
        type: linkToAPI.type,
        data: this.newState,
        success: function(data) {
            if(data.success) {
                this.uploaded = true;
            }
        }.bind(this),
        error: function(jXHR, textStatus, errorThrown) {
            toastr.error('Change upload failed!', 'Error');
        }
    });

    return promise;
};

/**
 * Revert change
 * @returns {boolean} Operation successful bool
*/
Change.prototype.revert = async function() {
    
    const inverseOperation = inverseChangeMap[this.type].inverse;
    const useElement = inverseChangeMap[this.type].useElement;

    if(!this.uploaded) { // Revert on the client side

        if(useElement) {
            // Get data to apply
            let data = this.oldState;

            // Get element by type
            let element = {};

            if(this.target.type === 'layout') {
                console.log('    ->TODO: Layout');
            } else if(this.target.type === 'region') {
                element = lD.layout.regions['region_' + this.target.id];
            } else if(this.target.type === 'widget') {
                console.log('    ->TODO: Widget');
            }

            // If the operation is a transform, parse data
            if(inverseOperation === 'transform') {
                data = JSON.parse(data.regions)[0];
            }

            // Apply inverse operation to the element
            element[inverseOperation](data, false);

            return true;
        } else {
            // TODO: Check if this is needed, since the only client side changes from now are on specific elements
            return lD.layout[inverseOperation](this.target.id, this.target.type, this.oldState);
        }

    } else { // Revert using the API
        
        const linkToAPI = urlsForApi[this.target.type][inverseOperation];
        let requestPath = linkToAPI.url;

        // replace id if necessary/exists
        requestPath = requestPath.replace(':id', this.target.id);

        var promise = $.ajax({
            url: requestPath,
            type: linkToAPI.type,
            data: this.oldState,
            success: function(data) {
                if(data.success) {
                    return data.success;
                }
                return false;
            },
            error: function(jXHR, textStatus, errorThrown) {
                toastr.error('Change upload failed!', 'Error');
            }
        });

        return promise;
    }
};

module.exports = Change;