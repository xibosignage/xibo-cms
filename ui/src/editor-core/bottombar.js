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
 * Render topbar
 */
Bottombar.prototype.render = function(element, data) {
    const self = this;
    const app = this.parent;
    const readOnlyModeOn = (app.readOnlyMode != undefined && app.readOnlyMode === true);
    
    if(app.navigatorMode) {
        this.DOMObject.html(BottomBarNavigatorTemplate(
            {
                trans: bottombarTrans,
                readOnlyModeOn: readOnlyModeOn,
                element: element
            }
        ));

        // Navbar buttons
        this.DOMObject.find('#close-btn').click(function() {
            if (self.DOMObject.parent().remove('fullscreen')) {
                self.DOMObject.parent().removeClass('fullscreen')
            }
            lD.toggleNavigatorEditing(false);
        });

        this.DOMObject.find('#add-btn').click(function() {
            lD.common.showLoadingScreen();

            if(lD.selectedObject.type == 'region') {
                lD.navigator.saveRegionPropertiesPanel();
                lD.selectObject();
            }

            lD.layout.addElement('region').then((res) => { // Success

                lD.common.hideLoadingScreen(); 

                // Behavior if successful 
                toastr.success(res.message);

                // Reload with the new added element
                lD.selectedObject.id = 'region_' + res.data.regionId;
                lD.selectedObject.type = 'region';
                lD.reloadData(lD.layout, true);
            }).catch((error) => { // Fail/error

                lD.common.hideLoadingScreen(); 
                // Show error returned or custom message to the user
                let errorMessage = '';

                if(typeof error == 'string') {
                    errorMessage = error;
                } else {
                    errorMessage = error.errorThrown;
                }

                toastr.error(errorMessagesTrans.createRegionFailed.replace('%error%', errorMessage));
            });
        });
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
                    pagingEnable: (totalItems > 1),
                    trans: bottombarTrans,
                    readOnlyModeOn: readOnlyModeOn,
                    element: element
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
                    trans: bottombarTrans,
                    readOnlyModeOn: readOnlyModeOn,
                    renderLayout: true,
                    element: element
                }
            ));

            // Preview request path
            let requestPath = urlsForApi.layout.preview.url;
            requestPath = requestPath.replace(':id', lD.layout.layoutId);        

            // Handle play button ( play or pause )
            this.DOMObject.find('#play-btn').click(function() {
                if(lD.viewer.previewPlaying) {
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
                    trans: bottombarTrans,
                    readOnlyModeOn: readOnlyModeOn,
                    element: element
                }
            ));
        }

        // Handle back button
        this.DOMObject.find('#back-btn').click(function() {
            lD.selectObject();
        }.bind(this));
    }

    // Button handlers
    this.DOMObject.find('#delete-btn').click(function() {
        if(element.isDeletable) {
            lD.deleteSelectedObject();
        } else {
            console.log('prevent delete');
        }
    });

    this.DOMObject.find('.properties-btn').click(function() {
        const buttonData = $(this).data();
        element.editPropertyForm(buttonData['property'], buttonData['propertyType']);
    });
};

module.exports = Bottombar;