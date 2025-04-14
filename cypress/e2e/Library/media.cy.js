describe('Media Admin', function() {
    let testRun;
  
    beforeEach(function() {
      cy.login();
  
      testRun = Cypress._.random(0, 1e9);
    });
  
    it('should add a media via url', function() {
      cy.visit('/library/view');
  
      // Click on the Add Playlist button
      cy.contains('Add media (URL)').click();
  
      cy.get('#url')
        .type('https://file-examples.com/storage/fef7a0384867fa86095088c/2017/04/file_example_MP4_480_1_5MG.mp4');
      
      cy.get('#optionalName')
        .type('Cypress Test Media ' + testRun);
  
      cy.get('.modal .save-button').click();
      cy.wait(24000);
  
      // Filter for the created playlist
      cy.get('#media')
        .type('Cypress Test Media ' + testRun);
  
      // Should have the added playlist
      cy.get('#libraryItems tbody tr').should('have.length', 1);
      cy.get('#libraryItems tbody tr:nth-child(1) td:nth-child(2)').contains('Cypress Test Media ' + testRun);
    });
  
    it('should cancel adding a media', function() {
      cy.visit('/library/view');
  
      // Click on the Add Playlist button
      cy.contains('Add media (URL)').click();
  
      cy.get('#url')
        .type('https://file-examples.com/storage/fef7a0384867fa86095088c/2017/04/file_example_MP4_480_1_5MG.mp4');
      
      cy.get('#optionalName')
        .type('Cypress Test Media ' + testRun);
  
      // Click cancel
      cy.get('#dialog_btn_1').click();
  
      // Check if you are back to the view page
      cy.url().should('include', '/library/view');
    });
  
    it('should show a list of Media', function() {
      // Wait for playlist grid reload
      cy.intercept('/library?draw=1&*').as('mediaGridLoad');
  
      cy.visit('/library/view').then(function() {
        cy.wait('@mediaGridLoad');
        cy.get('#libraryItems');
      });
    });
  
    // it('selects multiple playlists and delete them', function() {
    //   // Create a new playlist and then search for it and delete it
    //   cy.createNonDynamicPlaylist('Cypress Test Playlist ' + testRun).then(() => {
    //     cy.intercept('/library?draw=1&*').as('mediaGridLoad');
  
    //     // Delete all test playlists
    //     cy.visit('/library/view');
  
    //     // Clear filter and search for text playlists
    //     cy.get('#Filter input[name="name"]')
    //       .clear()
    //       .type('Cypress Test Playlist');
  
    //     // Wait for 2nd playlist grid reload
    //     cy.wait('@playlistGridLoad');
  
    //     // Select all
    //     cy.get('button[data-toggle="selectAll"]').click();
  
    //     // Delete all
    //     cy.get('.dataTables_info button[data-toggle="dropdown"]').click({force: true});
    //     cy.get('.dataTables_info a[data-button-id="playlist_button_delete"]').click({force: true});
  
    //     cy.get('button.save-button').click();
  
    //     // Modal should contain one successful delete at least
    //     cy.get('.modal-body').contains(': Success');
    //   });
    // });
  });