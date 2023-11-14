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
describe('Users', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should add a user', function() {
    cy.intercept({
      url: '/user/form/homepages?groupId=1&userTypeId=3*',
      query: {},
    }).as('loadHomepageAfterSearch');

    cy.visit('/user/view');

    // Click on the Add User button
    cy.contains('Add User').click();
    cy.get('.radio input[value="manual"]').click();

    cy.get('#onboarding-steper-next-button').click();

    cy.get('.modal input#userName')
      .type('CypressTestUser' + testRun);

    cy.get('.modal input#password')
      .type('cypress');

    // Error checking - for incorrect email format
    cy.get('.modal input#email').type('cypress');

    cy.get('.select2-container--bootstrap').eq(1).click();
    cy.log('Before waiting for Icon Dashboard element');
    cy.wait('@loadHomepageAfterSearch');
    cy.get('.select2-results__option')
      .should('contain', 'Icon Dashboard')
      .click();

    // Try saving
    cy.get('.modal .save-button').click();

    cy.contains('Please enter a valid email address.');
    cy.get('.modal input#email').clear().type('cypress@test.com');

    // Save
    cy.get('.modal .save-button').click();

    // Check if user is added in toast message
    cy.contains('Added CypressTestUser');
  });

  it('searches and edit existing user', function() {
    // Create a new user and then search for it and delete it
    cy.createUser('CypressTestUser' + testRun, 'password', 3, 1).then((id) => {
      cy.intercept({
        url: '/user?*',
        query: {userName: 'CypressTestUser' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'PUT',
        url: '/user/*',
      }).as('putRequest');

      cy.visit('/user/view');

      // Filter for the created user
      cy.get('#Filter input[name="userName"]')
        .type('CypressTestUser' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#users tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#users tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#users tr:first-child .user_button_edit').click({force: true});

      cy.get('.modal input#userName').clear()
        .type('CypressTestUserEdited' + testRun);

      cy.get('.modal input#newPassword').clear().type('newPassword');
      cy.get('.modal input#retypeNewPassword').clear().type('wrongPassword');

      // edit test user
      cy.get('.bootbox .save-button').click();
      cy.wait('@putRequest')

      // Error checking - for password mismatch
      cy.contains('Passwords do not match');
      cy.get('.modal input#retypeNewPassword').clear().type('newPassword');

      // edit test user
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@putRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;

        // assertion on the "user" value
        expect(responseData.userName).to.eq('CypressTestUserEdited' + testRun);
      });

      // Delete the user and assert success
      cy.deleteUser(id).then((res) => {
        expect(res.status).to.equal(200);
      });
    });
  });

  it('searches and delete existing user', function() {
    // Create a new user and then search for it and delete it
    cy.createUser('CypressTestUser' + testRun, 'password', 3, 1).then((id) => {
      cy.intercept({
        url: '/user?*',
        query: {userName: 'CypressTestUser' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/user/view');

      // Filter for the created user
      cy.get('#Filter input[name="userName"]')
        .type('CypressTestUser' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#users tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#users tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#users tr:first-child .user_button_delete').click({force: true});

      // Delete test User
      cy.get('.bootbox .save-button').click();

      // Check if User is deleted in toast message
      cy.get('.toast').contains('Deleted CypressTestUser');
    });
  });
});
