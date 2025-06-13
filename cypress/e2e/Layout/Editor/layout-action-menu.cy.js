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

/* eslint-disable max-len */
describe('Layout Editor Toolbar (Back button, Interactive Mode, Layout jump list)', () => {
  beforeEach(function() {
    cy.login();

    cy.intercept('GET', '/user/pref?preference=toolbar').as('toolbarPrefsLoad');
    cy.intercept('GET', '/user/pref?preference=editor').as('editorPrefsLoad');

    cy.visit('/layout/view');
    cy.get('button.layout-add-button').click();
    cy.get('#layout-viewer').should('be.visible');
    cy.wait('@toolbarPrefsLoad');
    cy.wait('@editorPrefsLoad');
  });

  it('Back button should be present and navigate correctly', () => {
    cy.get('#backBtn')
      .should('have.class', 'btn btn-lg')
      .and('have.attr', 'href', '/layout/view')
      .click({force: true});
    cy.url().should('include', '/layout/view');
  });

  it('should display Interactive Mode with OFF status initially', () => { // done
    cy.get('li.interactive-control')
      .should('have.attr', 'data-status', 'off')
      .within(() => {
        cy.contains('.interactive-control-label', 'Interactive Mode');
        cy.get('.interactive-control-status-off').should('be.visible').and('contain.text', 'OFF');
        cy.get('.interactive-control-status-on').should('not.be.visible');
      });
  });

  it('should toggle Interactive Mode status on click', () => { // done
    cy.get('li.nav-item.interactive-control[data-status="off"]')
      .should(($el) => {
        expect($el).to.exist;
        expect($el).to.be.visible;
      })
      .click({force: true});
    cy.get('.interactive-control-status-off').should('not.be.visible');
  });

  it('should open and close the layout jump list dropdown safely', () => { // err
    cy.get('#select2-layoutJumpList-container').should('be.visible')
      .and('exist');
    cy.get('#layoutJumpListContainer .select2-selection')
      .should('be.visible')
      .and('exist')
      .click();
  });

  it('Options dropdown menu toggles and contains expected items', () => {
    cy.get('#optionsContainerTop')
      .should('exist')
      .should('be.visible')
      .click();

    cy.get('.navbar-submenu-options-container').should('be.visible');

    cy.get('#publishLayout').should('be.visible');
    cy.get('#checkoutLayout').should('have.class', 'd-none');
    cy.get('#discardLayout').should('be.visible');
    cy.get('#newLayout').should('be.visible');
    cy.get('#deleteLayout').should('have.class', 'd-none');
    cy.get('#saveTemplate').should('have.class', 'd-none');
    cy.get('#scheduleLayout').should('have.class', 'd-none');
    cy.get('#clearLayout').should('be.visible');

    cy.get('#displayTooltips').should('be.checked');
    cy.get('#deleteConfirmation').should('be.checked');

    // Close dropdown for cleanup
    cy.get('#optionsContainerTop').click();
  });

  it('Tooltips and popovers appear on hover', () => {
    // Tooltip
    cy.get('.layout-info-name')
      .should('exist')
      .should('be.visible')
      .trigger('mouseover');
    cy.get('.tooltip').should('be.visible');
    cy.get('.layout-info-name')
      .should('exist')
      .should('be.visible')
      .trigger('mouseout');

    // Popover
    cy.get('#layout-info-status')
      .should('exist')
      .should('be.visible')
      .trigger('mouseover');
    cy.get('.popover').should('be.visible');
    cy.get('#layout-info-status')
      .should('exist')
      .should('be.visible')
      .trigger('mouseout');
  });
});
