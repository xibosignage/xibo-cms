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

    it('creates a new tab in the toolbar and searches for items', () => {

        cy.server();
        cy.route('/library?assignable=1&draw=2&*').as('mediaLoad');

        cy.populateLibraryWithMedia();

        cy.get('#playlist-editor-toolbar #btn-menu-new-tab').click();

        // Select and search image items
        cy.get('.toolbar-pane.active .input-type').select('audio');

        cy.wait('@mediaLoad');

        // Check if there are audio items in the search content
        cy.get('#playlist-editor-toolbar .media-table tbody tr:first').should('be.visible').contains('audio');
    });

    it('creates multiple tabs and then closes them all', () => {

        cy.get('#playlist-editor-toolbar [data-test="toolbarTabs"]').then(($el) => {

            const numTabs = $el.length;

            // Create 3 tabs
            cy.get('#playlist-editor-toolbar #btn-menu-new-tab').click();
            cy.get('#playlist-editor-toolbar #btn-menu-new-tab').click();
            cy.get('#playlist-editor-toolbar #btn-menu-new-tab').click();

            // Check if there are 4 tabs in the toolbar ( widgets default one and the 3 created )
            cy.get('#playlist-editor-toolbar [data-test="toolbarTabs"]').should('be.visible').should('have.length', numTabs + 3);

            // Close all tabs using the toolbar button and chek if there is just one tab
            cy.get('#playlist-editor-toolbar #deleteAllTabs').click().then(() => {
                cy.get('#playlist-editor-toolbar [data-test="toolbarTabs"]').should('be.visible').should('have.length', numTabs);
            });
        });

    });

    it('creates a new widget by selecting a searched media from the toolbar to the editor, and then reverts the change', () => {

        cy.populateLibraryWithMedia();

        // Create and alias for reload playlist
        cy.server();
        cy.route('/playlist?playlistId=*').as('reloadPlaylist');
        cy.route('DELETE', '/playlist/widget/*').as('deleteWidget');
        cy.route('/library?assignable=1&draw=2&*').as('mediaLoad');

        // Open a new tab
        cy.get('#playlist-editor-toolbar #btn-menu-new-tab').click();

        // Select and search image items
        cy.get('.toolbar-pane.active .input-type').select('image');

        cy.wait('@mediaLoad');

        // Get a table row, select it and add to the dropzone
        cy.get('#playlist-editor-toolbar .media-table .assignItem:first').click().then(() => {
            cy.get('#dropzone-container').click({force: true}).then(() => {

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