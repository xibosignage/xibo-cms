/*
 * Copyright (C) 2025 Xibo Signage Ltd
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

describe('Template creation and management', function() {
  let templateName = '';

  beforeEach(function() {
    cy.login();

    templateName = 'Template No. ' + Cypress._.random(0, 1e9);

    // Always intercept before visiting
    cy.intercept('GET', '**/template*').as('templatesList');
    cy.visit('/template/view');
    cy.wait('@templatesList').its('response.statusCode').should('eq', 200);
  });

  it('Allows user to create, open, and delete a template', function() {
    // Try to save without filling fields
    cy.contains('Add Template').click();
    cy.get('#dialog_btn_2').should('be.visible').click();
    cy.contains('Layout Name must be between 1 and 100 characters').should('be.visible');

    // Fill required fields and save
    cy.get('#name').clear().type(templateName);
    cy.get('#dialog_btn_2').should('be.visible').click();
    cy.get('#layout-editor', { timeout: 10000 }).should('be.visible');

    // Exit back to templates
    cy.get('#backBtn').click({ force: true });
    cy.contains('.widget-title', 'Templates').should('be.visible');
    cy.wait('@templatesList').its('response.statusCode').should('eq', 200);

    // Search for the template
    cy.get('#template').clear().type(templateName);
    cy.wait('@templatesList');

    // Open the newly created template
    cy.contains('td', templateName, { timeout: 10000 })
      .should('be.visible')
      .parents('tr')
      .within(() => {
        cy.get('div[title="Row Menu"] button.dropdown-toggle').click({ force: true });
        cy.get('a.layout_button_design').click({ force: true });
      });
    cy.get('#layout-editor', { timeout: 10000 }).should('be.visible');

    // Exit again
    cy.get('#backBtn').click({ force: true });
    cy.contains('.widget-title', 'Templates').should('be.visible');
    cy.wait('@templatesList').its('response.statusCode').should('eq', 200);

    // Delete the template
    cy.contains('td', templateName, { timeout: 10000 })
      .should('be.visible')
      .parents('tr')
      .within(() => {
        cy.get('div[title="Row Menu"] button.dropdown-toggle').click({ force: true });
        cy.get('a.layout_button_delete[data-commit-method="delete"]').click({ force: true });
      });

    cy.get('#layoutDeleteForm').should('be.visible');
    cy.contains('p', 'Are you sure you want to delete this item?').should('be.visible');
    cy.contains('Yes').click({ force: true });

    // Verify deletion
    cy.contains('.dataTables_empty', 'No data available in table');
  });
});


/*
 * TO DOs:
 * 1. Add "No" and "Retire" flow for delete modal
 * 2. Ensure duplicate template creation is not possible
 * 3. Layout Editor: change background, etc. -- this should not be covered here
 * 4. Search for non-existing template
 */




   // it('Visits the Template page and loads all existing Templates', function() {
  //   cy.intercept('GET', '**/template*').as('templatesList');
  //   cy.visit('/template/view');
  //   cy.wait('@templatesList').its('response.statusCode').should('eq', 200);
  // });

  // it('Prevents template creation without required fields', function() {
  //   cy.visit('/template/view');
  //   cy.contains('Add Template').click();  
  //   cy.get('#dialog_btn_2').should('be.visible').click(); // Click the SAVE button without input of required fields
  //   cy.contains('Layout Name must be between 1 and 100 characters'); // Assert: It should not be valid and shows error message
  // });


  // it('Opens an existing template + Exits the editor', function() {
  //   cy.intercept('GET', '**/template*').as('templatesList');
  //   cy.visit('/template/view');
  //   cy.wait('@templatesList').its('response.statusCode').should('eq', 200);

  //   //Choose the template to be opened
  //   cy.contains(testName, { timeout: 10000 })
  //     .should('be.visible')
  //     .parents('tr')
  //     .within(() => { // This is the row menu specific only to the selected template
  //       cy.get('div[title="Row Menu"] button.dropdown-toggle').click({ force: true });
  //       cy.get('a.layout_button_design').click({ force: true });
  //     });

  //   cy.get('#layout-editor', { timeout: 10000 }).should('be.visible'); // Assert: Editor opens
  //   cy.get('#backToLayoutEditorBtn').click({ force: true }); // Click on the Exit button to go back Template page
  //   cy.visit('/template/view'); // Assert: Template page is visible
  // });

  // it('Searches and deletes a template', function() {
  //   cy.intercept('GET', '**/template*').as('templatesList');
  //   cy.visit('/template/view');
  //   cy.wait('@templatesList').its('response.statusCode').should('eq', 200);

  //   cy.get('#template')
  //     .should('be.visible')
  //     .clear()
  //     .type(templateName);

  //   cy.wait('@templatesList');

  //   cy.contains(templateName, { timeout: 10000 })
  //     .should('be.visible')     
  //     .parents('tr')
  //     .within(() => {
  //       cy.get('div[title="Row Menu"] button.dropdown-toggle').click({ force: true });
  //       cy.get('a.layout_button_delete[data-commit-method="delete"]').click({ force: true });
  //     });

  //   cy.get('#layoutDeleteForm').should('be.visible');
  //   cy.contains('p', 'Are you sure you want to delete this item?').should('be.visible');
  //   cy.contains('Yes').click({ force: true });

  //   cy.contains('td.dtr-control', templateName).should('not.exist');
  // });
