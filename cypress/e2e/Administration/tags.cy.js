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
describe('Tags', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should add a tag', function() {
    cy.visit('/tag/view');

    // Click on the Add Tag button
    cy.contains('Add Tag').click();

    cy.get('.modal input#name')
      .type('Cypress Test Tag ' + testRun + '_1');

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if tag is added in toast message
    cy.contains('Added Cypress Test Tag ' + testRun + '_1');
  });

  it('searches and edit existing tag', function() {
    // Create a new tag and then search for it and delete it
    cy.createTag('Cypress Test Tag ' + testRun).then((res) => {
      cy.intercept({
        url: '/tag?*',
        query: {tag: 'Cypress Test Tag ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'PUT',
        url: '/tag/*',
      }).as('putRequest');

      cy.visit('/tag/view');

      // Filter for the created tag
      cy.get('#Filter input[name="tag"]')
        .type('Cypress Test Tag ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#tags tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#tags tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#tags tr:first-child .tag_button_edit').click({force: true});

      cy.get('.modal input#name').clear()
        .type('Cypress Test Tag Edited ' + testRun);

      // edit test tag
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@putRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;
        const tag = responseData.tag;

        // assertion on the "tag" value
        expect(tag).to.eq('Cypress Test Tag Edited ' + testRun);
      });
    });
  });

  it('searches and delete existing tag', function() {
    // Create a new tag and then search for it and delete it
    cy.createTag('Cypress Test Tag ' + testRun).then((res) => {
      cy.intercept({
        url: '/tag?*',
        query: {tag: 'Cypress Test Tag ' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/tag/view');

      // Filter for the created tag
      cy.get('#Filter input[name="tag"]')
        .type('Cypress Test Tag ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#tags tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#tags tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#tags tr:first-child .tag_button_delete').click({force: true});

      // Delete test tag
      cy.get('.bootbox .save-button').click();

      // Check if tag is deleted in toast message
      cy.get('.toast').contains('Deleted Cypress Test Tag');
    });
  });

  it('selects multiple tags and delete them', function() {
    // Create a new tag and then search for it and delete it
    cy.createTag('Cypress Test Tag ' + testRun).then((res) => {
      cy.intercept({
        url: '/tag?*',
        query: {tag: 'Cypress Test Tag'},
      }).as('loadGridAfterSearch');

      // Delete all test tags
      cy.visit('/tag/view');

      // Clear filter
      cy.get('.clear-filter-btn').click();
      cy.get('#Filter input[name="tag"]')
        .type('Cypress Test Tag');

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');

      // Select all
      cy.get('button[data-toggle="selectAll"]').click();

      // Delete all
      cy.get('.dataTables_info button[data-toggle="dropdown"]').click();
      cy.get('.dataTables_info a[data-button-id="tag_button_delete"]').click();

      cy.get('button.save-button').click();

      // Modal should contain one successful delete at least
      cy.get('.modal-body').contains(': Success');
    });
  });
});
