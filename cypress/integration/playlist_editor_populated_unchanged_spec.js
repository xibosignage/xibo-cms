describe('Playlist Editor (Populated/Unchanged)', function() {

    before(function() {
        cy.login();

        // Create random name
        let uuid = Cypress._.random(0, 1e9);

        // Create a new layout and go to the layout's designer page
        cy.createNonDynamicPlaylist(uuid).as('testPlaylistId').then((res) => {

            // Populate playlist with some widgets and media
            cy.addWidgetToPlaylist(res, 'embedded', {
                name: 'Embedded Widget'
            });

            cy.addRandomMediaToPlaylist(res);

            cy.addWidgetToPlaylist(res, 'clock', {
                name: 'Clock Widget'
            });
        });
    });

    /* Disabled for testing speed reasons
        after(function() {
            // Remove the created layout
            cy.deletePlaylist(this.testPlaylistId);
        });
    */

    beforeEach(function() {
        cy.login();
        cy.openPlaylistEditorAndLoadPrefs(this.testPlaylistId);
    });

    it('opens a media tab in the toolbar and searches for items', () => {

        cy.server();
        cy.route('/library/search?assignable=1&retired=0&*').as('mediaLoad');

        cy.populateLibraryWithMedia();

        // Open library search tab
        cy.get('#playlist-editor-toolbar #btn-menu-0').should('be.visible').click();
        cy.get('#playlist-editor-toolbar #btn-menu-1').should('be.visible').click();

        cy.wait('@mediaLoad');

        // Check if there are audio items in the search content
        cy.get('#playlist-editor-toolbar #content-1 .toolbar-card').should('be.visible');
    });

    it('creates a new widget by selecting a searched media from the toolbar to the editor, and then reverts the change', () => {
        cy.populateLibraryWithMedia();

        // Create and alias for reload playlist
        cy.server();
        cy.route('/playlist?playlistId=*').as('reloadPlaylist');
        cy.route('DELETE', '/playlist/widget/*').as('deleteWidget');
        cy.route('/library/search?assignable=1&retired=0&*').as('mediaLoad');

        // Open library search tab
        cy.get('#playlist-editor-toolbar #btn-menu-0').should('be.visible').click();
        cy.get('#playlist-editor-toolbar #btn-menu-2').should('be.visible').click();

        cy.wait('@mediaLoad');
        cy.wait(1000);

        // Get a table row, select it and add to the dropzone
        cy.get('#playlist-editor-toolbar .toolbar-card .select-button:first').click({force: true}).then(() => {
            cy.get('#timeline-overlay-container').click({force: true}).then(() => {

                // Wait for the layout to reload
                cy.wait('@reloadPlaylist');

                // Check if there is just one widget in the timeline
                cy.get('#timeline-container [data-type="widget"]').then(($widgets) => {
                    expect($widgets.length).to.eq(4);
                });

                // Click the revert button
                cy.get('#playlist-editor-toolbar #undoContainer').click();

                // Wait for the widget to be deleted and for the playlist to reload
                cy.wait('@deleteWidget');
                cy.wait('@reloadPlaylist');

                // Check if there is just one widget in the timeline
                cy.get('#timeline-container [data-type="widget"]').then(($widgets) => {
                    expect($widgets.length).to.eq(3);
                });
            });
        });
    });
});