describe('Layout Designer (Empty)', function() {

    beforeEach(function() {
        cy.login();
    });

    context('Unexisting Layout', function() {

        it('show layout not found if layout does not exist', function() {

            // Use a huge id to test a layout not found 
            cy.visit('/layout/designer/111111111111');

            // See if the message Layout not found exists
            cy.contains('Layout not found');
        });
    });

    context('Empty layout (published)', function() {

        var layoutTempName = '';

        beforeEach(function() {

            // Import a layout and go to the Layout's designer page - we need a Layout in a Published state
            cy.importLayout('../assets/export_test_layout.zip').as('testLayoutId').then((res) => {

                cy.goToLayoutAndLoadPrefs(res);
            });

        });

        it('goes into draft mode when checked out', function() {

            // Get the done button from the checkout modal
            cy.get('[data-test="welcomeModal"] button[data-bb-handler="checkout"]').click();

            // Check if campaign is deleted in toast message
            cy.contains('Checked out ' + layoutTempName);
        });

        it('should prevent a layout edit action, and show a toast message', function() {

            // Should contain widget options form
            cy.get('#properties-panel-container').contains('Edit Layout');

            // The save button should not be visible
            cy.get('#properties-panel-container [data-action="save"]').should('not.exist');
        });
    });

    context('Empty layout (draft)', function() {

        beforeEach(function() {
            // Create random name
            let uuid = Cypress._.random(0, 1e8);

            // Create a new layout and go to the layout's designer page, then load toolbar prefs
            cy.createLayout(uuid).as('testLayoutId').then((res) => {

                cy.goToLayoutAndLoadPrefs(res);
            });
        });

        /* Disabled for testing speed reasons
            afterEach(function() {
                // Remove the created layout
                cy.deleteLayout(this.testLayoutId);
            });
        */

        it('shows a toast message ("Empty Region") when trying to Publish a layout with an empty region', () => {

            cy.server();
            cy.route('PUT', '/layout/publish/*').as('layoutPublish');

            cy.get('#layout-editor-toolbar a#publishLayout').click();

            cy.get('button[data-bb-handler="Publish"]').click();

            cy.get('.toast-error').contains('Empty Region');
        });

        it('should create a new region from within the navigator edit', () => {

            // Open navigator edit
            cy.get('#layout-navigator #edit-btn').click();

            // Click on add region button
            cy.get('#layout-navigator-edit #add-btn').click();

            // Check if there are 2 regions in the timeline ( there was 1 by default )
            cy.get('#layout-timeline [data-type="region"]').should('have.length', 2);
        });

        it('should delete an existing region from within the navigator edit', () => {

            cy.server();
            cy.route('/layout?layoutId=*').as('reloadLayout');

            // Open navigator edit
            cy.get('#layout-navigator #edit-btn').click();

            // Select a region
            cy.get('#layout-navigator-edit [data-type="region"]:first-child').click();

            // Click on delete region button
            cy.get('#layout-navigator-edit #delete-btn').click();

            // Confirm modal
            cy.get('[data-test="deleteRegionModal"]').should('be.visible').find('button[data-bb-handler="confirm"]').click();

            cy.wait('@reloadLayout');

            // Check if there are no regions in the timeline ( there was 1 by default )
            cy.get('#layout-timeline [data-type="region"]').should('not.be.visible');
        });

        it('should delete a region using the toolbar bin', () => {

            cy.server();
            cy.route('/layout?layoutId=*').as('reloadLayout');

            // Open navigator edit
            cy.get('#layout-navigator #edit-btn').click();

            // Select a region from the navigator
            cy.get('#layout-navigator-edit-content [data-type="region"]:first-child').click().then(($el) => {

                const regionId = $el.attr('id');

                // Click trash container
                cy.get('#layout-navigator-edit-navbar button#delete-btn').click();

                // Confirm delete on modal
                cy.get('[data-test="deleteRegionModal"] button[data-bb-handler="confirm"]').click();

                // Check toast message
                cy.get('.toast-success').contains('Deleted');

                // Wait for the layout to reload
                cy.wait('@reloadLayout');

                // Close navigator edit
                cy.get('#layout-navigator-edit #close-btn').click();

                // Check that region is not on timeline
                cy.get('#layout-timeline [data-type="region"]#' + regionId).should('not.exist');
            });
        });

        it('creates a new widget by dragging a widget from the toolbar to layout-timeline region', () => {

            // Create and alias for reload Layout
            cy.server();
            cy.route('POST', '**/playlist/widget/embedded/*').as('createWidget');

            // Open toolbar Widgets tab
            cy.get('#layout-editor-toolbar .btn-menu-tab').contains('Tools').should('be.visible').click();
            cy.get('#layout-editor-toolbar .btn-menu-tab').contains('Widgets').should('be.visible').click();

            cy.get('#layout-editor-toolbar .toolbar-pane-content [data-sub-type="embedded"]').should('be.visible').then(() => {
                cy.dragToElement(
                    '#layout-editor-toolbar .toolbar-pane-content [data-sub-type="embedded"] .drag-area',
                    '#layout-timeline [data-type="region"]:first-child'
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

        it('creates a new widget by selecting a searched media from the toolbar to layout-navigator region', () => {

            cy.populateLibraryWithMedia();

            // Create and alias for reload Layout
            cy.server();
            cy.route('/layout?layoutId=*').as('reloadLayout');
            cy.route('/library?assignable=1&draw=2&*').as('mediaLoad');
            
            // Open a new tab
            cy.get('#layout-editor-toolbar #btn-menu-new-tab').click();

            // Select and search image items
            cy.get('.toolbar-pane.active .input-type').select('image');

            cy.wait('@mediaLoad');

            // Get a table row, select it and add to the region
            cy.get('#layout-editor-toolbar .media-table .assignItem:first').click().then(() => {
                cy.get('#layout-navigator [data-type="region"]:first-child').click().then(() => {

                    // Wait for the layout to reload
                    cy.wait('@reloadLayout');

                    // Check if there is just one widget in the timeline
                    cy.get('#layout-timeline [data-type="region"] [data-type="widget"]').then(($widgets) => {
                        expect($widgets.length).to.eq(1);
                    });
                });
            });
        });

        it('shows the file upload form by dragging a uploadable media from the toolbar to layout-navigator region', () => {

            cy.populateLibraryWithMedia();

            // Open toolbar Widgets tab
            cy.get('#layout-editor-toolbar .btn-menu-tab').contains('Tools').should('be.visible').click();
            cy.get('#layout-editor-toolbar .btn-menu-tab').contains('Widgets').should('be.visible').click();

            cy.get('#layout-editor-toolbar #content-1 .toolbar-pane-content [data-sub-type="audio"]').should('be.visible').then(() => {
                cy.dragToElement(
                    '#layout-editor-toolbar #content-1 .toolbar-pane-content [data-sub-type="audio"] .drag-area',
                    '#layout-navigator [data-type="region"]:first-child'
                ).then(() => {
                    cy.get('[data-test="uploadFormModal"]').contains('Upload media');
                });
            });
        });
    });
});