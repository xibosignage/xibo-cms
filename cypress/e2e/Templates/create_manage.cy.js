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
    const SELECTORS = {
        // addButton: 'XiboFormButton',
        saveTemplate: '#dialog_btn_2'
    };

    beforeEach(function() {
        cy.login();
        // cy.visit('/template/view');
              
    });

    it('Ensure users can successfully create a new template', function() {
      cy.visit('/template/view');  
      cy.contains('Add Template').click();  
        cy.get('#name').type('Lyka Test 7');
        cy.get(SELECTORS.saveTemplate).should('be.visible').click();
        cy.get('#layout-editor').should('be.visible');
        cy.get('#backToLayoutEditorBtn').click({ force: true});
        cy.visit('/template/view');
    });

    it('Validate existing template can be open and exit', function() {
      
    });
    
    // it('should display the list of Templates', function() {
    //   //Intercept any Get call to /template with query params
    //   cy.intercept('GET', '**/template*').as('displayTemplates');
    //   cy.visit('/template/view');

    //   // Wait for the API call and assert
    //   cy.wait('@displayTemplates')
    //     .its('response.statusCode')
    //     .should('eq', 200);

    //   cy.get('#template')
    //     .should('be.visible')
    //     .clear()
    //     .type('Lyka Template7: Cypress Test{enter}');

    //   cy.wait('@displayTemplates');

    //   cy.contains('Lyka Template7: Cypress Test', { timeout: 10000 })
    //     .should('be.visible')      // make sure row is visible
    //     .parents('tr')
    //     .find('div[title="Row Menu"] button.dropdown-toggle')
    //     .click({ force: true });
      
    //   cy.get('a.layout_button_delete[data-commit-method="delete"]')
    //     .click({ force: true })
        
    //   cy.get('#layoutDeleteForm')
    //     cy.contains('p', 'Are you sure you want to delete this item?')
    //   cy.contains('Yes').click({ force: true });

    //   // Choose Yes or No dynamically
    //   const confirmDelete = false; // set to false to test "No"

    //   if (confirmDelete) {
    //     cy.get('#dialog_btn_1').click({ force: true });
    //     cy.contains('Lyka Template7: Cypress Test').should('not.exist'); // deleted
    //   } else {
    //     cy.get('#dialog_btn_2').click({ force: true });
    //     cy.get('#layoutDeleteForm').should('not.exist');
    //     cy.contains('Lyka Template7: Cypress Test').should('exist');     // still there
    //   }
    // });


  

    // it('Prevent template creation without required fields', function() {
    //     cy.get(SELECTORS.saveTemplate).should('be.visible').click()
    //     cy.contains('Layout Name must be between 1 and 100 characters');
    // });

    

    // it('Search and Deletes the existing Template', function() {
    //     cy.get('#template').should('be.visible').click().type('Lyka Template7: Cypress Test');
    //     // cy.contains('Lyka Template7: Cypress Test')
    //     cy.get('#layout_button_design').click()
    //     cy.contains('Delete').click();
    //     cy.contains('Yes').click();
    // });
});