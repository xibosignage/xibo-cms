describe('Layout Designer (Empty)', function() {

    beforeEach(function() {
        cy.login();
    });

    context('Unexisting Layout', function() {

        it('show layout not found if layout does not exist', function() {

            // Use a huge id to test a layout not found 
            cy.visit('/layout/designer/111111111111');

            // See page not found message
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
            cy.get('[data-test="welcomeModal"] button.btn-bb-checkout').click();

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
            let uuid = Cypress._.random(0, 1e9);

            // Create a new layout and go to the layout's designer page, then load toolbar prefs
            cy.createLayout(uuid).as('testLayoutId').then((res) => {

                cy.goToLayoutAndLoadPrefs(res);
            });
        });

        it.skip('should create a new region from within the navigator edit', () => {

            // Open navigator edit
            cy.get('#layout-editor-bottombar #navigator-edit-btn').click();

            // Click on add region button
            cy.get('#layout-navigator-navbar #add-btn').click();

            // Check if there are 2 regions in the timeline ( there was 1 by default )
            cy.get('#layout-timeline [data-type="region"]').should('have.length', 2);
        });

        it.skip('should delete an existing region from within the navigator edit', () => {

            cy.server();
            cy.route('/layout?layoutId=*').as('reloadLayout');

            // Open navigator edit
            cy.get('#layout-editor-bottombar #navigator-edit-btn').click();

            // Select a region
            cy.get('#layout-navigator [data-type="region"]:first-child').click();

            // Click on delete region button
            cy.get('#layout-navigator #delete-btn').click();

            // Confirm modal
            cy.get('[data-test="deleteRegionModal"]').should('be.visible').find('button.btn-bb-confirm').click();

            cy.wait('@reloadLayout');

            // Check if there are no regions in the timeline ( there was 1 by default )
            cy.get('#layout-timeline [data-type="region"]').should('not.exist');
        });

        it.skip('should delete a region using the toolbar bin', () => {

            cy.server();
            cy.route('/layout?layoutId=*').as('reloadLayout');

            // Open navigator edit
            cy.get('#layout-editor-bottombar #navigator-edit-btn').click();

            // Select a region from the navigator
            cy.get('#layout-navigator-content [data-type="region"]:first-child').click().then(($el) => {

                const regionId = $el.attr('id');

                // Click trash container
                cy.get('#layout-navigator-navbar button#delete-btn').click();

                // Confirm delete on modal
                cy.get('[data-test="deleteRegionModal"] button.btn-bb-confirm').click();

                // Check toast message
                cy.get('.toast-success').contains('Deleted');

                // Wait for the layout to reload
                cy.wait('@reloadLayout');

                // Check that region is not on timeline
                cy.get('#layout-timeline [data-type="region"]#' + regionId).should('not.exist');
            });
        });

        it.skip('creates a new widget by dragging a widget from the toolbar to layout-timeline region', () => {

            // Create and alias for reload Layout
            cy.server();
            cy.route('POST', '**/playlist/widget/clock/*').as('createWidget');

            // Open toolbar Widgets tab
            cy.get('#layout-editor-toolbar #btn-menu-0').should('be.visible').click({force: true});
            cy.get('#layout-editor-toolbar #btn-menu-1').should('be.visible').click({force: true});

            cy.get('#layout-editor-toolbar .toolbar-pane-content [data-sub-type="clock"]').should('be.visible').then(() => {
                cy.dragToElement(
                    '#layout-editor-toolbar .toolbar-pane-content [data-sub-type="clock"] .drag-area',
                    '#layout-timeline .designer-region:first'
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
            cy.route('/library/search?assignable=1&retired=0&*').as('mediaLoad');

            cy.get('.timeline-panel.toggle-panel .toggle-container .toggle').click();

            // Open library search tab
            cy.get('#layout-editor-toolbar #btn-menu-0').should('be.visible').click({force: true});
            cy.get('#layout-editor-toolbar #btn-menu-1').should('be.visible').click({force: true});

            cy.wait('@mediaLoad');

            cy.get('#layout-editor-bottombar #navigator-edit-btn').click({force: true});

            cy.wait(1000);

            // Get a table row, select it and add to the region
            cy.get('#layout-editor-toolbar #media-content-1 .select-button:first').click({force: true}).then(() => {
                cy.get('#layout-navigator [data-type="region"]:first-child').click({force: true}).then(() => {

                    // Wait for the layout to reload
                    cy.wait('@reloadLayout');

                    // Check if there is just one widget in the timeline
                    cy.get('#layout-timeline [data-type="region"] [data-type="widget"]').then(($widgets) => {
                        expect($widgets.length).to.eq(1);
                    });
                });
            });
        });

        it.skip('shows the file upload form by dragging a uploadable media from the toolbar to layout-navigator region', () => {

            cy.populateLibraryWithMedia();

            // Open toolbar Widgets tab
            cy.get('#layout-editor-toolbar #btn-menu-0').should('be.visible').click({force: true});
            cy.get('#layout-editor-toolbar #btn-menu-1').should('be.visible').click({force: true});

            cy.get('#layout-editor-bottombar #navigator-edit-btn').click();

            cy.get('#layout-editor-toolbar #content-2 .toolbar-pane-content [data-sub-type="audio"]').should('be.visible').then(() => {
                cy.dragToElement(
                    '#layout-editor-toolbar #content-2 .toolbar-pane-content [data-sub-type="audio"] .drag-area',
                    '#layout-navigator [data-type="region"]:first-child'
                ).then(() => {
                    cy.get('[data-test="uploadFormModal"]').contains('Upload media');
                });
            });
        });
    });
});