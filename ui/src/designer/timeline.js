// TIMELINE Module

// Load templates
const timelineTemplate = require('../templates/timeline.hbs');

/**
 * Timeline contructor
 * @param {object} container - the container to render the timeline to
 * @param {object =} [options] - Timeline options
 */
let Timeline = function(container) {
    this.DOMObject = container;

    this.scrollPercent = {
        left: 0,
        right: 0
    };

    // Boolean to represent if a sort is happening
    this.beingSorted = false;

    // Properties to be used for the template
    this.properties = {
        zoom: -1, // Zoom by default is -1 so that can be calculated based on the widgets of the regions
        startingZoom: -1,
        minTime: 0,
        maxTime: lD.layout.duration,
        deltaTime: lD.layout.duration,
        zoomInDisable: '',
        zoomOutDisable: '',
        scrollPosition: 0, // scroll position
        scrollWidth: 0, // To fix the double scroll reseting to 0 bug
        widgetMinimumVisibleRatio: 10, // Minimum % value so that the region details are shown
        widgetMinimumDurationOnStart: 15 // % of the shortest widget to be used to calculate the default zoom 
    };
};

/**
 * Change timeline zoom
 * @param {number} zoom - the change to be applied to zoom ( -1:zoomOut, 0: default, 1: zoomIn )
 */
Timeline.prototype.changeZoom = function(zoom) {
    
    // Reset to starting zoom
    if(zoom === 0) {
        this.properties.scrollPosition = 0;
        this.properties.zoom = this.properties.startingZoom;
        return;
    }

    let zoomVariation = 10;
    
    if(this.properties.zoom >= 5000) {
        zoomVariation = 1000;
    } else if(this.properties.zoom >= 1000) {
        zoomVariation = 200;
    } else if(this.properties.zoom >= 500) {
        zoomVariation = 100;
    } else if(this.properties.zoom >= 200) {
        zoomVariation = 50;
    }

    // Calculate new zoom value
    let newZoom = Math.round(this.properties.zoom + (zoomVariation * zoom));
    
    // Reset zoom enable flags
    this.properties.zoomOutDisable = this.properties.zoomInDisable = '';

    // If zoom out is 100% or less disable button limit it to 100%
    if( newZoom <= 100 ){
        newZoom = 100;
        this.properties.zoomOutDisable = 'disabled';
        
        // Set scroll position to 0
        this.properties.scrollPosition = 0;
    }

    // Set the zoom and calculate the max time for the ruler
    this.properties.zoom = newZoom;
};

/**
 * Calculate time values/labels based on zoom and position of the scroll view
 */
Timeline.prototype.calculateTimeValues = function() {

    this.properties.deltaTime = Math.round(10 * (lD.layout.duration / (this.properties.zoom / 100))) / 10;
    this.properties.minTime = Math.round(10 * (this.properties.scrollPosition * lD.layout.duration)) / 10;
    this.properties.maxTime = this.properties.minTime + this.properties.deltaTime;
};

/**
 * Update timeline labels after rendering
 */
Timeline.prototype.updateLabels = function() {

    this.DOMObject.find('#minTime').html(this.properties.minTime + 's');
    this.DOMObject.find('#maxTime').html(this.properties.maxTime + 's');
    this.DOMObject.find('#zoom').html(this.properties.deltaTime + 's');
};

/**
 * If zoom is not defined, calculate default value based on widget lenght
 * @param {object} regions - Layout regions
 */
Timeline.prototype.calculateStartingZoom = function(regions) {
    
    // Find the smallest widget ( by duration )
    let shorterWidgetDuration = -1;
    for(let region in regions) {
        for(let widget in regions[region].widgets) {
            if(regions[region].widgets[widget].getTotalDuration() < shorterWidgetDuration || shorterWidgetDuration === -1) {
                shorterWidgetDuration = regions[region].widgets[widget].getTotalDuration();
            }
        }
    }

    // Calculate zoom and limit its minimum to 100%
    this.properties.zoom = Math.floor(this.properties.widgetMinimumDurationOnStart / (shorterWidgetDuration / lD.layout.duration));
    
    if(this.properties.zoom <= 100 ) {
        this.properties.zoom = this.properties.startingZoom = 100;
        this.properties.zoomOutDisable = 'disabled';
    } else {
        this.properties.zoomOutDisable = '';
    }

    this.properties.startingZoom = this.properties.zoom;
};

/**
 * Check regions and choose display type ( detailed/zoom-to-see-details) 
 * @param {object} regions - Layout regions
 */
Timeline.prototype.checkRegionsVisibility = function(regions) {

    const visibleDuration = lD.layout.duration * (100 / this.properties.zoom); //this.properties.maxTime - this.properties.minTime;
    
    for(let region in regions) {
        // Reset the region visibility flag
        regions[region].hideDetails = false;

        for(let widget in regions[region].widgets) {

            // Calculate the ratio of the widget compared to the region length
            const widthRatio = regions[region].widgets[widget].getTotalDuration() / visibleDuration;

            // Mark region as hidden if the widget is too small to be displayed
            if(widthRatio < (this.properties.widgetMinimumVisibleRatio/100)) {
                regions[region].hideDetails = true;
                break;
            }
        }
    }
};

/**
 * Create widget replicas
 * @param {object} regions - Layout regions
 */
Timeline.prototype.createGhostWidgetsDynamically = function(regions) {

    for(let region in regions) {
        
        let currentRegion = regions[region];

        // if the regions isn't marked for looping, or if does not contain any widget, skip to the next one
        if(!currentRegion.loop || $.isEmptyObject(currentRegion.widgets)) {
            continue;
        }

        let widgetsTotalDuration = 0;
        let ghostWidgetsObject = [];

        // Clear region previous ghosts
        currentRegion.ghostWidgetsObject = [];

        // calculate widgets total duration
        for(let widget in currentRegion.widgets) {
            widgetsTotalDuration += currentRegion.widgets[widget].getTotalDuration();
        }

        // starting and ending time to check/draw ghosts in
        //      get the ghosts drawing starting time, depending on the minimum visualization time and if the widgets are shown on screen after it or not
        const ghostsStartTime = (widgetsTotalDuration > this.properties.minTime) ? widgetsTotalDuration : this.properties.minTime;
        const ghostsEndTime = this.properties.maxTime;
        
        // distance from the beggining of ghosts and the end of the widgets
        let paddingLeft = 0;

        // if the widgets are shown until the end visualization ( or after ), don't draw any ghosts
        if(widgetsTotalDuration > ghostsEndTime){
            continue;
        }

        // start the auxiliar time just after the widgets
        let auxTime = widgetsTotalDuration;

        // go through auxiliar time, advancing with each widget's time
        while( auxTime < ghostsEndTime) {

            // repeat widget playlist to advance time and create the ghost widgets
            for(let widget in currentRegion.widgets) {

                // if the next widget shows on the time span, add it to the array
                if(auxTime + currentRegion.widgets[widget].getTotalDuration() > ghostsStartTime) {
                    // clone widget to create a ghost
                    let ghost = currentRegion.widgets[widget].createClone();

                    // if the ghost goes after the layout ending, crop it
                    if(auxTime + ghost.duration > lD.layout.duration) {
                        const cropDuration = ghost.duration - ((auxTime + ghost.duration) - lD.layout.duration);
                        ghost.duration = cropDuration;
                    }

                    // Add ghost to the array
                    ghostWidgetsObject.push(ghost);
                } else {                
                    paddingLeft += currentRegion.widgets[widget].getTotalDuration();
                }

                // Advance auxiliar time with the widget duration
                auxTime += currentRegion.widgets[widget].getTotalDuration();

                // if the time has passed the end ghost time, break out from the widget loop
                if(auxTime >= ghostsEndTime){
                    break;
                }
            }
        }

        // flag to see if there's padding
        currentRegion.ghostWidgetsHavePadding = (paddingLeft > 0);
    
        // Calulate padding in percentage ( related to the duration )
        currentRegion.ghostWidgetsPadding = (paddingLeft / lD.layout.duration) * 100;

        // add ghost object array to the region
        currentRegion.ghostWidgetsObject = ghostWidgetsObject;
    }
};

/**
 * Reset zoom to be recalculated on next render
 */
Timeline.prototype.resetZoom = function() {
    this.properties.zoom = -1;
};

/**
 * Move a widget in a region
 * @param {string} regionId - The target region
 * @param {string} widgetId - The widget to be moved
 * @param {string} moveType - "topLeft"; "left"; "right"; "topRight";
 */
Timeline.prototype.moveWidgetInRegion = function(regionId, widgetId, moveType) {

    let getElement = this.DOMObject.find('#' + regionId + ' #' + widgetId);

    switch(moveType) {
        case 'oneRight':
            getElement.insertAfter(getElement.next('.designer-widget:not(.designer-widget-ghost)'));
            break;

        case 'oneLeft':
            getElement.insertBefore(getElement.prev('.designer-widget:not(.designer-widget-ghost)'));
            break;

        case 'topRight':
            getElement.insertAfter(getElement.nextAll('.designer-widget:not(.designer-widget-ghost)').last());
            break;

        case 'topLeft':
            getElement.prependTo(getElement.parent());
            break;

        default:
            console.warn('Change type not known');        
            return;
    }

    // Save new order
    lD.common.showLoadingScreen();

    // Get playlist
    const region = this.DOMObject.find('#' + regionId);
    const playlist = lD.getElementByTypeAndId($(region).data('type'), $(region).attr('id')).playlists;

    // Add sort class
    $(region).addClass('to-sort');

    lD.layout.savePlaylistOrder(playlist, $(region).find('.designer-widget:not(.designer-widget-ghost)')).then((res) => { // Success

        lD.common.hideLoadingScreen();

        // Behavior if successful            
        toastr.success(res.message);
        lD.reloadData(lD.layout);
    }).catch((error) => { // Fail/error

        // Remove sort class
        $(region).removeClass('to-sort');

        lD.common.hideLoadingScreen();

        // Show error returned or custom message to the user
        // Show error returned or custom message to the user
        let errorMessage = '';

        if(typeof error == 'string') {
            errorMessage = error;
        } else {
            errorMessage = error.errorThrown;
        }

        toastr.error(errorMessagesTrans.saveOrderFailed.replace('%error%', errorMessage));
    });
};

/**
 * Render Timeline and the layout
 * @param {Object} layout - the layout object to be rendered
 */
Timeline.prototype.render = function(layout) {

    // If starting zoom is not defined, calculate its value based on minimum widget duration
    if(this.properties.zoom === -1) {
        this.calculateStartingZoom(layout.regions);
    }

    // Reset being sorted flag
    this.beingSorted = false;

    // Calulate time values based on scroll position
    this.calculateTimeValues();
    
    // Check regions to see if they can be rendered with details or not
    this.checkRegionsVisibility(layout.regions);

    // Check widget repetition and create ghosts
    this.createGhostWidgetsDynamically(layout.regions);

    // Render timeline template using layout object
    const html = timelineTemplate({
        layout: layout, 
        properties: this.properties
    });

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Load region container
    const regionsContainer = this.DOMObject.find('#regions-container');

    // Save regions size to guarantee that when the scroll event is called, the region don't reset to 0 ( bugfix )
    this.properties.scrollWidth = regionsContainer.find("#regions").width();

    // Maintain the previous scroll position
    regionsContainer.scrollLeft(this.properties.scrollPosition * regionsContainer.find("#regions").width());

    // Update timeline labels
    this.updateLabels();

    // Enable hover and select for each layout/region
    this.DOMObject.find('.selectable:not(.ui-draggable-dragging)').click(function(e) {
        e.stopPropagation();
        lD.selectObject($(this));
    });

    // Button actions
    const self = this;
    this.DOMObject.find('#zoomIn').click(function() {
        self.changeZoom(1);
        self.render(layout);
    });

    this.DOMObject.find('#zoomOut').click(function() {
        self.changeZoom(-1);
        self.render(layout);
    });

    this.DOMObject.find('#zoom').click(function() {
        self.changeZoom(0);
        self.render(layout);
    });

    this.DOMObject.find('.designer-region').droppable({
        accept: function(el) {
            return ($(this).hasClass('editable') && $(el).attr('drop-to') === 'region') ||
                ($(this).hasClass('permissionsModifiable') && $(el).attr('drop-to') === 'all' && $(el).data('subType') === 'permissions');
        },
        drop: function(event, ui) {
            lD.dropItemAdd(event.target, ui.draggable[0]);
        }
    });

    this.DOMObject.find('.designer-widget').droppable({
        greedy: true,
        accept: function(el) {
            return ($(this).hasClass('editable') && $(el).attr('drop-to') === 'widget') ||
                ($(this).hasClass('permissionsModifiable') && $(el).attr('drop-to') === 'all' && $(el).data('subType') === 'permissions');
        },
        drop: function(event, ui) {
            lD.dropItemAdd(event.target, ui.draggable[0]);
        }
    });

    this.DOMObject.find('.designer-widget.editable .editProperty').click(function(e) {
        e.stopPropagation();

        const parent = $(this).parents('.designer-widget.editable:first');
        const widget = lD.getElementByTypeAndId(parent.data('type'), parent.attr('id'), parent.data('widgetRegion'));

        widget.editPropertyForm($(this).data('property'), $(this).data('propertyType'));
    });
    
    
    if(lD.readOnlyMode === false) {

        this.DOMObject.find('#regions .designer-region.editable').sortable({
            items: '.designer-widget:not(.designer-widget-ghost)',
            placeholder: 'designer-widget-sortable-highlight',
            opacity: '.6',
            axis: 'x', // Restrict movement to X axis
            helper: 'clone',
            start: function(event, ui) {

                // Set sorted flag as true
                self.beingSorted = true;

                // Hide the trash container
                lD.toolbar.DOMObject.find('#trashContainer').removeClass('active');

                // Get element width and timeline zoom/scale
                let zoom = self.DOMObject.find('#regions').data('zoom') / 100;
                let elementWidth = $(ui.item).width();

                // set helper new width
                $(ui.helper).width(elementWidth * zoom);
            },
            stop: function() {

                // Reset being sorted flag
                self.beingSorted = false;

                lD.common.showLoadingScreen();

                // Add sort class
                $(this).addClass('to-sort');

                // Get playlist
                const playlist = lD.getElementByTypeAndId($(this).data('type'), $(this).attr('id')).playlists;

                lD.layout.savePlaylistOrder(playlist, $(this).find('.designer-widget:not(.designer-widget-ghost)')).then((res) => { // Success

                    lD.common.hideLoadingScreen();

                    // Behavior if successful            
                    toastr.success(res.message);
                    lD.reloadData(lD.layout);
                }).catch((error) => { // Fail/error

                    // Remove sort class
                    $(this).removeClass('to-sort');
                    
                    lD.common.hideLoadingScreen();

                    // Show error returned or custom message to the user
                    // Show error returned or custom message to the user
                    let errorMessage = '';

                    if(typeof error == 'string') {
                        errorMessage = error;
                    } else {
                        errorMessage = error.errorThrown;
                    }

                    toastr.error(errorMessagesTrans.saveOrderFailed.replace('%error%', errorMessage));
                });
            }
        });

        this.DOMObject.find('.designer-region, .designer-widget:not(.designer-widget-ghost)').contextmenu(function(ev) {
            
            if($(ev.currentTarget).is('.editable, .deletable, .permissionsModifiable')) {
                // Open context menu
                lD.openContextMenu(ev.currentTarget, {
                    x: ev.pageX,
                    y: ev.pageY
                });
            }

            // Prevent browser menu to open
            return false;
        });
    }
    
    // When scroll is called ( by scrollbar or .scrollLeft() method calling ), use debounce and process the behaviour
    regionsContainer.scroll(_.debounce(function() {
        
        // If regions are still not rendered, leave method
        if(self.properties.scrollWidth != $(this).find("#regions").width() || self.beingSorted == true) {
            return;
        }

        // Get new scroll position ( percentage )
        const newScrollPosition = $(this).scrollLeft() / $(this).find("#regions").width();

        // Render only if the scroll position has been updated ( avoiding looping when calloing .scrollLeft())
        if(self.properties.scrollPosition != newScrollPosition) {
            // Update cached scroll position
            self.properties.scrollPosition = newScrollPosition;

            // Render layout
            self.render(layout);
        }
    }, 500));
};

module.exports = Timeline;
