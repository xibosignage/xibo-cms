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
describe('Playlists Admin', function() {
  let testRun;

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
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

  it('should cancel adding a non-dynamic playlist', function() {
    cy.visit('/playlist/view');

    // Click on the Add Playlist button
    cy.contains('Add Playlist').click();

    cy.get('.modal input#name')
      .type('Cypress Test Playlist ' + testRun);

    // Click cancel
    cy.get('#dialog_btn_1').click();

    // Check if you are back to the view page
    cy.url().should('include', '/playlist/view');
  });

  it('should show a list of Playlists', function() {
    // Wait for playlist grid reload
    cy.server();
    cy.route('/playlist?draw=1&*').as('playlistGridLoad');

    cy.visit('/playlist/view').then(function() {
      cy.wait('@playlistGridLoad');
      cy.get('#playlists');
    });
  });

  it('selects multiple playlists and delete them', function() {
    // Create a new playlist and then search for it and delete it
    cy.createNonDynamicPlaylist('Cypress Test Playlist ' + testRun).then(() => {
      cy.server();
      cy.route('/playlist?draw=2&*').as('playlistGridLoad');

      // Delete all test playlists
      cy.visit('/playlist/view');

      // Clear filter and search for text playlists
      cy.get('#Filter input[name="name"]')
        .clear()
        .type('Cypress Test Playlist');

      // Wait for 2nd playlist grid reload
      cy.wait('@playlistGridLoad');

      // Select all
      cy.get('button[data-toggle="selectAll"]').click();

      // Delete all
      cy.get('.dataTables_info button[data-toggle="dropdown"]').click({force: true});
      cy.get('.dataTables_info a[data-button-id="playlist_button_delete"]').click({force: true});

      cy.get('button.save-button').click();

      // Modal should contain one successful delete at least
      cy.get('.modal-body').contains(': Success');
    });
  });
});