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

  beforeEach(function() {
    cy.login();
  });

  it('Visits the Template page and loads all existing Templates', function() {
    cy.intercept('GET', '**/template*').as('templatesList');
    cy.visit('/template/view');
    cy.wait('@templatesList').its('response.statusCode').should('eq', 200);
  });

  it('Prevents template creation without required fields', function() {
    cy.visit('/template/view');
    cy.contains('Add Template').click();  
    cy.get('#dialog_btn_2').should('be.visible').click();
    cy.contains('Layout Name must be between 1 and 100 characters');
  });

  it('Creates a new template and exits layout editor', function() {
    cy.visit('/template/view');  
    cy.contains('Add Template').click();  
    cy.get('#name').type('Santee');
    cy.get('#dialog_btn_2').should('be.visible').click();
    cy.get('#layout-editor').should('be.visible');
  });

  it('Opens and edits an existing template', function() {
    cy.intercept('GET', '**/template*').as('templatesList');
    cy.visit('/template/view');
    cy.wait('@templatesList').its('response.statusCode').should('eq', 200);

    cy.contains('Santee', { timeout: 10000 })
      .should('be.visible')
      .parents('tr')
      .within(() => {
        cy.get('div[title="Row Menu"] button.dropdown-toggle').click({ force: true });
        cy.get('a.layout_button_design').click({ force: true });
      });

    cy.get('#layout-editor', { timeout: 10000 }).should('be.visible');
    cy.get('#backToLayoutEditorBtn').click({ force: true });
    cy.visit('/template/view');
  });

  it('Searches and deletes a template', function() {
    cy.intercept('GET', '**/template*').as('templatesList');
    cy.visit('/template/view');
    cy.wait('@templatesList').its('response.statusCode').should('eq', 200);

    cy.get('#template')
      .should('be.visible')
      .clear()
      .type('Santee{enter}');

    cy.wait('@templatesList');

    cy.contains('Santee', { timeout: 10000 })
      .should('be.visible')     
      .parents('tr')
      .within(() => {
        cy.get('div[title="Row Menu"] button.dropdown-toggle').click({ force: true });
        cy.get('a.layout_button_delete[data-commit-method="delete"]').click({ force: true });
      });

    cy.get('#layoutDeleteForm').should('be.visible');
    cy.contains('p', 'Are you sure you want to delete this item?').should('be.visible');
    cy.contains('Yes').click({ force: true });

    cy.contains('td.dtr-control', 'Santee').should('not.exist');
  });

});

/*
 * TO DOs:
 * 1. Add "No" flow for delete modal
 * 2. Ensure duplicate template creation is not possible
 */
