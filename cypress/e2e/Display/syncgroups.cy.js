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
describe('Sync Groups', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should add one empty syncgroups', function() {
    cy.visit('/syncgroup/view');

    // Click on the Add Sync Group button
    cy.contains('Add Sync Group').click();

    cy.get('.modal input#name')
      .type('Cypress Test Sync Group ' + testRun);

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if syncgroup is added in toast message
    cy.contains('Added Cypress Test Sync Group ' + testRun);
  });

  it('searches and delete existing syncgroup', function() {
    // Create a new syncgroup and then search for it and delete it
    cy.createSyncGroup('Cypress Test Sync Group ' + testRun).then((res) => {
      cy.intercept({
        url: '/syncgroup?*',
        query: {name: 'Cypress Test Sync Group ' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/syncgroup/view');

      // Filter for the created syncgroup
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Sync Group ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#syncgroups tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#syncgroups tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#syncgroups tr:first-child .syncgroup_button_group_delete').click({force: true});

      // Delete test syncgroup
      cy.get('.bootbox .save-button').click();

      // Check if syncgroup is deleted in toast message
      cy.get('.toast').contains('Deleted Cypress Test Sync Group');
    });
  });

  // ---------
  // Tests - Error handling
  it.only('should not add a syncgroup without publisher port', function() {
    cy.visit('/syncgroup/view');

    // Click on the Add Sync Group button
    cy.contains('Add Sync Group').click();

    cy.get('.modal input#name')
        .type('Cypress Test Sync Group ' + testRun);

    cy.get('#syncPublisherPort').clear();

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if syncgroup is added in toast message
    cy.contains('Sync Publisher Port cannot be empty');
  });
});
