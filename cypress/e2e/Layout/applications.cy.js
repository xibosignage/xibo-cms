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
describe('Applications', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should add edit an application', function() {
    // Intercept the PUT request
    cy.intercept({
      method: 'PUT',
      url: '/application/*',
    }).as('putRequest');

    cy.visit('/application/view');

    // Click on the Add Application button
    cy.contains('Add Application').click();

    cy.get('.modal input#name')
      .type('Cypress Test Application ' + testRun);

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if application is added in toast message
    cy.contains('Edit Application');

    cy.get('.modal input#name').clear()
      .type('Cypress Test Application Edited ' + testRun);

    // edit test application
    cy.get('.bootbox .save-button').click();

    // Wait for the intercepted PUT request and check the form data
    cy.wait('@putRequest').then((interception) => {
      // Get the request body (form data)
      const response = interception.response;
      const responseData = response.body.data;

      // assertion on the "application" value
      expect(responseData.name).to.eq('Cypress Test Application Edited ' + testRun);
      // Return appKey as a Cypress.Promise to ensure proper scoping
      return Cypress.Promise.resolve(responseData.key);
    }).then((appKey) => {
      if (appKey) {
        // TODO cannot be deleted via cypress
        // Delete the application and assert success
        // cy.deleteApplication(appKey).then((res) => {
        //   expect(res.status).to.equal(200);
        // });
      }
    });
  });
});
