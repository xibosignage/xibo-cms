/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

/* eslint-disable max-len */
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
        .type('https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4');
      
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
        .type('https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4');
      
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
  
    it('selects media and delete them', function() {
      // Create a new playlist and then search for it and delete it
        cy.intercept('/library?draw=1&*').as('mediaGridLoad');
  
        // Delete all test playlists
        cy.visit('/library/view');
  
        // Clear filter and search for text playlists
        cy.get('#media')
          .clear()
          .type('Cypress Test Media');
  
        // Wait for 1st playlist grid reload
        cy.wait('@mediaGridLoad');
  
        // Select first entry
        cy.get('table#libraryItems').contains('Cypress Test Media').parents('tr.odd').should('be.visible').click();
        cy.get('button[data-toggle="dropdown"]').first().click();
  
        // Click Delete
        cy.contains('Delete').click();
        cy.get('button.save-button').click();
  
        // Modal should contain one successful delete at least
        cy.get('div[class="toast-message"]').should('contain', 'Deleted');
      });
  });