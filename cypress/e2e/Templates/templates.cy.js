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

describe('Template Test Suite', function () {
  let templateName = '';

  beforeEach(function () {
    cy.login();

    templateName = 'Template No. ' + Cypress._.random(0, 1e9);

    // Always intercept before visiting
    cy.intercept('GET', '**/template*').as('templatesList');
    cy.visit('/template/view');
    cy.wait('@templatesList').its('response.statusCode').should('eq', 200);

    // Open Add Template form
    cy.contains('Add Template').click();
  });

  it('Prevents saving incomplete template', function () {
    cy.get('#dialog_btn_2').should('be.visible').click();
    cy.contains('Layout Name must be between 1 and 100 characters').should('be.visible');
  });

  it('Creates a template, opens it, exits editor, searches, and deletes', function () {
    // Create template
    cy.get('#name').clear().type(templateName);
    cy.get('#dialog_btn_2').should('be.visible').click();
    cy.get('#layout-editor').should('be.visible');

    // Exit editor and verify template was created
    cy.get('#backBtn').click({ force: true });
    cy.contains('td', templateName).should('exist');

    // Reopen the newly created template
    cy.contains('td', templateName)
      .should('be.visible')
      .parents('tr')
      .within(() => {
        cy.get('div[title="Row Menu"] button.dropdown-toggle').click({
          force: true,
        });
        cy.get('a.layout_button_design').click({ force: true });
      });
    cy.get('#layout-editor').should('be.visible');

    // Exit editor and assert landing page
    cy.get('#backBtn').click({ force: true });
    cy.contains('.widget-title', 'Templates').should('be.visible');

    // Search for the template
    cy.get('#template').clear().type(templateName);
    cy.wait('@templatesList');

    // Delete the template
    cy.contains('td', templateName)
      .should('be.visible')
      .parents('tr')
      .within(() => {
        cy.get('div[title="Row Menu"] button.dropdown-toggle').click({ force: true});
        cy.get('a.layout_button_delete[data-commit-method="delete"]').click({force: true});
      });

    cy.get('#layoutDeleteForm').should('be.visible');
    cy.contains('p', 'Are you sure you want to delete this item?').should('be.visible');
    cy.contains('Yes').click({ force: true });

    // Verify deletion
    cy.contains('.dataTables_empty', 'No data available in table').should('be.visible');
  });
});

/*
 * TO DOs:
 * 1. Add "No" and "Retire" flow for delete modal
 * 2. Ensure duplicate template creation is not possible
 * 3. Layout Editor: change background, etc. -- this should not be covered here
 * 4. Search for non-existing template
 */
