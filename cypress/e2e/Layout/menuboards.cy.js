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
describe('Menuboards', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should add a menuboard', function() {
    cy.visit('/menuboard/view');

    // Click on the Add Menuboard button
    cy.contains('Add Menu Board').click();

    cy.get('.modal input#name')
      .type('Cypress Test Menuboard ' + testRun + '_1');
    cy.get('.modal input#code')
      .type('MENUBOARD');
    cy.get('.modal textarea#description')
      .type('Menuboard Description');

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if menuboard is added in toast message
    cy.contains('Added Menu Board');
  });

  it('searches and edit existing menuboard', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((res) => {
      cy.intercept({
        url: '/menuboard?*',
        query: {name: 'Cypress Test Menuboard ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'PUT',
        url: '/menuboard/*',
      }).as('putRequest');

      cy.visit('/menuboard/view');

      // Filter for the created menuboard
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Menuboard ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');

      // Click on the first row element to open the delete modal
      cy.get('#menuBoards tr:first-child .dropdown-toggle').click();
      cy.get('#menuBoards tr:first-child .menuBoard_edit_button').click();

      cy.get('.modal input#name').clear()
        .type('Cypress Test Menuboard Edited ' + testRun);

      // edit test menuboard
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@putRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data; // Access the "data" property
        const menuboard = responseData.name;

        // assertion on the "menuboard" value
        expect(menuboard).to.eq('Cypress Test Menuboard Edited ' + testRun);
      });
    });
  });

  it('searches and delete existing menuboard', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((res) => {
      cy.intercept({
        url: '/menuboard?*',
        query: {name: 'Cypress Test Menuboard ' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/menuboard/view');

      // Filter for the created menuboard
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Menuboard ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');

      // Click on the first row element to open the delete modal
      cy.get('#menuBoards tr:first-child .dropdown-toggle').click();
      cy.get('#menuBoards tr:first-child .menuBoard_delete_button').click();

      // Delete test menuboard
      cy.get('.bootbox .save-button').click();

      // Check if menuboard is deleted in toast message
      cy.get('.toast').contains('Deleted Cypress Test Menuboard');
    });
  });

  it.only('add categories to a menuboard', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((res) => {
      cy.intercept({
        url: '/menuboard?*',
        query: {name: 'Cypress Test Menuboard ' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/menuboard/view');

      // Filter for the created menuboard
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Menuboard ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');

      // Click on the first row element to open the delete modal
      cy.get('#menuBoards tr:first-child .dropdown-toggle').click();
      cy.get('#menuBoards tr:first-child .menuBoard_button_viewcategories').click();

      // Click on the Add Category button
      cy.contains('Add Category').click();

      cy.get('.modal input#name')
        .type('Cypress Test Menuboard Category ' + testRun + '_1');
      cy.get('.modal input#code')
        .type('MENUBOARDCAT');
      cy.get('.modal input#description')
        .type('Menuboard Description');

      // Add first by clicking next
      cy.get('.modal .save-button').click();

      // Check if menuboard is added in toast message
      cy.contains('Added Menu Board Category');
    });
  });
});
