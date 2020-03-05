describe('Playlist Editor (Empty)', function() {

    beforeEach(function() {
        cy.login();

        // Create random name
        let uuid = Cypress._.random(0, 1e9);

        // Create a new layout and go to the layout's designer page
        cy.createNonDynamicPlaylist(uuid).as('testPlaylistId').then((res) => {
            cy.openPlaylistEditorAndLoadPrefs(res);
        });
    });

    /* Disabled for testing speed reasons
        after(function() {
            // Remove the created layout
            cy.deletePlaylist(this.testPlaylistId);
        });
    */

    it('should show the droppable zone and toolbar', function() {

        cy.get('#dropzone-container').should('be.visible');
        cy.get('#playlist-editor-toolbar nav').should('be.visible');
    });

    it('creates a new widget by dragging a widget from the toolbar to the editor', () => {

        // Create and alias for reload playlist
        cy.server();
        cy.route('POST', '**/playlist/widget/embedded/*').as('createWidget');

        // Open toolbar Widgets tab
        cy.get('#playlist-editor-toolbar #btn-menu-1').should('be.visible').click();
        cy.get('#playlist-editor-toolbar #btn-menu-2').should('be.visible').click();

        cy.get('#playlist-editor-toolbar .toolbar-pane-content [data-sub-type="embedded"]').should('be.visible').then(() => {
            cy.dragToElement(
                '#playlist-editor-toolbar .toolbar-pane-content [data-sub-type="embedded"] .drag-area',
                '#dropzone-container'
            ).then(() => {
                // Wait for the widget to be added
                cy.wait('@createWidget');

                // Check if there is just one widget in the timeline
                cy.get('#timeline-container [data-type="widget"]').then(($widgets) => {
                    expect($widgets.length).to.eq(1);
                });
            });
        });

    });

});