describe('Playlist Editor (Populated)', function() {

    beforeEach(function() {
        cy.login();

        // Create random name
        let uuid = Cypress._.random(0, 1e9);

        // Create a new layout and go to the layout's designer page
        cy.createNonDynamicPlaylist(uuid).as('testPlaylistId').then((res) => {

            // Populate playlist with some widgets and media
            cy.addWidgetToPlaylist(res, 'embedded', {
                name: 'Embedded Widget'
            });

            cy.addRandomMediaToPlaylist(res);

            cy.addWidgetToPlaylist(res, 'clock', {
                name: 'Clock Widget'
            });

            cy.openPlaylistEditorAndLoadPrefs(res);
        });
    });

    it('changes and saves widget properties', () => {
        // Create and alias for reload widget
        cy.server();
        cy.route('/playlist/widget/form/edit/*').as('reloadWidget');

        // Select the first widget on timeline ( image )
        cy.get('#timeline-container [data-type="widget"]:first-child').click();

        // Wait for the widget to load
        cy.wait('@reloadWidget');

        // Type the new name in the input
        cy.get('#properties-panel-container input[name="name"]').clear().type('newName');

        // Set a duration
        cy.get('#properties-panel-container #useDuration').check();
        cy.get('#properties-panel-container input[name="duration"]').clear().type(12);

        // Save form
        cy.get('#properties-panel-container button[data-action="save"]').click();

        // Should show a notification for the name change
        cy.get('.toast-success');

        // Wait for the widget to reload
        cy.wait('@reloadWidget');

        // Check if the values are the same entered after reload
        cy.get('#properties-panel-container input[name="name"]').should('have.attr', 'value').and('equal', 'newName');
        cy.get('#properties-panel-container input[name="duration"]').should('have.attr', 'value').and('equal', '12');

    });

    it('should revert a saved form to a previous state', () => {

        let oldName;

        // Create and alias for reload widget
        cy.server();
        cy.route('/playlist/widget/form/edit/*').as('reloadWidget');
        cy.route('PUT', '/playlist/widget/*').as('saveWidget');

        // Select the first widget on timeline ( image )
        cy.get('#timeline-container [data-type="widget"]:first-child').click();

        // Wait for the widget to load
        cy.wait('@reloadWidget');

        // Get the input field
        cy.get('#properties-panel-container input[name="name"]').then(($input) => {

            // Save old name
            oldName = $input.val();

            //Type the new name in the input
            cy.get('#properties-panel-container input[name="name"]').clear().type('newName');

            // Save form
            cy.get('#properties-panel-container button[data-action="save"]').click();

            // Should show a notification for the name change
            cy.get('.toast-success');

            // Wait for the widget to save
            cy.wait('@reloadWidget');

            // Click the revert button
            cy.get('#playlist-editor-toolbar #undoContainer').click();

            // Wait for the widget to save
            cy.wait('@saveWidget');

            // Test if the revert made the name go back to the old name
            cy.get('#properties-panel-container input[name="name"]').should('have.attr', 'value').and('equal', oldName);
        });
    });

    it.skip('should delete a widget using the toolbar bin', () => {
        cy.server();
        cy.route('/playlist?playlistId=*').as('reloadPlaylist');

        // Select a widget from the navigator
        cy.get('#playlist-timeline [data-type="widget"]:first-child').click().then(($el) => {

            const widgetId = $el.attr('id');

            // Click trash container
            cy.get('#playlist-editor-toolbar a#trashContainer').click();

            // Confirm delete on modal
            cy.get('[data-test="deleteObjectModal"] button.btn-bb-confirm').click();

            // Check toast message
            cy.get('.toast-success').contains('Deleted');

            // Wait for the layout to reload
            cy.wait('@reloadPlaylist');

            // Check that widget is not on timeline
            cy.get('#playlist-timeline [data-type="widget"]#' + widgetId).should('not.exist');
        });
    });

    it.skip('should add an audio clip to a widget by drag and drop, and adds a link to open the form in the timeline', () => {
        
        cy.populateLibraryWithMedia();

        // Create and alias for reload playlist
        cy.server();
        cy.route('/playlist?playlistId=*').as('reloadPlaylist');

        // Open toolbar Tools tab
        cy.get('#playlist-editor-toolbar #btn-menu-0').should('be.visible').click();
        cy.get('#playlist-editor-toolbar #btn-menu-1').should('be.visible').click();

        // Open the audio form
        cy.dragToElement(
            '#playlist-editor-toolbar #content-1 .toolbar-pane-content [data-sub-type="audio"] .drag-area',
            '#timeline-container [data-type="widget"]:first-child'
        ).then(() => {

            // Select the 1st option
            cy.get('[data-test="widgetPropertiesForm"] #mediaId > option').eq(1).then(($el) => {
                cy.get('[data-test="widgetPropertiesForm"] #mediaId').select($el.val());
            });

            // Save and close the form
            cy.get('[data-test="widgetPropertiesForm"] .btn-bb-done').click();

            // Check if the widget has the audio icon
            cy.wait('@reloadPlaylist');
            cy.get('#timeline-container [data-type="widget"]:first-child')
                .find('i[data-property="Audio"]').click();

            cy.get('[data-test="widgetPropertiesForm"]').contains('Audio for');
        });
    });

    // Skip test for now ( it's failing in the test suite and being tested already in layout designer spec ) 
    it.skip('attaches expiry dates to a widget by drag and drop, and adds a link to open the form in the timeline', () => {
        // Create and alias for reload playlist
        cy.server();
        cy.route('/playlist?playlistId=*').as('reloadPlaylist');
        
        // Open toolbar Tools tab
        cy.get('#playlist-editor-toolbar #btn-menu-0').should('be.visible').click();
        cy.get('#playlist-editor-toolbar #btn-menu-1').should('be.visible').click();

        // Open the expiry form
        cy.dragToElement(
            '#playlist-editor-toolbar #content-1 .toolbar-pane-content [data-sub-type="expiry"] .drag-area',
            '#timeline-container [data-type="widget"]:first-child'
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

            // Check if the widget has the expiry dates icon
            cy.wait('@reloadPlaylist');
            cy.get('#timeline-container [data-type="widget"]:first-child')
                .find('i[data-property="Expiry"]').click();

            cy.get('[data-test="widgetPropertiesForm"]').contains('Expiry for');
        });
    });

    // NOTE: Test skipped for now until transitions are enabled by default
    it.skip('adds a transition to a widget by drag and drop, and adds a link to open the form in the timeline', () => {
        // Create and alias for reload playlist
        cy.server();
        cy.route('/playlist?playlistId=*').as('reloadPlaylist');

        // Open toolbar Tools tab
        cy.get('#playlist-editor-toolbar #btn-menu-0').should('be.visible').click();
        cy.get('#playlist-editor-toolbar #btn-menu-1').should('be.visible').click();

        // Open the transition form
        cy.dragToElement(
            '#playlist-editor-toolbar .toolbar-pane-content [data-sub-type="transitionIn"] .drag-area',
            '#timeline-container [data-type="widget"]:nth-child(2)'
        ).then(() => {

            // Select the 1st option
            cy.get('[data-test="widgetPropertiesForm"] #transitionType > option').eq(1).then(($el) => {
                cy.get('[data-test="widgetPropertiesForm"] #transitionType').select($el.val());
            });

            // Save and close the form
            cy.get('[data-test="widgetPropertiesForm"] .btn-bb-done').click();

            // Check if the widget has the transition icon
            cy.wait('@reloadPlaylist').then(() => {
                cy.get('#timeline-container [data-type="widget"]:nth-child(2)')
                    .find('i[data-property="Transition"]').click();

                cy.get('[data-test="widgetPropertiesForm"]').contains('Edit in Transition for');
            });
        });
    });

    it.skip('check if the form to attach a transition to a widget by click to add appears', () => {
        // Create and alias for reload playlist
        cy.server();
        cy.route('/playlist?playlistId=*').as('reloadPlaylist');

        // Open toolbar Tools tab
        cy.get('#playlist-editor-toolbar #btn-menu-0').should('be.visible').click();
        cy.get('#playlist-editor-toolbar #btn-menu-1').should('be.visible').click();

        // Activate the Add button
        cy.get('#playlist-editor-toolbar #content-1 .toolbar-pane-content [data-sub-type="transitionIn"] .add-area').invoke('show').click();

            // Click on the widget to add
        cy.get('#timeline-container [data-type="widget"]:nth-child(2)').click();

        // Check if the right form appears
        cy.get('[data-test="widgetPropertiesForm"]').contains('Edit in Transition for');

    });
});