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
describe('Dayparts', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should add a daypart', function() {
    cy.visit('/daypart/view');

    // Click on the Add Daypart button
    cy.contains('Add Daypart').click();

    cy.get('.modal input#name')
      .type('Cypress Test Daypart ' + testRun + '_1');

    cy.get(':nth-child(3) > .col-sm-10 > .input-group > .flatpickr-wrapper > .datePickerHelper').click();
    // cy.get('.open > .flatpickr-time > :nth-child(1) > .arrowUp').click();
    cy.get('.open > .flatpickr-time > :nth-child(1) > .numInput').type('8');
    cy.get(':nth-child(4) > .col-sm-10 > .input-group > .flatpickr-wrapper > .datePickerHelper').click();
    cy.get('.open > .flatpickr-time > :nth-child(1) > .numInput').type('17');

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if daypart is added in toast message
    cy.contains('Added Cypress Test Daypart ' + testRun + '_1');
  });

  it('searches and edit existing daypart', function() {
    // Create a new daypart and then search for it and edit it
    cy.createDayPart('Cypress Test Daypart ' + testRun).then((id) => {
      cy.intercept({
        url: '/daypart?*',
        query: {name: 'Cypress Test Daypart ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'PUT',
        url: '/daypart/*',
      }).as('putRequest');

      cy.visit('/daypart/view');

      // Filter for the created daypart
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Daypart ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#dayparts tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#dayparts tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#dayparts tr:first-child .daypart_button_edit').click({force: true});

      cy.get('.modal input#name').clear()
        .type('Cypress Test Daypart Edited ' + testRun);

      // edit test daypart
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@putRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;

        // assertion on the "daypart" value
        expect(responseData.name).to.eq('Cypress Test Daypart Edited ' + testRun);
      });

      // Delete the daypart and assert success
      cy.deleteDayPart(id).then((res) => {
        expect(res.status).to.equal(204);
      });
    });
  });

  it('searches and delete existing daypart', function() {
    // Create a new daypart and then search for it and delete it
    cy.createDayPart('Cypress Test Daypart ' + testRun).then((res) => {
      cy.intercept({
        url: '/daypart?*',
        query: {name: 'Cypress Test Daypart ' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/daypart/view');

      // Filter for the created daypart
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Daypart ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#dayparts tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#dayparts tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#dayparts tr:first-child .daypart_button_delete').click({force: true});

      // Delete test daypart
      cy.get('.bootbox .save-button').click();

      // Check if daypart is deleted in toast message
      cy.get('.toast').contains('Deleted Cypress Test Daypart');
    });
  });

  it('searches and share existing daypart', function() {
    // Create a new daypart and then search for it and share it
    cy.createDayPart('Cypress Test Daypart ' + testRun).then((res) => {
      cy.intercept({
        url: '/daypart?*',
        query: {name: 'Cypress Test Daypart ' + testRun},
      }).as('loadGridAfterSearch');

      cy.intercept({
        query: {name: 'Everyone'},
        url: /\/user\/permissions\/DayPart\/\d+\?draw=2/,
      }).as('draw2');

      cy.intercept({
        query: {name: 'Everyone'},
        url: /\/user\/permissions\/DayPart\/\d+\?draw=3/,
      }).as('draw3');

      cy.visit('/daypart/view');

      // Filter for the created daypart
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Daypart ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#dayparts tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#dayparts tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#dayparts tr:first-child .daypart_button_permissions').click({force: true});

      cy.get('.modal #name').type('Everyone');
      cy.wait('@draw2');
      cy.get('#permissionsTable tbody tr').should('have.length', 1);

      cy.get('#permissionsTable').within(() => {
        cy.get('input[type="checkbox"][data-permission="view"]').should('be.visible').check();

        // DOM is refreshed at this point, so wait for it
        cy.wait('@draw3');
        // We have no other option but to put {force: true} here
        cy.get('input[type="checkbox"][data-permission="edit"]').check();
      });

      // Save
      cy.get('.bootbox .save-button').click();

      // Check if daypart is deleted in toast message
      cy.get('.toast').contains('Share option Updated');
    });
  });

  it('selects multiple dayparts and delete them', function() {
    // Create a new daypart and then search for it and delete it
    cy.createDayPart('Cypress Test Daypart ' + testRun).then((res) => {
      cy.intercept({
        url: '/daypart?*',
        query: {name: 'Cypress Test Daypart'},
      }).as('loadGridAfterSearch');

      // Delete all test dayparts
      cy.visit('/daypart/view');

      // Clear filter
      cy.get('#Filter input[name="name"]')
        .clear()
        .type('Cypress Test Daypart');

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');

      // Select all
      cy.get('button[data-toggle="selectAll"]').click();

      // Delete all
      cy.get('.dataTables_info button[data-toggle="dropdown"]').click();
      cy.get('.dataTables_info a[data-button-id="daypart_button_delete"]').click();

      cy.get('button.save-button').click();

      // Modal should contain one successful delete at least
      cy.get('.modal-body').contains(': Success');
    });
  });
});
