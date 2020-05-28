// NAVIGATOR Module

// Load templates
const TopbarTemplate = require('../templates/topbar.hbs');
const TopbarLayoutJumpList = require('../templates/toolbar-layout-jump-list.hbs');

/**
 * Bottom topbar contructor
 * @param {object} container - the container to render the navigator to
 * @param {object[]} [customDropdownOptions] - customized dropdown buttons
 * @param {object} [customActions] - customized actions
 * @param {boolean=} [showOptions] - show options menu
 */
let Topbar = function(parent, container, customDropdownOptions = null, customActions = {}, jumpList = {}, showOptions = false) {

    this.parent = parent;
    
    this.DOMObject = container;

    // Layout jumplist
    this.jumpList = jumpList;

    // Custom dropdown buttons
    this.customDropdownOptions = customDropdownOptions;

    // Custom actions
    this.customActions = customActions;

    // Options menu
    this.showOptions = showOptions;

    // Flag to mark if the topbar has been rendered at least one time
    this.firstRun = true;
};

/**
 * Render topbar
 */
Topbar.prototype.render = function() {

    // Load preferences when the topbar is rendered for the first time
    if(this.firstRun) {
        // Mark topbar as loaded
        this.firstRun = false;
    }

    let self = this;
    const app = this.parent;

    // Get main object 
    const mainObject = app.getElementByTypeAndId(app.mainObjectType, app.mainObjectId);

    // Get topbar trans
    let newTopbarTrans = $.extend(toolbarTrans, topbarTrans);

    // Compile layout template with data
    const html = TopbarTemplate({
        customDropdownOptions: this.customDropdownOptions,
        displayTooltips: app.common.displayTooltips,
        trans: newTopbarTrans,
        mainObject: mainObject,
        showOptions: self.showOptions
    });

    // Append layout html to the main div
    this.DOMObject.html(html);

    const setButtonActionAndState = function(button) {
        let buttonInactive = false;

        // Bind action to button
        self.DOMObject.find('#' + button.id).click(
            button.action
        );

        // If there is a inactiveCheck, use that function to switch button state
        if(button.inactiveCheck != undefined) {
            const inactiveClass = (button.inactiveCheckClass != undefined) ? button.inactiveCheckClass : 'disabled';
            const toggleValue = button.inactiveCheck();
            self.DOMObject.find('#' + button.id).toggleClass(inactiveClass, toggleValue);
            buttonInactive = toggleValue;
        }

        return buttonInactive;
    };

    // Handle custom dropwdown buttons
    if(this.customDropdownOptions != null) {

        let activeDropdown = false;

        for(let index = 0;index < this.customDropdownOptions.length;index++) {
            let buttonInactive = setButtonActionAndState(this.customDropdownOptions[index]);

            if(!buttonInactive) {
                activeDropdown = true;
            }
        }

        self.DOMObject.find('.dropdown.navbar-submenu:not(.navbar-submenu-options)').toggle(activeDropdown);
    }

    // Set layout jumpList if exists
    if(!$.isEmptyObject(this.jumpList) && $('#layoutJumpList').length == 0) {
        this.setupJumpList($("#layoutJumpListContainer"));
    }

    // Options menu
    if(self.showOptions) {
        self.DOMObject.find('.navbar-submenu-options-container').off().click(function(e) {
            e.stopPropagation();
        });

        // Toggle tooltips
        self.DOMObject.find('#displayTooltips').off().click(function() {

            app.common.displayTooltips = $('#displayTooltips').prop('checked');

            if(app.common.displayTooltips) {
                toastr.success(editorsTrans.tooltipsEnabled);
            } else {
                toastr.error(editorsTrans.tooltipsDisabled);
            }

            app.toolbar.savePrefs();

            app.common.reloadTooltips(app.editorContainer);
        });

        // Reset tour
        if(typeof app.resetTour === 'function') {
            self.DOMObject.find('#resetTour').removeClass('hidden').off().click(function() {
                app.resetTour();
            });
        }
    }

    // Update layout status
    this.updateLayoutStatus();
};

/**
* Setup layout jumplist
* @param {object} jumpListContainer
*/
Topbar.prototype.setupJumpList = function(jumpListContainer) {

    const html = TopbarLayoutJumpList(this.jumpList);
    const self = this;

    // Append layout html to the main div
    jumpListContainer.html(html);

    jumpListContainer.show();

    const jumpList = jumpListContainer.find('#layoutJumpList');

    jumpList.select2({
        ajax: {
            url: jumpList.data().url,
            dataType: "json",
            data: function(params) {

                var query = {
                    layout: params.term,
                    start: 0,
                    length: 10
                };

                // Tags
                if(query.layout != undefined) {
                    var tags = query.layout.match(/\[([^}]+)\]/);
                    if(tags != null) {
                        // Add tags to search
                        query.tags = tags[1];

                        // Replace tags in the query text
                        query.layout = query.layout.replace(tags[0], '');
                    }
                }

                // Set the start parameter based on the page number
                if(params.page != null) {
                    query.start = (params.page - 1) * 10;
                }

                // Find out what is inside the search box for this list, and save it (so we can replay it when the list
                // is opened again)
                if(params.term !== undefined) {
                    localStorage.liveSearchPlaceholder = params.term;
                }

                return query;
            },
            processResults: function(data, params) {
                var results = [];

                $.each(data.data, function(index, element) {
                    results.push({
                        "id": element.layoutId,
                        "text": element.layout
                    });
                });

                var page = params.page || 1;
                page = (page > 1) ? page - 1 : page;

                return {
                    results: results,
                    pagination: {
                        more: (page * 10 < data.recordsTotal)
                    }
                };
            },
            delay: 250
        }
    });

    jumpList.on("select2:select", function(e) {
        // OPTIMIZE: Maybe use the layout load without reloading page
        //self.jumpList.callback(e.params.data.id);

        // Go to the Layout we've selected.
        window.location = jumpList.data().designerUrl.replace(":id", e.params.data.id);
    }).on("select2:opening", function(e) {
        // Set the search box according to the saved value (if we have one)

        if(localStorage.liveSearchPlaceholder != null && localStorage.liveSearchPlaceholder !== "") {
            var $search = jumpList.data("select2").dropdown.$search;
            $search.val(localStorage.liveSearchPlaceholder);

            setTimeout(function() {
                $search.trigger("input");
            }, 100);
        }
    });
};


/**
 * Update layout status in the info fields
 */
Topbar.prototype.updateLayoutStatus = function() {

    const statusContainer = this.DOMObject.find('#layout-info-status');
    const app = this.parent;

    // Use status loader icon
    statusContainer.find('i').removeClass().addClass('fa fa-spinner fa-spin');
    statusContainer.removeClass().addClass('label label-default');

    // Prevent the update if there's no layout status yet
    if(lD.layout.status == undefined) {
        return;
    }

    let title = '';
    let content = '';

    const labelCodes = {
        '1': 'success',
        '2': 'warning',
        '3': 'info',
        '': 'danger'
    };

    const iconCodes = {
        '1': 'check',
        '2': 'exclamation',
        '3': 'cogs',
        '': 'times'
    };

    // Create title and description
    if(lD.layout.status.messages.length > 0) {
        title = lD.layout.status.description;
        for(let index = 0;index < lD.layout.status.messages.length;index++) {
            content += '<div class="status-message">' + lD.layout.status.messages[index] + '</div>';
        }
    } else {
        title = '';
        content = '<div class="status-title text-center">' + lD.layout.status.description + '</div>';
    }

    // Update label
    let labelType = (labelCodes[lD.layout.status.code] != undefined) ? labelCodes[lD.layout.status.code] : labelCodes[''];
    statusContainer.removeClass().addClass('label label-' + labelType)
        .attr('data-status-code', lD.layout.status.code);

    // Create or update popover
    if(statusContainer.data('bs.popover') == undefined) {
        // Create popover
        statusContainer.popover(
            {
                delay: tooltipDelay,
                title: title,
                content: content
            }
        );
    } else {
        // Update popover
        statusContainer.data('bs.popover').options.title = title;
        statusContainer.data('bs.popover').options.content = content;
    }

    // Click status to scroll timeline to first broken widget
    statusContainer.toggleClass('clickable', (iconCodes[lD.layout.status.code] == undefined)).on('click', function(){
        if(iconCodes[lD.layout.status.code] == undefined) {
            app.timeline.scrollToBrokenWidget();
        }
    });

    // Change Icon
    let iconType = (iconCodes[lD.layout.status.code] != undefined) ? iconCodes[lD.layout.status.code] : iconCodes[''];
    statusContainer.find('i').removeClass().addClass('fa fa-' + iconType);
};

module.exports = Topbar;