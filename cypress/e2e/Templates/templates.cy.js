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

  // create template flow
  function createTemplate(name) {
    cy.visit('/template/view');
    cy.contains('Add Template').click();
    cy.get('#name').clear().type(name);
    cy.get('#dialog_btn_2').should('be.visible').click();
    cy.get('#layout-editor').should('be.visible');
    cy.get('#backBtn').click({ force: true });
  }

  // delete template flow
  function deleteATemplate(name) {
    cy.get('div[title="Row Menu"] button.dropdown-toggle').click({ force: true });
    cy.get('a.layout_button_delete[data-commit-method="delete"]').click({ force: true });

    cy.get('#layoutDeleteForm').should('be.visible');
    cy.contains('p', 'Are you sure you want to delete this item?').should('be.visible');
  }

  beforeEach(function () {
    cy.login();
    templateName = 'Template No. ' + Cypress._.random(0, 1e9);
  });

  // Display Template List
  it('should display the template list', function () {
    cy.intercept('GET', '**/template*').as('templateList');
    cy.visit('/template/view');
    cy.wait('@templateList').its('response.statusCode').should('eq', 200);
  });

  // Save Incomplete Form
  it('should prevent saving incomplete template', function () {
    cy.visit('/template/view');
    cy.contains('Add Template').click();
    cy.get('#dialog_btn_2').should('be.visible').click();
    cy.contains('Layout Name must be between 1 and 100 characters').should('be.visible');
  });

  // Create a Template
  it('should create a template', function () {
    createTemplate(templateName);
    cy.contains('td', templateName).should('be.visible');
  });

  // Duplicate Template
  it('should not allow duplicate template name', function () {
    createTemplate(templateName);

    cy.contains('Add Template').click();
    cy.get('#name').clear().type(templateName);
    cy.get('#dialog_btn_2').should('be.visible').click();

    cy.get('.modal-footer .form-error')
      .contains(`You already own a Layout called '${templateName}'. Please choose another name.`)
      .should('be.visible');
  });

  // Search and Delete a template
  it('should search template and delete it', function () {
    cy.intercept({
      url: '/template?*',
      query: { template: templateName },
    }).as('displayTemplateAfterSearch');

    createTemplate(templateName);

    cy.get('#template').clear().type(templateName);
    cy.wait('@displayTemplateAfterSearch');
    cy.get('table tbody tr').should('have.length', 1);
    cy.get('#templates tbody tr:nth-child(1) td:nth-child(1)').contains(templateName);

    cy.get('#templates tbody tr')
      .should('have.length', 1)
      .first()
      .should('contain.text', templateName);

    // delete template = no
    deleteATemplate(templateName);
    cy.get('#dialog_btn_2').click({ force: true });
    cy.contains(templateName).should('be.visible');

    // delete template = yes
    deleteATemplate(templateName);
    cy.get('#dialog_btn_3').click({ force: true });
    cy.get('#toast-container .toast-message').contains(`Deleted ${templateName}`).should('be.visible');
    cy.contains(templateName).should('not.exist');
  });

  // Multiple deleting of templates
  it('should delete multiple templates', function () {
    createTemplate(templateName);

    cy.get('button[data-toggle="selectAll"]').click();
    cy.get('.dataTables_info button[data-toggle="dropdown"]').click();
    cy.get('a[data-button-id="layout_button_delete"]').click();

    cy.get('.modal-footer').contains('Save').click();
    cy.get('.modal-body').contains(': Success');
    cy.get('.modal-footer').contains('Close').click();
    cy.contains('.dataTables_empty', 'No data available in table').should('be.visible');
  });

  // Search for non-existing template
  it('should not return any entry for non-existing template', function () {
    cy.visit('/template/view');
    cy.get('#template').clear().type('This is a hardcoded template name just to make sure it doesnt exist in the record');
    cy.contains('.dataTables_empty', 'No data available in table').should('be.visible');
  });

});
