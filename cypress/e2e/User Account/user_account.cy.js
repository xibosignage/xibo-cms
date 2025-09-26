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

describe('User Account Test Suite', function() {

  beforeEach(function () {
    cy.login();
    cy.visit('/statusdashboard');
  });

  it('navigates to edit profile', function() {
    cy.url().should('include', 'dashboard');
    cy.get('img.nav-avatar').should('be.visible');
    cy.get('#navbarUserMenu').click();
    cy.get('div[aria-labelledby="navbarUserMenu"]')
      .should('be.visible')
      .contains('Edit Profile');
  });

  it('verifies all menu items are present and in order', function() {
    cy.get('#navbarUserMenu').click();
    cy.get('div[aria-labelledby="navbarUserMenu"] a')
      .should('have.length', 6)
      .then($items => {
        const texts = [...$items].map(el => el.innerText.trim());
        expect(texts).to.deep.equal([
          'Preferences',
          'Edit Profile',
          'My Applications',
          'Reshow welcome',
          'About',
          'Logout'
        ]);
      });
  });

  it('validates edit profile', function() {
    cy.get('#navbarUserMenu').click();
    cy.get('div[aria-labelledby="navbarUserMenu"]')
      .contains('Edit Profile')
      .click();

    cy.get('.modal-content').should('be.visible');
    cy.contains('label', 'User Name').should('be.visible');
    cy.contains('label', 'Password').should('be.visible');
    cy.contains('label', 'New Password').should('be.visible');
    cy.contains('label', 'Retype New Password').should('be.visible');
    cy.contains('label', 'Email').should('be.visible');
    cy.contains('label', 'Two Factor Authentication').should('be.visible');

    // Ensure 2FA defaults to Off
    cy.get('#twoFactorTypeId')
      .should('be.visible')
      .find('option:selected')
      .should('have.text', 'Off');
  });

});