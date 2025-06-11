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
describe('Display Groups', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should add one empty and one filled display groups', function() {
    cy.visit('/displaygroup/view');

    // Click on the Add Displaygroup button
    cy.contains('Add Display Group').click();

    cy.get('.modal input#displayGroup')
      .type('Cypress Test Displaygroup ' + testRun + '_1');

    // Add first by clicking next
    cy.get('.modal').contains('Next').click();

    // Check if displaygroup is added in toast message
    cy.contains('Added Cypress Test Displaygroup ' + testRun + '_1');

    cy.get('.modal input#displayGroup')
      .type('Cypress Test Displaygroup ' + testRun + '_2');

    cy.get('.modal input#description')
      .type('Description');

    cy.get('.modal input#isDynamic').check();

    cy.get('.modal input#dynamicCriteria')
      .type('testLayoutId');

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if displaygroup is added in toast message
    cy.contains('Added Cypress Test Displaygroup ' + testRun + '_2');
  });

  it('copy an existing displaygroup', function() {
    // Create a new displaygroup and then search for it and delete it
    cy.createDisplaygroup('Cypress Test Displaygroup ' + testRun).then((res) => {
      cy.intercept({
        url: '/displaygroup?*',
        query: {displayGroup: 'Cypress Test Displaygroup ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the POST request
      cy.intercept({
        method: 'POST',
        url: /\/displaygroup\/\d+\/copy$/,
      }).as('postRequest');

      cy.visit('/displaygroup/view');

      // Filter for the created displaygroup
      cy.get('#Filter input[name="displayGroup"]')
        .type('Cypress Test Displaygroup ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#displaygroups tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#displaygroups tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#displaygroups tr:first-child .displaygroup_button_copy').click({force: true});

      // Delete test displaygroup
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted POST request and check the form data
      cy.wait('@postRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;
        expect(responseData.displayGroup).to.include('Cypress Test Displaygroup ' + testRun + ' 2');
      });
    });
  });

  it('searches and delete existing displaygroup', function() {
    // Create a new displaygroup and then search for it and delete it
    cy.createDisplaygroup('Cypress Test Displaygroup ' + testRun).then((res) => {
      cy.intercept({
        url: '/displaygroup?*',
        query: {displayGroup: 'Cypress Test Displaygroup ' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/displaygroup/view');

      // Filter for the created displaygroup
      cy.get('#Filter input[name="displayGroup"]')
        .type('Cypress Test Displaygroup ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#displaygroups tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#displaygroups tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#displaygroups tr:first-child .displaygroup_button_delete').click({force: true});

      // Delete test displaygroup
      cy.get('.bootbox .save-button').click();

      // Check if displaygroup is deleted in toast message
      cy.get('.toast').contains('Deleted Cypress Test Displaygroup');
    });
  });

  // Seeded displays: dispgrp_disp1, dispgrp_disp2
  it('manage membership for a displaygroup', function() {
    cy.createDisplaygroup('Cypress Test Displaygroup ' + testRun).then((res) => {
      // assign displays to display group
      cy.intercept({
        url: '/displaygroup?*',
        query: {displayGroup: 'Cypress Test Displaygroup ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'POST',
        url: /\/displaygroup\/\d+\/display\/assign$/,
      }).as('postRequest');

      cy.intercept({
        url: '/display*',
        query: {display: 'dispgrp_disp1'},
      }).as('loadDisplayAfterSearch');

      cy.visit('/displaygroup/view');

      // Filter for the created displaygroup
      cy.get('#Filter input[name="displayGroup"]')
        .type('Cypress Test Displaygroup ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#displaygroups tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#displaygroups tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#displaygroups tr:first-child .displaygroup_button_group_members').click({force: true});

      cy.get('.modal #display').type('dispgrp_disp1');

      cy.wait('@loadDisplayAfterSearch');
      cy.get('#displaysMembersTable').within(() => {
        // count the rows within table
        cy.get('tbody').find('tr').should('have.length', 1);
        cy.get('tbody tr:first-child input[type="checkbox"]').check();
      });

      // Save assignments
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted POST request and check the form data
      cy.wait('@postRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const body = response.body;
        expect(body.success).to.eq(true);
      });
    });
  });

  // -------
  // Seeded displays: dispgrp_disp_dynamic1, dispgrp_disp_dynamic2
  it('should add a dynamic display group', function() {
    cy.intercept({
      url: '/display?*',
      query: {display: 'dynamic'},
    }).as('loadDisplayGridAfterSearch');

    cy.visit('/displaygroup/view');

    // Click on the Add Displaygroup button
    cy.contains('Add Display Group').click();

    cy.get('.modal input#displayGroup')
      .type('Cypress Test Displaygroup ' + testRun);

    // Add first by clicking next
    cy.get('.modal #isDynamic').check();
    // Type "dynamic" into the input field with the name "dynamicCriteria"
    cy.get('.modal input[name="dynamicCriteria"]').type('dynamic');
    cy.wait('@loadDisplayGridAfterSearch');

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if displaygroup is added in toast message
    cy.contains('Added Cypress Test Displaygroup ' + testRun);
  });

  it('should edit the criteria of a dynamic display group', function() {
    // Create a new displaygroup with dynamic criteria
    cy.createDisplaygroup('Cypress Test Displaygroup Dynamic ' + testRun, true, 'dynamic').then((res) => {
      cy.intercept({
        url: '/displaygroup?*',
        query: {displayGroup: 'Cypress Test Displaygroup Dynamic ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'PUT',
        url: '/displaygroup/*',
      }).as('putRequest');

      cy.visit('/displaygroup/view');

      // Filter for the created displaygroup
      cy.get('#Filter input[name="displayGroup"]')
        .type('Cypress Test Displaygroup Dynamic ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#displaygroups tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#displaygroups tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#displaygroups tr:first-child .displaygroup_button_edit').click({force: true});

      cy.get('.modal input[name="dynamicCriteria"]').clear().type('dynamic_edited');

      // Delete test displaygroup
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@putRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;

        // assertion on the "display" value
        expect(responseData.dynamicCriteria).to.eq('dynamic_edited');
      });
    });
  });

  // -------
  // -- Delete Many
  it('selects multiple display groups and delete them', function() {
    // Create a new displaygroup and then search for it and delete it
    cy.createDisplaygroup('Cypress Test Displaygroup ' + testRun).then((res) => {
      cy.intercept('GET', '/displaygroup?draw=2&*').as('displaygroupGridLoad');

      // Delete all test displaygroups
      cy.visit('/displaygroup/view');

      // Clear filter
      cy.get('#Filter input[name="displayGroup"]')
        .clear()
        .type('Cypress Test Displaygroup');

      // Wait for the grid reload
      cy.wait('@displaygroupGridLoad');

      // Select all
      cy.get('button[data-toggle="selectAll"]').click();

      // Delete all
      cy.get('.dataTables_info button[data-toggle="dropdown"]').click();
      cy.get('.dataTables_info a[data-button-id="displaygroup_button_delete"]').click();

      cy.get('input#checkbox-confirmDelete').check();
      cy.get('button.save-button').click();

      // Modal should contain one successful delete at least
      cy.get('.modal-body').contains(': Success');
    });
  });

  // ---------
  // Tests - Error handling
  it('should not add a displaygroup without dynamic criteria', function() {
    cy.visit('/displaygroup/view');

    // Click on the Add Displaygroup button
    cy.contains('Add Display Group').click();

    cy.get('.modal input#displayGroup')
        .type('Cypress Test Displaygroup ' + testRun + '_1');

    cy.get('.modal input#isDynamic').check();

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check toast message
    cy.contains('Dynamic Display Groups must have at least one Criteria specified.');
  });
});
