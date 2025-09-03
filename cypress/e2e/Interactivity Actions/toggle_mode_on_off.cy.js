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


describe('Test IA: Toggle Mode ON/OFF', () => {
  
  beforeEach(() => {
    cy.login();

    // Navigate to Layouts page
    cy.visit('/layout/view');
    
    //Click the Add Layout button
    cy.get('button.layout-add-button').click();
    cy.get('#layout-viewer').should('be.visible');
  });

  it('should verify default status = OFF and checks the status of IA Mode when toggled to ON or OFF', () => {
    
    //check default IA Mode = OFF
    cy.get('li.nav-item.interactive-control')
    .should('have.attr', 'data-status', 'off')
    .then(($el) => {
        cy.wrap($el).click({ force: true })
    })

    //Toggle Mode = ON
    cy.get('li.nav-item.interactive-control')
    .should('have.attr', 'data-status', 'on')
    .and('contain.text', 'ON')

    //Toggle OFF back to Layout Editor
    cy.get('li.nav-item.interactive-control').click({ force: true })
    cy.get('li.nav-item.interactive-control')
    .should('have.attr', 'data-status', 'off')
    .and('contain.text', 'OFF')
  });
});