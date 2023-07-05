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

  it('should create a new layout and be redirected to the layout designer, add/delete playlist widget', function() {
    cy.visit('/layout/view');

    cy.get('button[href="/layout"]').click();

    // Open widget menu
    cy.openToolbarMenu(0);

    cy.get('[data-sub-type="playlist"]').click();
    cy.get('.viewer-element.layout.ui-droppable-active').click();

    // Check if the widget is in the viewer
    cy.get('#layout-viewer .designer-region .widget-preview[data-type="playlist"]').should('exist');
    cy.get('#layout-viewer .designer-region .widget-preview[data-type="playlist"]').parent().parent().click({multiple: true});

    cy.get('[data-sub-type="clock"]').click();

    cy.get('[data-sub-type="clock-analogue"] > .toolbar-card-thumb').click();
    cy.get('#playlist-timeline').click();

    cy.get('[data-widget-type="clock-analogue"]').rightclick();
    cy.get('[data-title="Delete"]').click();
    cy.get('.modal-footer > .btn-danger').click();

    // Close the modal
    cy.get('.bootbox.modal')
      .should('be.visible')
      .find('.modal-header')
      .find('button')
      .should('contain', '×')
      .click();
    cy.get('.modal-dialog').should('not.be.visible');

    cy.get('[data-title="Close content"]').click();
    cy.get('#backToLayoutEditorBtn').click();

    cy.get('.widget-preview>div>p').contains('Playlist with 0 Widgets');

    //
    // TODO Subplaylist
  });
});
