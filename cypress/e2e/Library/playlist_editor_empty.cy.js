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

describe('Playlist Editor (Empty)', function() {

    beforeEach(function() {
        cy.login();

        // Create random name
        let uuid = Cypress._.random(0, 1e9);

        // Create a new layout and go to the layout's designer page
        cy.createNonDynamicPlaylist(uuid).as('testPlaylistId').then((res) => {
            cy.openPlaylistEditorAndLoadPrefs(res);
        });
    });

    it('should show the droppable zone and toolbar', function() {

        cy.get('#playlist-editor-container').should('be.visible');
        cy.get('div[class="container-toolbar container-fluid flex-column flex-column justify-content-between"]').should('be.visible');
    });
});