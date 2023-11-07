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
describe('Display Settings', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should and edit a display setting', function() {
    // Intercept the POST request
    cy.intercept({
      method: 'POST',
      url: '/displayprofile',
    }).as('postRequest');

    // Intercept the PUT request
    cy.intercept({
      method: 'PUT',
      url: '/displayprofile/*',
    }).as('putRequest');

    cy.visit('/displayprofile/view');

    // Click on the Add Display Setting button
    cy.contains('Add Profile').click();

    cy.get('.modal input#name')
      .type('Cypress Test Display Setting ' + testRun);

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Wait for the intercepted PUT request and check the form data
    cy.wait('@postRequest').then((interception) => {
      // Get the request body (form data)
      const response = interception.response;
      const responseData = response.body.data;

      // assertion on the "tag" value
      expect(responseData.name).to.eq('Cypress Test Display Setting ' + testRun);

      cy.get('.modal input#name').clear()
        .type('Cypress Test Display Setting Edited ' + testRun);

      // Select the option with the value "10 minutes"
      cy.get('.modal #collectInterval').select('600');

      // Add first by clicking next
      cy.get('.modal .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@putRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;

        // assertion on the "tag" value
        expect(responseData.name).to.eq('Cypress Test Display Setting Edited ' + testRun);
      });
    });
  });

  it('searches and edit existing display setting', function() {
    // Create a new tag and then search for it and delete it
    cy.createDisplayProfile('Cypress Test Display Setting ' + testRun, 'android').then((id) => {
      cy.intercept({
        url: '/displayprofile?*',
        query: {displayProfile: 'Cypress Test Display Setting ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'PUT',
        url: '/displayprofile/*',
      }).as('putRequest');

      cy.visit('/displayprofile/view');

      // Filter for the created tag
      cy.get('#Filter input[name="displayProfile"]')
        .type('Cypress Test Display Setting ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#displayProfiles tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#displayProfiles tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#displayProfiles tr:first-child .displayprofile_button_edit').click({force: true});

      cy.get('.modal input#name').clear()
        .type('Cypress Test Display Setting Edited ' + testRun);

      // edit test tag
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@putRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;

        // assertion on the "tag" value
        expect(responseData.name).to.eq('Cypress Test Display Setting Edited ' + testRun);
      });

      // Delete the user and assert success
      cy.deleteDisplayProfile(id).then((res) => {
        expect(res.status).to.equal(204);
      });
    });
  });

  it('searches and delete existing display setting', function() {
    // Create a new tag and then search for it and delete it
    cy.createDisplayProfile('Cypress Test Display Setting ' + testRun, 'android').then((id) => {
      cy.intercept({
        url: '/displayprofile?*',
        query: {displayProfile: 'Cypress Test Display Setting ' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/displayprofile/view');

      // Filter for the created tag
      cy.get('#Filter input[name="displayProfile"]')
        .type('Cypress Test Display Setting ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#displayProfiles tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#displayProfiles tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#displayProfiles tr:first-child .displayprofile_button_delete').click({force: true});

      // Delete test tag
      cy.get('.bootbox .save-button').click();

      // Check if tag is deleted in toast message
      cy.get('.toast').contains('Deleted Cypress Test Display Setting');
    });
  });
});
