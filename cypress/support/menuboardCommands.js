/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add("login", (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add("drag", { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add("dismiss", { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This is will overwrite an existing command --
// Cypress.Commands.overwrite("visit", (originalFn, url, options) => { ... })
/* eslint-disable max-len */
// Menuboard
Cypress.Commands.add('createMenuboard', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/menuboard',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
    },
  }).then((res) => {
    return res.body.menuId;
  });
});

Cypress.Commands.add('createMenuboardCat', function(name, menuId) {
  cy.request({
    method: 'POST',
    url: '/api/menuboard/' + menuId + '/' + 'category',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
    },
  }).then((res) => {
    return res.body.menuCategoryId;
  });
});

Cypress.Commands.add('createMenuboardCatProd', function(name, menuCatId) {
  cy.request({
    method: 'POST',
    url: '/api/menuboard/' + menuCatId + '/' + 'product',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
    },
  }).then((res) => {
    return res.body.menuProductId;
  });
});

Cypress.Commands.add('deleteMenuboard', function(id) {
  cy.request({
    method: 'DELETE',
    url: '/api/menuboard/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {},
  }).then((res) => {
    return res;
  });
});