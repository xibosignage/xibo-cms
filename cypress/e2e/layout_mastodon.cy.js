/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
describe('Layout Designer', function() {
  beforeEach(function() {
    cy.login();
  });

  it('should create a new layout and be redirected to the layout designer, add/delete Mastodon widget', function() {
    cy.visit('/layout/view');

    cy.get('button[href="/layout"]').click();

    // Open widget menu
    cy.openToolbarMenu(0);

    cy.get('[data-sub-type="mastodon"]').click();

    cy.get('[data-template-id="social_media_static_1"] > .toolbar-card-thumb').click();

    cy.get('.viewer-object.layout.ui-droppable-active').click();

    // Check if the widget is in the viewer
    cy.get('#layout-viewer .designer-region .widget-preview[data-type="widget_mastodon"]').should('exist');

    cy.get('[name="hashtag"]').clear();
    cy.get('[name="hashtag"]').type('#cat');
    cy.get('[name="searchOn"]').select('local', {force: true});
    cy.get('[name="numItems"]').clear().type('10').trigger('change');
    cy.get('[name="onlyMedia"]').check();

    // Click on Appearance Tab
    cy.get('.nav-link[href="#appearanceTab"]').click();
    cy.get('[name="itemsPerPage"]').clear().type('2').trigger('change');

    // Vertical/Fade/100/Right/Bottom
    cy.get('[name="displayDirection"]').select('Vertical', {force: true});
    cy.get('[name="effect"]').select('Fade', {force: true});
    cy.get('[name="speed"]').clear().type('100').trigger('change');
    cy.get('[name="alignmentH"]').select('Right', {force: true});
    cy.get('[name="alignmentV"]').select('Bottom', {force: true});

    cy.get('#layout-viewer .designer-region .widget-preview[data-type="widget_mastodon"]').parents('.designer-region').rightclick();
    cy.get('[data-title="Delete"]').click();
    cy.get('#layout-viewer .designer-region .widget-preview[data-type="widget_mastodon"]').should('not.exist');
  });
});
