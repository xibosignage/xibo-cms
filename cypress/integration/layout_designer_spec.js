describe('Layout Designer (Empty)', function() {

    /**
     * Drag one element to another one
     * @param {string} draggableSelector 
     * @param {string} dropableSelector 
     */
    function dragToElement(draggableSelector, dropableSelector) {
        
        return cy.get(dropableSelector).then(($el) => {
            let position = {
                x: $el.offset().left + $el.width() / 2 + window.scrollX,
                y: $el.offset().top + $el.height() / 2 + window.scrollY
            };

            cy.get(draggableSelector).invoke('show');

            cy.get(draggableSelector)
                .trigger('mousedown', {
                    which: 1
                })
                .trigger('mousemove', {
                    which: 1,
                    pageX: position.x,
                    pageY: position.y
                })
                .trigger('mouseup');
        });
        
    }

    /**
     * Go to layout editor page and wait for toolbar user prefs to load
     * @param {number} layoutId 
     */
    function goToLayoutAndLoadPrefs(layoutId) {

        cy.server();
        cy.route('/user/pref?preference=toolbar').as('userPrefsLoad');
        
        cy.clearToolbarPrefs();

        cy.visit('/layout/designer/' + layoutId);

        // Wait for user prefs to load
        cy.wait('@userPrefsLoad');
    }

    /**
     * Add media items to library
     */
    function populateLibraryWithMedia() {
        // Add audio media to library
        cy.addMediaToLibrary('../assets/audioSample.mp3');

        // Add image media to library
        cy.addMediaToLibrary('../assets/imageSample.png');
    }

    beforeEach(function() {
        cy.login();
    });

    context('Unexisting Layout', function() {

        it('show layout not found if layout does not exist', function() {

            // Use a huge id to test a layout not found 
            cy.visit('/layout/designer/' + Cypress._.random(0, 1e9));

            // See if the message Layout not found exists
            cy.contains('Layout not found');
        });
    });

    context('Empty layout (published)', function() {

        beforeEach(function() {
            // Create random name
            const uuid = Cypress._.random(0, 1e8);

            // Create a new layout and go to the layout's designer page
            cy.createLayout(uuid).as('testLayoutId').then((res) => {
                goToLayoutAndLoadPrefs(res);
            });

        });

        afterEach(function() {
            // Remove the created layout
            cy.deleteLayout(this.testLayoutId);
        });

        it('should not show the welcome tutorial', function() {
            cy.get('.popover.tour').should('not.be.visible');
        });

        it('shows the read only message', function() {

            // Check if the checkout modal appears
            cy.get('#read-only-message').should('exist');
        });

        it('goes into draft mode when checked out', function() {

            // Click message to open the modal
            cy.get('#read-only-message').click();

            // Get the done button from the checkout modal
            cy.get('[data-test="checkoutModal"] button[data-bb-handler="checkout"]').click();

            // Check if the checkout message disappeared
            cy.get('#read-only-message').should('not.be.visible');
        });
    });

    context('Empty layout (draft)', function() {

        beforeEach(function() {
            // Create random name
            const uuid = Cypress._.random(0, 1e8);

            // Create a new layout and go to the layout's designer page, then load toolbar prefs
            cy.createLayout(uuid).as('testLayoutId').then((res) => {
    
                cy.checkoutLayout(res);

                goToLayoutAndLoadPrefs(res);
            });
        });

        afterEach(function() {
            // Remove the created layout
            cy.deleteLayout(this.testLayoutId);
        });
        
        // Main
        it('should load all the layout designer elements', function() {

            // Check if the basic elements of the designer loaded
             cy.get('#layout-editor').should('be.visible');
             cy.get('#layout-navigator').should('be.visible');
             cy.get('#layout-timeline').should('be.visible');
             cy.get('#layout-viewer-container').should('be.visible');
             cy.get('#properties-panel').should('be.visible');
        });

        // Toolbar
        it('shows a toast message ("Empty Region") when trying to Publish a layout with an empty region', () => {

            cy.server();
            cy.route('PUT', '/layout/publish/*').as('layoutPublish');

            cy.get('#layout-editor-toolbar a#publishLayout').click();

            cy.get('[data-test="publishModal"] button[data-bb-handler="done"]').click();

            cy.get('.toast-error').contains('Empty Region');

            cy.wait('@layoutPublish');

            cy.get('[data-test="publishModal"] button#publishLayout').should('not.be.visible');
        });

        it('creates a new tab in the toolbar and searches for items', () => {

            populateLibraryWithMedia();

            cy.get('#layout-editor-toolbar #btn-menu-new-tab').click();

            // Select and search image items
            cy.get('.toolbar-pane.active .input-type').select('audio');

            // Check if there are audio items in the search content
            cy.get('#layout-editor-toolbar .media-table tbody tr:first').should('be.visible').contains('audio');
        });

        it('creates multiple tabs and then closes them all', () => {

            cy.get('#layout-editor-toolbar [data-test="toolbarTabs"]').then(($el) => {

                const numTabs = $el.length;
                
                // Create 3 tabs
                cy.get('#layout-editor-toolbar #btn-menu-new-tab').click();
                cy.get('#layout-editor-toolbar #btn-menu-new-tab').click();
                cy.get('#layout-editor-toolbar #btn-menu-new-tab').click();

                // Check if there are 4 tabs in the toolbar ( widgets default one and the 3 created )
                cy.get('#layout-editor-toolbar [data-test="toolbarTabs"]').should('be.visible').should('have.length', numTabs + 3);

                // Close all tabs using the toolbar button and chek if there is just one tab
                cy.get('#layout-editor-toolbar #deleteAllTabs').click().then(() => {
                    cy.get('#layout-editor-toolbar [data-test="toolbarTabs"]').should('be.visible').should('have.length', numTabs);
                });
            });

        });

        it('uses the layout select field to load a new layout', () => {

            cy.url().then((startURL) => {

                cy.get('#layout-editor-toolbar #layoutJumpListContainer').click();

                // Select the last layout option available ( avoid result and message "options")
                cy.get('.select2-container .select2-results .select2-results__option:not(.select2-results__option--load-more):not(.loading-results):not(.select2-results__message):last').click();

                // Assure that we are in a new layout (different) page
                cy.url().should('not.be.eq', startURL);
            });
        });

        // Timeline
        it('shows empty region message in a region with no widgets', () => {
            cy.get('#regions-container > #regions > .designer-region:first-child').contains('Empty Region');
        });

        // Navigator
        it('should create a new region from within the navigator edit', () => {

            // Open navigator edit
            cy.get('#layout-navigator #edit-btn').click();

            // Click on add region button
            cy.get('#layout-navigator-edit #add-btn').click();

            // Close the navigator edit
            cy.get('#layout-navigator-edit #close-btn').click({force: true});

            // Check if there are 2 regions in the timeline ( there was 1 by default )
            cy.get('#layout-timeline [data-type="region"]').should('be.visible').should('have.length', 2);
        });

        it('creates a region using the toolbar', () => {
            // Create and alias for reload layout
            cy.server();
            cy.route('/layout?layoutId=*').as('reloadLayout');

            // Open toolbar Tools tab
            cy.get('#layout-editor-toolbar .btn-menu-tab').contains('Tools').should('be.visible').click();

            // Drag region to layout to add it
            dragToElement(
                '#layout-editor-toolbar .toolbar-pane-content [data-sub-type="region"] .drag-area',
                '#layout-navigator [data-type="layout"]'
            ).then(() => {

                cy.wait('@reloadLayout').then(() => {

                    // Check if there are 2 regions in the timeline ( there was 1 by default )
                    cy.get('#layout-timeline [data-type="region"]').should('be.visible').should('have.length', 2);
                });
            });
        });

        it('should delete an existing region from within the navigator edit', () => {

            // Open navigator edit
            cy.get('#layout-navigator #edit-btn').click();

            // Select a region
            cy.get('#layout-navigator-edit [data-type="region"]:first-child').click();

            // Click on delete region button
            cy.get('#layout-navigator-edit #delete-btn').click();

            // Confirm modal
            cy.get('[data-test="deleteRegionModal"]').should('be.visible').find('button[data-bb-handler="confirm"]').click();

            // Close the navigator edit
            cy.get('#layout-navigator-edit #close-btn').click({force: true});

            // Check if there are no regions in the timeline ( there was 1 by default )
            cy.get('#layout-timeline [data-type="region"]').should('not.be.visible');
        });

        it('should revert a created region, by deleting it', () => {

            // Create and alias for reload Layout
            cy.server();
            cy.route('/layout?layoutId=*').as('reloadLayout');

            // Open navigator edit
            cy.get('#layout-navigator #edit-btn').click();

            // Click on add region button
            cy.get('#layout-navigator-edit #add-btn').click();

            // Close the navigator edit
            cy.get('#layout-navigator-edit #close-btn').click({force: true});
            

            // Check if there are 2 regions in the timeline ( there was 1 by default )
            cy.get('#layout-timeline [data-type="region"]').should('be.visible').should('have.length', 2);

            // Wait for the layout to reload
            cy.wait('@reloadLayout');

            // Click the revert button
            cy.get('#layout-editor-toolbar #undoContainer').click({force: true});

            // Wait for the layout to reload
            cy.wait('@reloadLayout').then(() => {
                // Check if there is just 1 region
                cy.get('#layout-timeline [data-type="region"]').should('be.visible').should('have.length', 1);
            });
        });

        ['layout-timeline', 'layout-navigator'].forEach((target) => {

            it('creates a new widget by dragging a widget from the toolbar to ' + target + ' region', () => {

                // Create and alias for reload Layout
                cy.server();
                cy.route('POST', '**/playlist/widget/embedded/*').as('createWidget');

                // Open toolbar Widgets tab
                cy.get('#layout-editor-toolbar .btn-menu-tab').contains('Tools').should('be.visible').click();
                cy.get('#layout-editor-toolbar .btn-menu-tab').contains('Widgets').should('be.visible').click();

                cy.get('#layout-editor-toolbar .toolbar-pane-content [data-sub-type="embedded"]').should('be.visible').then(() => {
                    dragToElement(
                        '#layout-editor-toolbar .toolbar-pane-content [data-sub-type="embedded"] .drag-area',
                        '#' + target + ' [data-type="region"]:first-child'
                    ).then(() => {

                        // Wait for the widget to be added
                        cy.wait('@createWidget');

                        // Check if there is just one widget in the timeline
                        cy.get('#layout-timeline [data-type="region"] [data-type="widget"]').then(($widgets) => {
                            expect($widgets.length).to.eq(1);
                        });
                    });
                });

            });

            it('creates a new widget by selecting a searched media from the toolbar to ' + target + ' region', () => {

                // Create and alias for reload Layout
                cy.server();
                cy.route('/layout?layoutId=*').as('reloadLayout');
                cy.route('/library?assignable=*').as('mediaLoad');

                // Open a new tab
                cy.get('#layout-editor-toolbar #btn-menu-new-tab').click();

                cy.wait('@mediaLoad');

                // Select and search image items
                cy.get('.toolbar-pane.active .input-type').select('image');

                cy.wait('@mediaLoad');

                // Get a table row, select it and add to the region
                cy.get('#layout-editor-toolbar .media-table .assignItem:first').click().then(() => {
                    cy.get('#' + target + ' [data-type="region"]:first-child').click({force: true}).then(() => {

                        // Wait for the layout to reload
                        cy.wait('@reloadLayout');

                        // Check if there is just one widget in the timeline
                        cy.get('#layout-timeline [data-type="region"] [data-type="widget"]').then(($widgets) => {
                            expect($widgets.length).to.eq(1);
                        });
                    });
                });
            });

            it('shows the file upload form by dragging a uploadable media from the toolbar to ' + target + ' region', () => {

                populateLibraryWithMedia();

                // Open toolbar Widgets tab
                cy.get('#layout-editor-toolbar .btn-menu-tab').contains('Widgets').should('be.visible').click();

                cy.get('#layout-editor-toolbar .toolbar-pane-content [data-sub-type="audio"]').should('be.visible').then(() => {
                    dragToElement(
                        '#layout-editor-toolbar .toolbar-pane-content [data-sub-type="audio"] .drag-area',
                        '#' + target + ' [data-type="region"]:first-child'
                    ).then(() => {
                        cy.get('[data-test="uploadFormModal"]').contains('Upload media');
                    });
                });
            });

            it('shows the file upload form by using the Add button on a card with uploadable media from the toolbar to ' + target + ' region', () => {

                populateLibraryWithMedia();

                // Open toolbar Widgets tab
                cy.get('#layout-editor-toolbar .btn-menu-tab').contains('Tools').should('be.visible').click();
                cy.get('#layout-editor-toolbar .btn-menu-tab').contains('Widgets').should('be.visible').click();

                // Activate the Add button
                cy.get('#layout-editor-toolbar #content-1 .toolbar-pane-content [data-sub-type="audio"] .add-area').invoke('show').click();

                // Click on the region to add
                cy.get('#' + target + ' [data-type="region"]:first-child').click();

                // Check if the form opened
                cy.get('[data-test="uploadFormModal"]').contains('Upload media');
            
            });
        });
    });
});