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

describe('Modules Page', function () {
    beforeEach(function () {
        cy.login();
    });

    it.skip('should load the modules page and show a complete table of modules', function () {

        cy.visit('/module/view');

        cy.contains('Modules');

        // Click on the first page of the pagination
        cy.get('.pagination > :nth-child(2) > a').click();

        cy.contains('Showing 1 to');
    });
});