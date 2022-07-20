// NAVIGATOR Module

// Load templates
const BottomBarNavigatorTemplate = require('../templates/bottombar-navigator.hbs');
const BottomBarViewerTemplate = require('../templates/bottombar-viewer.hbs');

/**
 * Bottom topbar contructor
 * @param {object} container - the container to render the navigator to
 */
let Bottombar = function(parent, container) {
    this.parent = parent;
    this.DOMObject = container;
};

/**
 * Render bottombar
 */
Bottombar.prototype.render = function(element, data) {
    const self = this;
    const app = this.parent;
    const readOnlyModeOn = (app.readOnlyMode != undefined && app.readOnlyMode === true);

    // Get topbar trans
    let newBottomBarTrans = $.extend(toolbarTrans, topbarTrans);

    const checkHistory = app.checkHistory();
    newBottomBarTrans.undoActiveTitle = (checkHistory) ? checkHistory.undoActiveTitle : '';

    // Check if trash bin is active
    let trashBinActive = app.selectedObject.isDeletable && (app.readOnlyMode === undefined || app.readOnlyMode === false);

    // Get text for bin tooltip
    newBottomBarTrans.trashBinActiveTitle = (trashBinActive) ? newBottomBarTrans.deleteObject.replace('%object%', app.selectedObject.type) : '';

    if(app.navigatorMode) {
        this.DOMObject.html(BottomBarNavigatorTemplate(
            {
                trans: newBottomBarTrans,
                readOnlyModeOn: readOnlyModeOn,
                element: element,
                undoActive: checkHistory.undoActive,
                trashActive: trashBinActive
            }
        ));

        this.DOMObject.find('#add-btn').click(lD.addRegion);
    } else {
        if(element.type == 'widget') {
            const currentItem = element.index;
            const parentRegion = (element.drawerWidget) ? lD.getElementByTypeAndId('drawer') : lD.getElementByTypeAndId('region', element.regionId);
            const totalItems = (parentRegion != undefined && parentRegion.numWidgets != undefined) ? parentRegion.numWidgets : 1;

            // Render widget toolbar
            this.DOMObject.html(BottomBarViewerTemplate(
                {
                    currentItem: currentItem,
                    totalItems: totalItems,
                    extra: data.extra,
                    regionName: (parentRegion) ? parentRegion.name : '',
                    pagingEnable: (totalItems > 1),
                    trans: newBottomBarTrans,
                    readOnlyModeOn: readOnlyModeOn,
                    element: element,
                    undoActive: checkHistory.undoActive,
                    trashActive: trashBinActive
                }
            ));

            // Paging controls
            if(data.extra && totalItems > 1) {
                this.DOMObject.find('#left-btn').prop('disabled', (currentItem <= 1)).click(function() {
                    lD.selectObject($('#' + element.getNextWidget(true).id));
                }.bind(this));

                this.DOMObject.find('#right-btn').prop('disabled', (currentItem >= totalItems)).click(function() {
                    lD.selectObject($('#' + element.getNextWidget().id));
                }.bind(this));
            }
        } else if(element.type == 'layout') {
            // Render layout  toolbar
            this.DOMObject.html(BottomBarViewerTemplate(
                {
                    trans: newBottomBarTrans,
                    readOnlyModeOn: readOnlyModeOn,
                    renderLayout: true,
                    element: element,
                    undoActive: checkHistory.undoActive,
                    trashActive: trashBinActive
                }
            ));

            // Preview request path
            let requestPath = urlsForApi.layout.preview.url;
            requestPath = requestPath.replace(':id', lD.layout.layoutId);        

            // Handle play button ( play or pause )
            this.DOMObject.find('#play-btn').click(function() {
                if(lD.viewer.previewPlaying) {
                    this.DOMObject.find('#play-btn i').removeClass('fa-stop-circle').addClass('fa-play-circle').attr('title', bottombarTrans.playPreviewLayout);
                    app.renderContainer(app.viewer, app.layout);
                } else {
                    lD.viewer.playPreview(requestPath, lD.viewer.containerElementDimensions);
                    this.DOMObject.find('#play-btn i').removeClass('fa-play-circle').addClass('fa-stop-circle').attr('title', bottombarTrans.stopPreviewLayout);
                    lD.viewer.previewPlaying = true;
                }
            }.bind(this));

            // Handle navigator toggle button
            this.DOMObject.find('#navigator-edit-btn').click(function() {
                app.toggleNavigatorEditing(true);
            }.bind(this));
        } else if(element.type == 'region') {
            // Render region toolbar
            this.DOMObject.html(BottomBarViewerTemplate(
                {
                    trans: newBottomBarTrans,
                    readOnlyModeOn: readOnlyModeOn,
                    element: element,
                    undoActive: checkHistory.undoActive,
                    trashActive: trashBinActive
                }
            ));
        }
    }

    // If read only mode is enabled
    if (app.readOnlyMode != undefined && app.readOnlyMode === true) {
        // Create the read only alert message
        const $readOnlyMessage = $('<div id="read-only-message" class="alert alert-warning text-center navbar-nav" data-container=".editor-bottom-bar" data-toggle="tooltip" data-placement="bottom" data-title="' + layoutEditorTrans.readOnlyModeMessage + '" role="alert"><strong>' + layoutEditorTrans.readOnlyModeTitle + '</strong>:&nbsp;' + layoutEditorTrans.readOnlyModeMessage + '</div>');

        // Prepend the element to the bottom toolbar's content
        $readOnlyMessage.insertAfter(this.DOMObject.find('.pull-left')).on('click', lD.checkoutLayout);
    }

    // Button handlers
    this.DOMObject.find('#delete-btn').click(function() {
        if(element.isDeletable) {
            lD.deleteSelectedObject();
        }
    });

    this.DOMObject.find('#undo-btn').click(function() {
        app.undoLastAction();
    });

    this.DOMObject.find('.properties-btn').click(function() {
        const buttonData = $(this).data();
        element.editPropertyForm(buttonData['property'], buttonData['propertyType']);
    });
};

/**
 * Show message on play button
 */
Bottombar.prototype.showPlayMessage = function() {
    const self = this;
    const $target = self.DOMObject.find('#play-btn i');

    // Show popover
    $target.popover('show');

    // Destroy popover after some time
    setTimeout(function() {
        $target.popover('dispose');
    }, 4000);
};

module.exports = Bottombar;