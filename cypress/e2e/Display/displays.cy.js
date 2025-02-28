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
describe('Displays', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  // Seeded displays: disp1, disp2, disp3, disp4, disp5
  // Seeded display Groups: disp5_dispgrp
  // Seeded layouts: disp4_default_layout
  it('searches and edit existing display', function() {
    // search for a display disp1 and edit
    cy.intercept({
      url: '/display?*',
      query: {display: 'dis_disp1'},
    }).as('loadGridAfterSearch');

    // Intercept the PUT request
    cy.intercept({
      method: 'PUT',
      url: '/display/*',
    }).as('putRequest');

    cy.visit('/display/view');

    // Filter for the created display
    cy.get('#Filter input[name="display"]')
      .type('dis_disp1');

    // Wait for the grid reload
    cy.wait('@loadGridAfterSearch');
    cy.get('#displays tbody tr').should('have.length', 1);

    // Click on the first row element to open the delete modal
    cy.get('#displays tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#displays tr:first-child .display_button_edit').click({force: true});

    cy.get('.modal input#display').clear()
      .type('dis_disp1 Edited');

    cy.get('.modal input#license').clear()
      .type('dis_disp1_license');

    cy.get('.modal input#description').clear()
      .type('description');

    // edit test display
    cy.get('.bootbox .save-button').click();

    // Wait for the intercepted PUT request and check the form data
    cy.wait('@putRequest').then((interception) => {
      // Get the request body (form data)
      const response = interception.response;
      const responseData = response.body.data;

      // assertion on the "display" value
      expect(responseData.display).to.eq('dis_disp1 Edited');
      expect(responseData.description).to.eq('description');
      expect(responseData.license).to.eq('dis_disp1_license');
    });
  });

  // Display: disp2
  it('searches and delete existing display', function() {
    cy.intercept({
      url: '/display?*',
      query: {display: 'dis_disp2'},
    }).as('loadGridAfterSearch');

    cy.visit('/display/view');

    // Filter for the created display
    cy.get('#Filter input[name="display"]')
      .type('dis_disp2');

    // Wait for the grid reload
    cy.wait('@loadGridAfterSearch');
    cy.get('#displays tbody tr').should('have.length', 1);

    // Click on the first row element to open the delete modal
    cy.get('#displays tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#displays tr:first-child .display_button_delete').click({force: true});

    // Delete test display
    cy.get('.bootbox .save-button').click();

    // Check if display is deleted in toast message
    cy.get('.toast').contains('Deleted dis_disp2');
  });

  // Display: disp3
  it('searches and authorise an unauthorised display', function() {
    // search for a display disp1 and edit
    cy.intercept({
      url: '/display?*',
      query: {display: 'dis_disp3'},
    }).as('loadGridAfterSearch');

    // Intercept the PUT request
    cy.intercept({
      method: 'PUT',
      url: '/display/authorise/*',
    }).as('putRequest');

    cy.visit('/display/view');

    // Filter for the created display
    cy.get('#Filter input[name="display"]')
      .type('dis_disp3');

    // Wait for the grid reload
    cy.wait('@loadGridAfterSearch');
    cy.get('#displays tbody tr').should('have.length', 1);

    // Click on the first row element to open the delete modal
    cy.get('#displays tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#displays tr:first-child .display_button_authorise').click({force: true});

    // edit test display
    cy.get('.bootbox .save-button').click();

    // Wait for the intercepted PUT request and check the form data
    cy.wait('@putRequest').then((interception) => {
      // Get the request body (form data)
      const response = interception.response;
      // assertion
      expect(response.body.message).to.eq('Authorised set to 1 for dis_disp3');
    });
  });

  // Display: disp4
  it('set a default layout', function() {
    cy.intercept({
      url: '/display?*',
      query: {display: 'dis_disp4'},
    }).as('loadGridAfterSearch');

    // Intercept the PUT request
    cy.intercept({
      method: 'PUT',
      url: '/display/defaultlayout/*',
    }).as('putRequest');

    cy.intercept({
      url: '/layout*',
      query: {
        layout: 'disp4_default_layout',
      },
    }).as('loadLayoutAfterSearch');

    cy.visit('/display/view');

    // Filter for the created display
    cy.get('#Filter input[name="display"]')
      .type('dis_disp4');

    // Wait for the grid reload
    cy.wait('@loadGridAfterSearch');
    cy.get('#displays tbody tr').should('have.length', 1);

    // Click on the first row element to open the delete modal
    cy.get('#displays tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#displays tr:first-child .display_button_defaultlayout').click({force: true});

    // Set the default layout
    cy.get('.modal .select2-container--bootstrap').click();
    cy.get('.select2-search__field').type('disp4_default_layout');

    cy.wait('@loadLayoutAfterSearch');
    cy.get('.select2-results__option').contains('disp4_default_layout').click();

    // edit test display
    cy.get('.bootbox .save-button').click();

    // Wait for the intercepted PUT request and check the form data
    cy.wait('@putRequest').then((interception) => {
      // Get the request body (form data)
      const response = interception.response;
      const body = response.body;
      expect(body.success).to.eq(true);
    });
  });

  // Display: disp5
  it('manage membership for disp5', function() {
    cy.intercept({
      url: '/display?*',
      query: {display: 'dis_disp5'},
    }).as('loadGridAfterSearch');

    // Intercept the PUT request
    cy.intercept({
      method: 'POST',
      url: /\/display\/\d+\/displaygroup\/assign$/,
    }).as('postRequest');

    cy.intercept({
      url: '/displaygroup*',
      query: {
        displayGroup: 'disp5_dispgrp',
      },
    }).as('loadDisplaypGroupAfterSearch');

    cy.visit('/display/view');

    // Filter for the created display
    cy.get('#Filter input[name="display"]')
      .type('dis_disp5');

    // Wait for the grid reload
    cy.wait('@loadGridAfterSearch');
    cy.get('#displays tbody tr').should('have.length', 1);

    // Click on the first row element to open the delete modal
    cy.get('#displays tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#displays tr:first-child .display_button_group_membership').click({force: true});

    cy.get('.modal #displayGroup').type('disp5_dispgrp');

    cy.wait('@loadDisplaypGroupAfterSearch');
    cy.get('#displaysGroupsMembersTable').within(() => {
      // count the rows within table
      cy.get('tbody').find('tr')
        .should('have.length', 1)
        .and('contain', 'disp5_dispgrp');
      cy.get('tbody tr:first-child input[type="checkbox"]')
        .should('not.be.checked')
        .check();
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

  it('should display map and revert back to table', function() {
    cy.intercept('GET', '/user/pref?preference=displayGrid').as('displayPrefsLoad');
    cy.intercept('GET', '/display?draw=2*').as('displayLoad');
    cy.intercept('POST', '/user/pref').as('userPrefPost');

    cy.visit('/display/view');

    cy.wait('@displayPrefsLoad');
    cy.wait('@displayLoad');
    cy.wait('@userPrefPost');

    cy.get('#map_button').click();

    cy.get('#display-map.leaflet-container').should('be.visible');

    cy.get('#list_button').click();

    cy.get('#displays_wrapper.dataTables_wrapper').should('be.visible');
  });

  // ---------
  // Tests - Error handling
  it('should not be able to save while editing existing display with incorrect latitude/longitude', function() {
    // search for a display disp1 and edit
    cy.intercept({
      url: '/display?*',
      query: {display: 'dis_disp1'},
    }).as('loadGridAfterSearch');

    // Intercept the PUT request
    cy.intercept({
      method: 'PUT',
      url: '/display/*',
    }).as('putRequest');

    cy.visit('/display/view');

    // Filter for the created display
    cy.get('#Filter input[name="display"]')
        .type('dis_disp1');

    // Wait for the grid reload
    cy.wait('@loadGridAfterSearch');
    cy.get('#displays tbody tr').should('have.length', 1);

    // Click on the first row element to open the delete modal
    cy.get('#displays tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#displays tr:first-child .display_button_edit').click({force: true});
    cy.contains('Details').click();

    cy.get('.modal input#latitude').type('1234');

    // edit test display
    cy.get('.bootbox .save-button').click();

    // Check error message
    cy.contains('The latitude entered is not valid.');

    cy.get('.modal input#latitude').clear();
    cy.get('.modal input#longitude').type('1234');

    // edit test display
    cy.get('.bootbox .save-button').click();

    // Check error message
    cy.contains('The longitude entered is not valid.');
  });
});
