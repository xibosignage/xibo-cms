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
describe('Usergroups', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should add a usergroup', function() {
    cy.visit('/group/view');

    // Click on the Add Usergroup button
    cy.contains('Add User Group').click();

    cy.get('.modal input#group')
      .type('Cypress Test Usergroup ' + testRun + '_1');

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if usergroup is added in toast message
    cy.contains('Added Cypress Test Usergroup');
  });

  it('searches and edit existing usergroup', function() {
    // Create a new usergroup and then search for it and delete it
    cy.createUsergroup('Cypress Test Usergroup ' + testRun).then((groupId) => {
      cy.intercept({
        url: '/group?*',
        query: {userGroup: 'Cypress Test Usergroup ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'PUT',
        url: '/group/*',
      }).as('putRequest');

      cy.visit('/group/view');

      // Filter for the created usergroup
      cy.get('#Filter input[name="userGroup"]')
        .type('Cypress Test Usergroup ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#userGroups tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#userGroups tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#userGroups tr:first-child .usergroup_button_edit').click({force: true});

      cy.get('.modal input#group').clear()
        .type('Cypress Test Usergroup Edited ' + testRun);

      // edit test usergroup
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@putRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;

        // assertion on the "usergroup" value
        expect(responseData.group).to.eq('Cypress Test Usergroup Edited ' + testRun);

        // Delete the usergroup and assert success
        cy.deleteUsergroup(groupId).then((response) => {
          expect(response.status).to.equal(200);
        });
      });
    });
  });

  it('searches and delete existing usergroup', function() {
    // Create a new usergroup and then search for it and delete it
    cy.createUsergroup('Cypress Test Usergroup ' + testRun).then((groupId) => {
      cy.intercept({
        url: '/group?*',
        query: {userGroup: 'Cypress Test Usergroup ' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/group/view');

      // Filter for the created usergroup
      cy.get('#Filter input[name="userGroup"]')
        .type('Cypress Test Usergroup ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#userGroups tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#userGroups tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#userGroups tr:first-child .usergroup_button_delete').click({force: true});

      // Delete test usergroup
      cy.get('.bootbox .save-button').click();

      // Check if usergroup is deleted in toast message
      cy.get('.toast').contains('Deleted Cypress Test Usergroup');
    });
  });
});
