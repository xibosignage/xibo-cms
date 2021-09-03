describe('Layout Designer (Populated)', function() {

    beforeEach(function() {
        cy.login();

        // Import existing
        cy.importLayout('../assets/export_test_layout.zip').as('testLayoutId').then((res) => {

            cy.checkoutLayout(res);

            cy.goToLayoutAndLoadPrefs(res);
        });
    });

    /* Disabled for testing speed reasons
        afterEach(function() {
            // Remove the created layout
            cy.deleteLayout(this.testLayoutId);
        });
    */

    // Open widget form, change the name and duration, save, and see the name change result
    it('changes and saves widget properties', () => {
        // Create and alias for reload widget
        cy.server();
        cy.route('/playlist/widget/form/edit/*').as('reloadWidget');

        // Select the first widget from the first region on timeline ( image )
        cy.get('#layout-timeline .designer-region:first [data-type="widget"]:first-child').click();

        // Type the new name in the input
        cy.get('#properties-panel input[name="name"]').clear().type('newName');

        // Set a duration
        cy.get('#properties-panel #useDuration').check();
        cy.get('#properties-panel input[name="duration"]').clear().type(12);

        // Save form
        cy.get('#properties-panel button[data-action="save"]').click();

        // Should show a notification for the name change
        cy.get('.toast-success').contains('newName');

        // Check if the values are the same entered after reload
        cy.wait('@reloadWidget').then(() => {
            cy.get('#properties-panel input[name="name"]').should('have.attr', 'value').and('equal', 'newName');
            cy.get('#properties-panel input[name="duration"]').should('have.attr', 'value').and('equal', '12');
        });
    });

    // Open region form, change the name, dimensions and duration, save, and see the name change result
    it.skip('changes and saves region properties', () => {

        // Create and alias for reload region
        cy.server();
        cy.route('/region/form/edit/*').as('reloadRegion');

        // Open navigator edit
        cy.get('#layout-viewer-navbar #navigator-edit-btn').click();

        // Select the first region on navigator
        cy.get('#layout-navigator [data-type="region"]:first-child').click();

        // Type the new name in the input
        cy.get('#properties-panel input[name="name"]').clear().type('newName');

        // Save form
        cy.get('#properties-panel button#save').click();

        // Should show a notification for the name change
        cy.get('.toast-success').contains('newName');

        // Check if the values are the same entered after reload
        cy.wait('@reloadRegion').then(() => {
            cy.get('#properties-panel input[name="name"]').should('have.attr', 'value').and('equal', 'newName');
        });
    });

    // On layout edit form, change background color and layer, save and check the changes
    it('changes and saves layout properties', () => {

        // Create and alias for reload layout
        cy.server();
        cy.route('/layout?layoutId=*').as('reloadLayout');

        // Change background color
        cy.get('#properties-panel input[name="backgroundColor"]').clear().type('#ccc');

        // Change layer
        cy.get('#properties-panel input[name="backgroundzIndex"]').clear().type(1);

        // Save form
        cy.get('#properties-panel button[data-action="save"]').click();

        // Should show a notification for the successful save
        cy.get('.toast-success').contains('Edited');

        // Check if the values are the same entered after reload
        cy.wait('@reloadLayout').then(() => {
            cy.get('#properties-panel input[name="backgroundColor"]').should('have.attr', 'value').and('equal', '#cccccc');
            cy.get('#properties-panel input[name="backgroundzIndex"]').should('have.value', '1');
        });
    });

    // On layout edit form, change background image check the changes
    it('should change layout´s background image', () => {

        // Create and alias for reload layout
        cy.server();
        cy.route('/layout?layoutId=*').as('reloadLayout');

        cy.get('#properties-panel #select2-backgroundImageId-container').click();

        // Select the last image option available ( avoid result and message "options")
        cy.get('.select2-container .select2-results .select2-results__option:not(.select2-results__option--load-more):not(.loading-results):not(.select2-results__message):first').click();

        // Save form
        cy.get('#properties-panel button[data-action="save"]').click();

        cy.wait('@reloadLayout');

        // Change background image
        cy.get('#properties-panel #select2-backgroundImageId-container').should('have.attr', 'title').then((title) => {
            cy.get('#properties-panel #select2-backgroundImageId-container').click();

            // Select the last image option available ( avoid result and message "options")
            cy.get('.select2-container .select2-results .select2-results__option:not(.select2-results__option--load-more):not(.loading-results):not(.select2-results__message):last').click();

            // Save form
            cy.get('#properties-panel button[data-action="save"]').click();

            // Should show a notification for the successful save
            cy.get('.toast-success').contains('Edited');

            // Check if the values are the same entered after reload
            cy.wait('@reloadLayout').then(() => {
                cy.get('#properties-panel #select2-backgroundImageId-container').should('have.attr', 'title').and('not.include', title);
            });
        });
    });

    it.skip('should add a audio clip to a widget, and adds a link to open the form in the timeline', () => {

        cy.populateLibraryWithMedia();

        // Create and alias for reload layout
        cy.server();
        cy.route('/layout?layoutId=*').as('reloadLayout');

        // Open toolbar Tools tab
        cy.get('#layout-editor-toolbar #btn-menu-0').should('be.visible').click({force:true});
        cy.get('#layout-editor-toolbar #btn-menu-1').should('be.visible').click({force:true});

        // Open the audio form
        cy.dragToElement(
            '#layout-editor-toolbar #content-1 .toolbar-pane-content [data-sub-type="audio"] .drag-area',
            '#layout-timeline .designer-region:first [data-type="widget"]:nth-child(2)'
        ).then(() => {

            // Select the 1st option
            cy.get('[data-test="widgetPropertiesForm"] #mediaId > option').eq(1).then(($el) => {
                cy.get('[data-test="widgetPropertiesForm"] #mediaId').select($el.val());
            });

            // Save and close the form
            cy.get('[data-test="widgetPropertiesForm"] .btn-bb-done').click();

            // Check if the widget has the audio icon
            cy.wait('@reloadLayout').then(() => {
                cy.get('#layout-timeline .designer-region:first [data-type="widget"]:nth-child(2)')
                    .find('i[data-property="Audio"]').click();

                cy.get('[data-test="widgetPropertiesForm"]').contains('Audio for');
            });
        });
    });

    it.skip('attaches expiry dates to a widget, and adds a link to open the form in the timeline', () => {

        // Create and alias for reload layout
        cy.server();
        cy.route('/layout?layoutId=*').as('reloadLayout');

        // Open toolbar Tools tab
        cy.get('#layout-editor-toolbar #btn-menu-0').should('be.visible').click({force:true});
        cy.get('#layout-editor-toolbar #btn-menu-1').should('be.visible').click({force:true});

        // Open the audio form
        cy.dragToElement(
            '#layout-editor-toolbar #content-1 .toolbar-pane-content [data-sub-type="expiry"] .drag-area',
            '#layout-timeline .designer-region:first [data-type="widget"]:nth-child(2)'
        ).then(() => {

            // Add dates
            cy.get('[data-test="widgetPropertiesForm"] .starttime-control .date-clear-button').click();
            cy.get('[data-test="widgetPropertiesForm"] #fromDt').siblings('.date-open-button').click();
            cy.get('.flatpickr-calendar.open .dayContainer .flatpickr-day:first').click();

            cy.get('[data-test="widgetPropertiesForm"] .endtime-control .date-clear-button').click();
            cy.get('[data-test="widgetPropertiesForm"] #toDt').siblings('.date-open-button').click();
            cy.get('.flatpickr-calendar.open .dayContainer .flatpickr-day:first').click();
            
            // Save and close the form
            cy.get('[data-test="widgetPropertiesForm"] .btn-bb-done').click();

            // Check if the widget has the audio icon
            cy.wait('@reloadLayout').then(() => {
                cy.get('#layout-timeline .designer-region:first [data-type="widget"]:nth-child(2)')
                    .find('i[data-property="Expiry"]').click();

                cy.get('[data-test="widgetPropertiesForm"]').contains('Expiry for');
            });
        });
    });

    // NOTE: Test skipped for now until transitions are enabled by default
    it.skip('adds a transition to a widget, and adds a link to open the form in the timeline', () => {
        // Create and alias for reload layout
        cy.server();
        cy.route('/layout?layoutId=*').as('reloadLayout');

        // Open toolbar Tools tab
        cy.get('#layout-editor-toolbar #btn-menu-1').should('be.visible').click({force:true});

        // Open the audio form
        cy.dragToElement(
            '#layout-editor-toolbar .toolbar-pane-content [data-sub-type="transitionIn"] .drag-area',
            '#layout-timeline .designer-region:first [data-type="widget"]:nth-child(2)'
        ).then(() => {

            // Select the 1st option
            cy.get('[data-test="widgetPropertiesForm"] #transitionType > option').eq(1).then(($el) => {
                cy.get('[data-test="widgetPropertiesForm"] #transitionType').select($el.val());
            });

            // Save and close the form
            cy.get('[data-test="widgetPropertiesForm"] .btn-bb-done').click();

            // Check if the widget has the audio icon
            cy.wait('@reloadLayout').then(() => {
                cy.get('#layout-timeline .designer-region:first [data-type="widget"]:nth-child(2)')
                    .find('i[data-property="Transition"]').click();

                cy.get('[data-test="widgetPropertiesForm"]').contains('Edit in Transition for');
            });
        });
    });

    // Navigator
    it.skip('should change and save the region´s position', () => {

        // Create and alias for position save and reload layout
        cy.server();
        cy.route('/layout?layoutId=*').as('reloadLayout');
        cy.route('/region/form/edit/*').as('reloadRegion');

        // Open navigator edit
        cy.get('#layout-viewer-navbar #navigator-edit-btn').click();

        cy.get('#layout-navigator [data-type="region"]').then(($originalRegion) => {
            const regionId = $originalRegion.attr('id');

            // Select region
            cy.get('#layout-navigator-content #' + regionId).click();

            // Move region 50px for each dimension
            cy.get('#layout-navigator-content #' + regionId).then(($movedRegion) => {

                const regionOriginalPosition = {
                    top: Math.round($movedRegion.position().top),
                    left: Math.round($movedRegion.position().left)
                };

                const offsetToAdd = 50;

                // Move the region
                cy.get('#layout-navigator-content #' + regionId)
                    .trigger('mousedown', {
                        which: 1
                    })
                    .trigger('mousemove', {
                        which: 1,
                        pageX: $movedRegion.width() / 2 + $movedRegion.offset().left + offsetToAdd,
                        pageY: $movedRegion.height() / 2 + $movedRegion.offset().top + offsetToAdd
                    })
                    .trigger('mouseup');

                // Close the navigator edit
                cy.wait('@reloadRegion');

                // Save
                cy.get('#properties-panel button#save').click();

                // Wait for the layout to reload
                cy.wait('@reloadLayout');

                // Check if the region´s position are not the original
                cy.get('#layout-navigator-content #' + regionId).then(($changedRegion) => {
                    expect(Math.round($changedRegion.position().top)).to.not.eq(regionOriginalPosition.top);
                    expect(Math.round($changedRegion.position().left)).to.not.eq(regionOriginalPosition.left);
                });
            });
        });
    });

    it.skip('should delete a widget using the toolbar bin', () => {
        cy.server();
        cy.route('/layout?layoutId=*').as('reloadLayout');

        // Select a widget from the navigator
        cy.get('#layout-timeline .designer-region:first [data-type="widget"]:first-child').click().then(($el) => {

            const widgetId = $el.attr('id');

            // Click trash container
            cy.get('.editor-toolbar a#trashContainer').click();

            // Confirm delete on modal
            cy.get('[data-test="deleteObjectModal"] button.btn-bb-confirm').click();

            // Check toast message
            cy.get('.toast-success').contains('Deleted');

            // Wait for the layout to reload
            cy.wait('@reloadLayout');

            // Check that widget is not on timeline
            cy.get('#layout-timeline [data-type="widget"]#' + widgetId).should('not.exist');
        });
    });

    it('saves the widgets order when sorting by dragging', () => {
        cy.server();
        cy.route('POST', '**/playlist/order/*').as('saveOrder');
        cy.route('/layout?layoutId=*').as('reloadLayout');

        cy.get('#layout-timeline .designer-region:first [data-type="widget"]:first-child').then(($oldWidget) => {

            const offsetX = 50;

            // Move to the second widget position ( plus offset )
            cy.wrap($oldWidget)
                .trigger('mousedown', {
                    which: 1
                })
                .trigger('mousemove', {
                    which: 1,
                    pageX: $oldWidget.offset().left + $oldWidget.width() * 1.5 + offsetX
                })
                .trigger('mouseup', {force: true});

            cy.wait('@saveOrder');

            // Should show a notification for the order change
            cy.get('.toast-success').contains('Order Changed');

            // Reload layout and check if the new first widget has a different Id
            cy.wait('@reloadLayout');

            cy.get('#layout-timeline .designer-region:first [data-type="widget"]:first-child').then(($newWidget) => {
                expect($oldWidget.attr('id')).not.to.eq($newWidget.attr('id'));
            });
        });
    });

    it.skip('should publish a layout and go into a published state', () => {

        cy.server();
        cy.route('PUT', '/layout/publish/*').as('layoutPublish');

        cy.get('#layout-editor-topbar li.navbar-submenu-options a#optionsContainerTop').click();
        cy.get('#layout-editor-topbar li.navbar-submenu-options #publishLayout').click();

        cy.get('button.btn-bb-Publish').click();

        // Get the id from the published layout and check if the designer reloaded to the Read Only Mode of that layout
        cy.wait('@layoutPublish').then((res) => {
            // Check if the page redirected to the layout designer with the new published layout
            cy.url().should('include', '/layout/designer/' + res.response.body.data.layoutId);

            // Check if the read only message appears
            cy.get('#read-only-message').should('exist');
        });
    });
});