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
        cy.visit('/template/view');
              
    });

    // it('Prevent template creation without required fields', function() {
    //     cy.get(SELECTORS.saveTemplate).should('be.visible').click()
    //     cy.contains('Layout Name must be between 1 and 100 characters');
    // });

    // it('Ensure users can successfully create a new template', function() {
    //     cy.contains('Add Template').click();  
    //     cy.get('#name').type('Lyka Template7: Cypress Test');
    //     cy.get(SELECTORS.saveTemplate).should('be.visible').click();
    //     cy.get('#layout-editor').should('be.visible');
    //     // cy.get('#backBtn').click(); // click on Exit button
    //     // cy.contains('Add Template').click();      
    // });

    it('Search and Deletes the existing Template', function() {
        cy.get('#template').should('be.visible').click().type('Lyka Template7: Cypress Test');
        // cy.contains('Lyka Template7: Cypress Test')
        cy.get('#layout_button_design').click()
        cy.contains('Delete').click();
        cy.contains('Yes').click();
    });
});