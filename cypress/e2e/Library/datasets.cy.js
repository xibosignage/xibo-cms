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
describe('Datasets', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should add one empty dataset', function() {
    cy.visit('/dataset/view');

    // Click on the Add Dataset button
    cy.contains('Add DataSet').click();

    cy.get('.modal input#dataSet')
      .type('Cypress Test Dataset ' + testRun + '_1');

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if dataset is added in toast message
    cy.contains('Added Cypress Test Dataset ' + testRun + '_1');
  });

  it('searches and edit existing dataset', function() {
    // Create a new dataset and then search for it and delete it
    cy.createDataset('Cypress Test Dataset ' + testRun).then((id) => {
      cy.intercept({
        url: '/dataset?*',
        query: {dataSet: 'Cypress Test Dataset ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'PUT',
        url: '/dataset/*',
      }).as('putRequest');

      cy.visit('/dataset/view');

      // Filter for the created dataset
      cy.get('#Filter input[name="dataSet"]')
        .type('Cypress Test Dataset ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#datasets tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#datasets tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#datasets tr:first-child .dataset_button_edit').click({force: true});

      cy.get('.modal input#dataSet').clear()
        .type('Cypress Test Dataset Edited ' + testRun);

      // edit test dataset
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@putRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;

        // assertion on the "dataset" value
        expect(responseData.dataSet).to.eq('Cypress Test Dataset Edited ' + testRun);
      });

      // Delete the dataset and assert success
      cy.deleteDataset(id).then((res) => {
        expect(res.status).to.equal(204);
      });
    });
  });

  it('add row/column to an existing dataset', function() {
    // Create a new dataset and then search for it and delete it
    cy.createDataset('Cypress Test Dataset ' + testRun).then((id) => {
      cy.intercept({
        url: '/dataset?*',
        query: {dataSet: 'Cypress Test Dataset ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'POST',
        url: /\/dataset\/\d+\/column$/,
      }).as('postRequestAddColumn');

      cy.intercept({
        method: 'POST',
        url: /\/dataset\/data\/\d+/,
      }).as('postRequestAddRow');

      cy.visit('/dataset/view');

      // Filter for the created dataset
      cy.get('#Filter input[name="dataSet"]')
        .type('Cypress Test Dataset ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#datasets tbody tr').should('have.length', 1);

      // Click on the first row element to open the View data
      cy.get('#datasets tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#datasets tr:first-child .dataset_button_viewcolumns').click({force: true});

      cy.get('#datasets').contains('No data available in table');

      // Add data row to dataset
      cy.contains('Add Column').click();
      cy.get('.modal input#heading').type('Col1');

      // Save
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@postRequestAddColumn').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;

        // assertion on the "dataset" value
        expect(responseData.heading).to.eq('Col1');

        cy.contains('View Data').click();
        cy.get('#datasets').contains('No data available in table');

        // Add data row to dataset
        cy.contains('Add Row').click();
        cy.get('#dataSetDataAdd').within(() => {
          cy.get('input:first').type('Your text goes here');
        });

        // Save
        cy.get('.bootbox .save-button').click();

        // Wait for the intercepted request and check data
        cy.wait('@postRequestAddRow').then((interception) => {
          cy.contains('Added Row');
        });
      });

      // Now try to delete the dataset
      cy.visit('/dataset/view');

      // Filter for the created dataset
      cy.get('#Filter input[name="dataSet"]')
        .type('Cypress Test Dataset ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#datasets tbody tr').should('have.length', 1);

      // Click on the first row element to open the View data
      cy.get('#datasets tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#datasets tr:first-child .dataset_button_delete').click({force: true});
    });
  });

  it('copy an existing dataset', function() {
    // Create a new dataset and then search for it and copy it
    cy.createDataset('Cypress Test Dataset ' + testRun).then((res) => {
      cy.intercept({
        url: '/dataset?*',
        query: {dataSet: 'Cypress Test Dataset ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the POST request
      cy.intercept({
        method: 'POST',
        url: /\/dataset\/copy\/\d+/,
      }).as('postRequest');

      cy.visit('/dataset/view');

      // Filter for the created dataset
      cy.get('#Filter input[name="dataSet"]')
        .type('Cypress Test Dataset ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#datasets tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#datasets tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#datasets tr:first-child .dataset_button_copy').click({force: true});

      // save
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted POST request and check the form data
      cy.wait('@postRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;
        expect(responseData.dataSet).to.include('Cypress Test Dataset ' + testRun + ' 2');
      });
    });
  });

  it('searches and delete existing dataset', function() {
    // Create a new dataset and then search for it and delete it
    cy.createDataset('Cypress Test Dataset ' + testRun).then((res) => {
      cy.intercept('GET', '/dataset?draw=2&*').as('datasetGridLoad');

      cy.visit('/dataset/view');

      // Filter for the created dataset
      cy.get('#Filter input[name="dataSet"]')
        .type('Cypress Test Dataset ' + testRun);

      // Wait for the grid reload
      cy.wait('@datasetGridLoad');
      cy.get('#datasets tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#datasets tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#datasets tr:first-child .dataset_button_delete').click({force: true});

      // Delete test dataset
      cy.get('.bootbox .save-button').click();

      // Check if dataset is deleted in toast message
      cy.get('.toast').contains('Deleted Cypress Test Dataset');
    });
  });

  it('selects multiple datasets and delete them', function() {
    // Create a new dataset and then search for it and delete it
    cy.createDataset('Cypress Test Dataset ' + testRun).then((res) => {
      cy.intercept('GET', '/dataset?draw=2&*').as('datasetGridLoad');

      // Delete all test datasets
      cy.visit('/dataset/view');

      // Clear filter
      cy.get('#Filter input[name="dataSet"]')
        .clear()
        .type('Cypress Test Dataset');

      // Wait for the grid reload
      cy.wait('@datasetGridLoad');

      // Select all
      cy.get('button[data-toggle="selectAll"]').click();

      // Delete all
      cy.get('.dataTables_info button[data-toggle="dropdown"]').click();
      cy.get('.dataTables_info a[data-button-id="dataset_button_delete"]').click();

      cy.get('input#deleteData').check();
      cy.get('button.save-button').click();

      // Modal should contain one successful delete at least
      cy.get('.modal-body').contains(': Success');
    });
  });

  // ---------
  // Tests - Error handling
  it.only('should not add a remote dataset without URI', function() {
    cy.visit('/dataset/view');

    // Click on the Add Dataset button
    cy.contains('Add DataSet').click();

    cy.get('.modal input#dataSet')
      .type('Cypress Test Dataset ' + testRun);

    cy.get('.modal input#isRemote').check();

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Click on the "Remote" tab
    cy.get(':nth-child(2) > .nav-link').should('be.visible').click();

    // Check that the error message is displayed for the missing URI field
    cy.get('#uri-error').should('have.text', 'This field is required.');
  });
});
