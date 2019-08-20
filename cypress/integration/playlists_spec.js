describe('Playlists Admin', function () {

    var testRun = Cypress._.random(0, 1e6);

    beforeEach(function () {
        cy.login();
    });

    it('should show a list of Playlists', function () {
        cy.visit('/playlist/view');

        cy.contains('Playlists');
    });

    it('should add a non-dynamic playlist', function() {
        cy.visit('/playlist/view');

        // Click on the Add Playlist button
        cy.contains('Add Playlist').click();

        cy.get('.modal input#name')
            .type('Cypress Test Playlist ' + testRun);

        cy.get('.modal .save-button').click();

        // Filter for the created playlist
        cy.get('#Filter input[name="name"]')
            .type('Cypress Test Playlist ' + testRun);

        // Should have the added playlist
        cy.get('#playlists tbody tr').should('have.length', 1);
        cy.get('#playlists tbody tr:nth-child(1) td:nth-child(2)').contains('Cypress Test Playlist ' + testRun);
    });

});