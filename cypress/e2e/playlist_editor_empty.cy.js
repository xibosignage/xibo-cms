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
});