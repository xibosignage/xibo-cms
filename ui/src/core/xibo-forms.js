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

/* eslint-disable new-cap */
/**
 * Switches an item between 2 connected lists.
 */
function switchLists(e) {
   // determine which list they are in
   // http://www.remotesynthesis.com/post.cfm/working-with-related-sortable-lists-in-jquery-ui
   var otherList = $($(e.currentTarget).parent().sortable("option","connectWith")).not($(e.currentTarget).parent());

   otherList.append(e.currentTarget);
}

function GroupSecurityCallBack(dialog)
{
    $("#groupsIn, #groupsOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

    $(".li-sortable", dialog).dblclick(switchLists);
}

function GroupSecuritySubmit() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#groupsIn").attr('href') + "&ajax=true";
    
    // Get the two lists        
    serializedData = $("#groupsIn").sortable('serialize');
    
    $.ajax({
        type: "post",
        url: href,
        cache: false,
        dataType: "json",
        data: serializedData,
        success: XiboSubmitResponse
    });
    
    return;
}

function DisplayGroupManageMembersCallBack(dialog)
{
    $("#displaysIn, #displaysOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

    $(".li-sortable", dialog).dblclick(switchLists);
}

function DisplayGroupMembersSubmit() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#displaysIn").attr('href') + "&ajax=true";

    // Get the two lists
    serializedData = $("#displaysIn").sortable('serialize');

    $.ajax({
        type: "post",
        url: href,
        cache: false,
        dataType: "json",
        data: serializedData,
        success: XiboSubmitResponse
    });

    return;
}

/**
 * Library Assignment Form Callback
 */
var FileAssociationsCallback = function()
{
    // Attach a click handler to all of the little pointers in the grid.
    $("#FileAssociationsTable .library_assign_list_select").click(function(){
        // Get the row that this is in.
        var row = $(this).parent().parent();

        // Construct a new list item for the lower list and append it.
        var newItem = $("<li/>", {
            text: row.attr("litext"),
            id: row.attr("rowid"),
            "class": "li-sortable",
            dblclick: function(){
                $(this).remove();
            }
        });

        newItem.appendTo("#FileAssociationsSortable");

        // Add a span to that new item
        $("<span/>", {
            "class": "fa fa-minus",
            click: function(){
                $(this).parent().remove();
                $(".modal-body .XiboGrid").each(function(){

                    var gridId = $(this).attr("id");

                    // Render
                    XiboGridRender(gridId);
                });
            }
        })
        .appendTo(newItem);

        // Remove the row
        row.remove();
    });

    // Attach a click handler to all of the little points in the trough
    $("#FileAssociationsSortable li .fa-minus").click(function() {

        // Remove this and refresh the table
        $(this).parent().remove();

    });

    $("#FileAssociationsSortable").sortable().disableSelection();
};

var FileAssociationsSubmit = function(displayGroupId)
{
    // Serialize the data from the form and call submit
    var mediaList = $("#FileAssociationsSortable").sortable('serialize');

    $.ajax({
        type: "post",
        url: "index.php?p=displaygroup&q=SetFileAssociations&displaygroupid="+displayGroupId+"&ajax=true",
        cache: false,
        dataType: "json",
        data: mediaList,
        success: XiboSubmitResponse
    });
};

var settingsUpdated = function(response) {
    if (!response.success) {
        SystemMessage((response.message == "") ? translation.failure : response.message, true);
    }
};

function permissionsFormOpen(dialog) {

    var grid = $("#permissionsTable").closest(".XiboGrid");

    // initialise the permissions array
    if (grid.data().permissions.length <= 0)
        grid.data().permissions = {};

    var table = $("#permissionsTable").DataTable({ "language": dataTablesLanguage,
        serverSide: true, stateSave: true,
        "filter": false,
        searchDelay: 3000,
        "order": [[ 0, "asc"]],
        ajax: {
            url: grid.data().url,
            "data": function(d) {
                $.extend(d, grid.find(".permissionsTableFilter form").serializeObject());
            }
        },
        "columns": [
            {
                "data": "group",
                "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    if (row.isUser == 1)
                        return data;
                    else
                        return '<strong>' + data + '</strong>';
                }
            },
            { "data": "view", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in grid.data().permissions) {
                        var cache = grid.data().permissions[row.groupId];

                        checked = (cache.view !== undefined && cache.view === 1) ? 1 : 0;
                    } else {
                        checked = data;
                    }

                    // Cached changes to this field?
                    return "<input type=\"checkbox\" data-permission=\"view\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            },
            { "data": "edit", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in grid.data().permissions) {
                        var cache = grid.data().permissions[row.groupId];

                        checked = (cache.edit !== undefined && cache.edit === 1) ? 1 : 0;
                    } else {
                        checked = data;
                    }

                    return "<input type=\"checkbox\" data-permission=\"edit\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            },
            { "data": "delete", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in grid.data().permissions) {
                        var cache = grid.data().permissions[row.groupId];

                        checked = (cache.delete !== undefined && cache.delete === 1) ? 1 : 0;
                    } else {
                        checked = data;
                    }

                    return "<input type=\"checkbox\" data-permission=\"delete\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            }
        ]
    });

    table.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Bind to the checkboxes change event
        var target = $("#" + e.target.id);
        target.find("input[type=checkbox]").change(function() {
            // Update our global permissions data with this
            var groupId = $(this).data().groupId;
            var permission = $(this).data().permission;
            var value = $(this).is(":checked");
            //console.log("Setting permissions on groupId: " + groupId + ". Permission " + permission + ". Value: " + value);
            if (grid.data().permissions[groupId] === undefined) {
                grid.data().permissions[groupId] = {};
            }
            grid.data().permissions[groupId][permission] = (value) ? 1 : 0;
        });
    });
    table.on('processing.dt', dataTableProcessing);

    // Bind our filter
    grid.find(".permissionsTableFilter form input, .permissionsTableFilter form select").change(function() {
        table.ajax.reload();
    });
}

function permissionsFormSubmit(id) {

    var form = $("#" + id);
    var $formContainer = form.closest(".permissions-form");
    var permissions = {
        "groupIds": $(form).data().permissions,
        "ownerId": $formContainer.find("select[name=ownerId]").val()
    };
    var data = $.param(permissions);

    $.ajax({
        type: "POST",
        url: form.data().url,
        cache: false,
        dataType: "json",
        data: data,
        success: function(xhr, textStatus, error) {
            XiboSubmitResponse(xhr, form);
        },
        error: function(xhr, textStatus, errorThrown) {
            SystemMessage(xhr.responseText, false);
        }
    });
}

function permissionsMultiFormOpen(dialog) {
    var $permissionsTable = $(dialog).find("#permissionsMultiTable");
    var $grid = $permissionsTable.closest(".XiboGrid");

    var table = $permissionsTable.DataTable({ "language": dataTablesLanguage,
        serverSide: true, 
        stateSave: true,
        "filter": false,
        searchDelay: 3000,
        "order": [[ 0, "asc"]],
        ajax: {
            url: $grid.data().url,
            "data": function(d) {
                $.extend(d, $grid.find(".permissionsMultiTableFilter form").serializeObject());

                $.extend(d, {
                    ids: $grid.data().targetIds
                });
            },
            "dataSrc": function(json) {
                var newData = json.data;

                for (var dataKey in newData) {
                    if (newData.hasOwnProperty(dataKey)) {
                        var permissionGrouped = {
                            "view": null,
                            "edit": null,
                            "delete": null
                        }

                        for (var key in newData[dataKey].permissions) {
                            if (newData[dataKey].permissions.hasOwnProperty(key)) {
                                var permission = newData[dataKey].permissions[key];

                                if(permission.view != permissionGrouped.view) {
                                    if(permissionGrouped.view != null) {
                                        permissionGrouped.view = 2;
                                    } else {
                                        permissionGrouped.view = permission.view;
                                    }
                                }

                                if(permission.edit != permissionGrouped.edit) {
                                    if(permissionGrouped.edit != null) {
                                        permissionGrouped.edit = 2;
                                    } else {
                                        permissionGrouped.edit = permission.edit;
                                    }
                                }

                                if(permission.delete != permissionGrouped.delete) {
                                    if(permissionGrouped.delete != null) {
                                        permissionGrouped.delete = 2;
                                    } else {
                                        permissionGrouped.delete = permission.delete;
                                    }
                                }
                            }
                        }

                        newData[dataKey] = Object.assign(permissionGrouped, newData[dataKey]);
                        delete newData[dataKey].permissions;
                    }
                }

                // merge the permission and start permissions arrays
                $grid.data().permissions = Object.assign({}, $grid.data().permissions, newData);
                $grid.data().startPermissions = Object.assign({}, $grid.data().startPermissions, JSON.parse(JSON.stringify(newData)));

                // init save permissions if undefined
                if ($grid.data().savePermissions == undefined) {
                    $grid.data().savePermissions = {};
                }

                // Return an array of permissions
                return Object.values(newData);
            }
        },
        "columns": [
            {
                "data": "group",
                "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    if (row.isUser == 1)
                        return data;
                    else
                        return '<strong>' + data + '</strong>';
                }
            },
            { "data": "view", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in $grid.data().permissions) {
                        var cache = $grid.data().permissions[row.groupId];

                        checked = (cache.view !== undefined && cache.view !== 0) ? cache.view : 0;
                    } else {
                        checked = data;
                    }

                    // Cached changes to this field?
                    return "<input type=\"checkbox\" class=\"" + ((checked === 2) ? "indeterminate" : "") + "\" data-permission=\"view\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            },
            { "data": "edit", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in $grid.data().permissions) {
                        var cache = $grid.data().permissions[row.groupId];

                        checked = (cache.edit !== undefined && cache.edit !== 0) ? cache.edit : 0;
                    } else {
                        checked = data;
                    }

                    return "<input type=\"checkbox\" class=\"" + ((checked === 2) ? "indeterminate" : "") + "\" data-permission=\"edit\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            },
            { "data": "delete", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in $grid.data().permissions) {
                        var cache = $grid.data().permissions[row.groupId];

                        checked = (cache.delete !== undefined && cache.delete !== 0) ? cache.delete : 0;
                    } else {
                        checked = data;
                    }

                    return "<input type=\"checkbox\" class=\"" + ((checked === 2) ? "indeterminate" : "") + "\" data-permission=\"delete\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            }
        ]
    });

    table.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Bind to the checkboxes change event
        var target = $("#" + e.target.id);
        target.find("input[type=checkbox]").change(function() {
            // Update our global permissions data with this
            var groupId = $(this).data().groupId;
            var permission = $(this).data().permission;
            var value = $(this).is(":checked");
            var valueNumeric = (value) ? 1 : 0;

            //console.log("Setting permissions on groupId: " + groupId + ". Permission " + permission + ". Value: " + value);
            // Update main permission object
            if ($grid.data().permissions[groupId] === undefined) {
                $grid.data().permissions[groupId] = {};
            }
            $grid.data().permissions[groupId][permission] = valueNumeric;

            // Update save permissions object
            if($grid.data().savePermissions[groupId] === undefined) {
                $grid.data().savePermissions[groupId] = {};
                $grid.data().savePermissions[groupId][permission] = valueNumeric;
            } else {
                if($grid.data().startPermissions[groupId][permission] === valueNumeric) {
                    // if changed value is the same as the initial permission object, remove it from the save permissions object
                    delete $grid.data().savePermissions[groupId][permission];

                    // Remove group if it's an empty object
                    if($.isEmptyObject($grid.data().savePermissions[groupId])) {
                        delete $grid.data().savePermissions[groupId]; 
                    }
                } else {
                    // Add new change to the save permissions object
                    $grid.data().savePermissions[groupId][permission] = valueNumeric;
                }
            }

            // Enable save button only if we have permission changes to save
            $(dialog).find('.save-button').toggleClass('disabled', $.isEmptyObject($grid.data().savePermissions));
        });

        // Mark indeterminate checkboxes and add title
        target.find('input[type=checkbox].indeterminate').prop('indeterminate', true).prop('title', translations.indeterminate);
    });

    // Disable save button by default
    $(dialog).find('.save-button').addClass('disabled');

    table.on('processing.dt', dataTableProcessing);

    // Bind our filter
    $grid.find(".permissionsMultiTableFilter form input, .permissionsMultiTableFilter form select").change(function() {
        table.ajax.reload();
    });
}

function permissionsMultiFormSubmit(id) {
    var form = $("#" + id);
    var permissions = $(form).data().savePermissions;
    var targetIds = $(form).data().targetIds;
    
    var data = $.param({
        groupIds: permissions,
        ids: targetIds
    });
    
    $.ajax({
        type: "POST",
        url: form.data().url,
        cache: false,
        dataType: "json",
        data: data,
        success: function(xhr, textStatus, error) {
            XiboSubmitResponse(xhr, form);
        },
        error: function(xhr, textStatus, errorThrown) {
            SystemMessage(xhr.responseText, false);
        }
    });
}

/**
 * Create context and pass it to createTableFromContext
 * @param {object} dialog
 */
function createDisplayGroupMembersTable(dialog) {
  const control = $(dialog).find('.controlDiv');
  const context = {
    tableName: '#displaysGroupsMembersTable',
    columns: [
      {data: 'displayGroupId', responsivePriority: 2},
      {data: 'displayGroup', responsivePriority: 2},
    ],
    members: control.data().members.displayGroups,
    extra: dialog.data().extra.displayGroupsAssigned,
    id: 'displayGroupId',
    type: 'displayGroup',
    getUrl: control.data().displayGroupsGetUrl,
  };
  createTableFromContext(dialog, context);
}

/**
 * Create context and pass it to createTableFromContext
 * @param {object} dialog
 */
function createDisplayMembersTable(dialog) {
  const control = $(dialog).find('.controlDiv');
  const context = {
    tableName: '#displaysMembersTable',
    columns: [
      {data: 'displayId', responsivePriority: 2},
      {data: 'display', responsivePriority: 2},
      {
        data: 'mediaInventoryStatus',
        responsivePriority: 2,
        render: function(data, type, row) {
          if (type != 'display') {
            return data;
          }
          let icon = '';
          if (data == 1) {
            icon = 'fa-check';
          } else if (data == 0) {
            icon = 'fa-times';
          } else {
            icon = 'fa-cloud-download';
          }
          return '<span class=\'fa ' + icon + '\'></span>';
        },
      },
      {
        data: 'loggedIn',
        render: dataTableTickCrossColumn,
        responsivePriority: 3,
      },
      {
        name: 'clientSort',
        responsivePriority: 3,
        data: function(data) {
          return data.clientType + ' ' +
            data.clientVersion + '-' +
            data.clientCode;
        },
        visible: false,
      },
    ],
    members: control.data().members.displays,
    extra: dialog.data().extra.displaysAssigned,
    id: 'displayId',
    type: 'display',
    getUrl: control.data().displayGetUrl,
  };
  createTableFromContext(dialog, context);
}
/**
 * Create context and pass it to createTableFromContext
 * @param {object} dialog
 */
function createUserMembersTable(dialog) {
  const control = $(dialog).find('.controlDiv');
  const context = {
    tableName: '#userMembersTable',
    columns: [
      {data: 'userId', responsivePriority: 2},
      {data: 'userName', responsivePriority: 2},
    ],
    members: control.data().members.users,
    extra: dialog.data().extra.usersAssigned,
    id: 'userId',
    type: 'user',
    getUrl: control.data().userGetUrl,
  };
  createTableFromContext(dialog, context);
}

/**
 * Create context and pass it to createTableFromContext
 * @param {object} dialog
 */
function createUserGroupMembersTable(dialog) {
  const control = $(dialog).find('.controlDiv');
  const context = {
    tableName: '#userGroupMembersTable',
    columns: [
      {data: 'groupId', responsivePriority: 2},
      {data: 'group', responsivePriority: 2},
    ],
    members: control.data().members.userGroups,
    extra: dialog.data().extra.userGroupsAssigned,
    id: 'groupId',
    type: 'userGroup',
    getUrl: control.data().userGroupsGetUrl,
  };
  createTableFromContext(dialog, context);
}

/**
 * Create datatable from provided context
 * @param {object} dialog
 * @param {object} context
 */
function createTableFromContext(dialog, context) {
  const control = $(dialog).find('.controlDiv');
  const columns = context.columns;

  columns.push({
    name: 'member',
    responsivePriority: 2,
    data: function(data, type, row) {
      if (type != 'display') {
        return data;
      }

      let checked = '';

      // Check if the element is already been checked/unchecked
      if (typeof control.data().members != 'undefined' &&
        context.members[data[context.id]] != undefined
      ) {
        checked = (context.members[data[context.id]]) ? 'checked' : '';
      } else {
        // If its not been altered, check for the original state
        if (dialog.data().extra) {
          context.extra.forEach(function(extraElement) {
            if (extraElement[context.id] == data[context.id]) {
              checked = 'checked';
            }
          });
        }
      }
      // Create checkbox
      return '<input type="checkbox" class="checkbox"' +
        ' data-member-id=' + data[context.id] + ' data-member-type="' +
        context.type + '" ' + checked + '>';
    },
  });

  const table = $(context.tableName).DataTable({
    language: dataTablesLanguage,
    serverSide: true,
    stateSave: true, stateDuration: 0,
    filter: false,
    responsive: true,
    searchDelay: 3000,
    order: [[1, 'asc']],
    ajax: {
      url: context.getUrl ?? control.data().getUrl,
      data: function(data) {
        $.extend(data,
          $(context.tableName)
            .closest('.XiboGrid')
            .find('.FilterDiv form')
            .serializeObject(),
        );
        return data;
      },
    },
    columns: columns,
  });

  table.on('draw', dataTableDraw);
  table.on('processing.dt', dataTableProcessing);
}

/**
 * Callback for all membership forms
 * @param {object} dialog Dialog object
 */
function membersFormOpen(dialog) {
  const control = $(dialog).find('.controlDiv');
  // This contains the changes made since the form open

  if (control.data().members == undefined) {
    control.data().members = {
      displays: {},
      displayGroups: {},
      users: {},
      userGroups: {},
    };
  }
  if (control.data().displayGroups) {
    createDisplayGroupMembersTable(dialog);
  }

  if (control.data().display) {
    createDisplayMembersTable(dialog);
  }

  if (control.data().userGroups) {
    createUserGroupMembersTable(dialog);
  }

  if (control.data().user) {
    createUserMembersTable(dialog);
  }

  // Bind to the checkboxes change event
  control.on('change', '.checkbox', function() {
    // Update our global members data with this
    const memberId = $(this).data().memberId;
    const memberType = $(this).data().memberType;
    const value = $(this).is(':checked');

    if (memberType === 'display') {
      control.data().members.displays[memberId] = (value) ? 1 : 0;
    } else if (memberType === 'displayGroup') {
      control.data().members.displayGroups[memberId] = (value) ? 1 : 0;
    } else if (memberType === 'user') {
      control.data().members.users[memberId] = (value) ? 1 : 0;
    } else if (memberType === 'userGroup') {
      control.data().members.userGroups[memberId] = (value) ? 1 : 0;
    }
  });
}

/**
 * Submit membership form
 * @param {string} id The form id
 */
function membersFormSubmit(id) {
  const form = $('#' + id);
  const members = form.data().members;

  // There may not have been any changes
  if (members == undefined) {
    // No changes
    XiboDialogClose();
    return;
  }

  // Create a new queue.
  window.queue = $.jqmq({
    // Next item will be processed only when queue.next() is called in callback.
    delay: -1,

    // Process queue items one-at-a-time.
    batch: 1,

    // For each queue item, execute this function, making an AJAX request. Only
    // continue processing the queue once the AJAX request's callback executes.
    callback: function(data) {
      // Make an AJAX call
      $.ajax({
        type: 'POST',
        url: data.url,
        cache: false,
        dataType: 'json',
        data: $.param(data.data),
        success: function(response, textStatus, error) {
          if (response.success) {
            // Success - what do we do now?
            if (response.message != '') {
              SystemMessage(response.message, true);
            }

            // Process the next item
            queue.next();
          } else {
            // Why did we fail?
            if (response.login) {
              // We were logged out
              LoginBox(response.message);
            } else {
              // Likely just an error that we want to report on
              // Remove the saving cog
              form.closest('.modal-dialog').find('.saving').remove();
              SystemMessageInline(response.message, form.closest('.modal'));
            }
          }
        },
        error: function(responseText) {
          // Remove the saving cog
          form.closest('.modal-dialog').find('.saving').remove();
          SystemMessage(responseText, false);
        },
      });
    },
    // When the queue completes naturally, execute this function.
    complete: function() {
      // Remove the save button
      form.closest('.modal-dialog').find('.saving').parent().remove();

      // Refresh the grids
      // (this is a global refresh)
      XiboRefreshAllGrids();

      if (form.data('nextFormUrl') !== undefined) {
        XiboFormRender(form.data().nextFormUrl);
      }
      // Close the dialog
      XiboDialogClose();
    },
  });

  let addedToQueue = false;

  // Build an array of id's to assign and an array to unassign
  const assign = [];
  const unassign = [];

  $.each(members.displays, function(name, value) {
    if (value == 1) {
      assign.push(name);
    } else {
      unassign.push(name);
    }
  });

  if (assign.length > 0 || unassign.length > 0) {
    const dataDisplays = {
      data: {},
      url: form.data().displayUrl,
    };
    dataDisplays.data[form.data().displayParam] = assign;
    dataDisplays.data[form.data().displayParamUnassign] = unassign;

    // Queue
    queue.add(dataDisplays);
    addedToQueue = true;
  }

  // Build an array of id's to assign and an array to unassign
  const assignDisplayGroup = [];
  const unassignDisplayGroup = [];

  $.each(members.displayGroups, function(name, value) {
    if (value == 1) {
      assignDisplayGroup.push(name);
    } else {
      unassignDisplayGroup.push(name);
    }
  });

  if (assignDisplayGroup.length > 0 || unassignDisplayGroup.length > 0) {
    const dataDisplayGroups = {
      data: {},
      url: form.data().displayGroupsUrl,
    };
    dataDisplayGroups.data[form.data().displayGroupsParam] = assignDisplayGroup;
    dataDisplayGroups.data[form.data().displayGroupsParamUnassign] =
      unassignDisplayGroup;

    // Queue
    queue.add(dataDisplayGroups);
    addedToQueue = true;
  }

  // Build an array of id's to assign and an array to unassign
  const assignUser = [];
  const unassignUser = [];

  $.each(members.users, function(name, value) {
    if (value == 1) {
      assignUser.push(name);
    } else {
      unassignUser.push(name);
    }
  });

  if (assignUser.length > 0 || unassignUser.length > 0) {
    const dataUsers = {
      data: {},
      url: form.data().userUrl,
    };
    dataUsers.data[form.data().userParam] = assignUser;
    dataUsers.data[form.data().userParamUnassign] = unassignUser;

    // Queue
    queue.add(dataUsers);
    addedToQueue = true;
  }

  // Build an array of id's to assign and an array to unassign
  const assignUserGroup = [];
  const unassignUserGroup = [];

  $.each(members.userGroups, function(name, value) {
    if (value == 1) {
      assignUserGroup.push(name);
    } else {
      unassignUserGroup.push(name);
    }
  });

  if (assignUserGroup.length > 0 || unassignUserGroup.length > 0) {
    const dataUserGroups = {
      data: {},
      url: form.data().userGroupsUrl,
    };
    dataUserGroups.data[form.data().userGroupsParam] = assignUserGroup;
    dataUserGroups.data[form.data().userGroupsParamUnassign] =
      unassignUserGroup;

    // Queue
    queue.add(dataUserGroups);
    addedToQueue = true;
  }

  if (!addedToQueue) {
    XiboDialogClose();
  } else {
    // Start the queue
    queue.start();
  }
}

// Callback for the media form
function mediaDisplayGroupFormCallBack() {

    var container = $("#FileAssociationsAssign");
    if (container.data().media == undefined) {
        container.data().media = {};
    }

    // Get starting items
    var includedItems = [];
    $('#FileAssociationsSortable').find('[data-media-id]').each((_i, el) => {
        includedItems.push($(el).data('mediaId'));
    });

    var mediaTable = $("#mediaAssignments").DataTable({ "language": dataTablesLanguage,
            serverSide: true, stateSave: true,
            searchDelay: 3000,
            "order": [[ 0, "asc"]],
            "filter": false,
            ajax: {
                "url": $("#mediaAssignments").data().url,
            "data": function(d) {
                $.extend(d, $("#mediaAssignments").closest(".XiboGrid").find(".FilterDiv form").serializeObject());
            }
        },
        "columns": [
            { "data": "name" },
            { "data": "mediaType" },
            {
                "sortable": false,
                "data": function(data, type, row, meta) {
                    if (type != "display")
                        return "";

                    // If media id is already added to the container
                    // Create span with disabled
                    if(includedItems.indexOf(data.mediaId) != -1) {
                        // Create a disabled span
                        return "<a href=\"#\" class=\"assignItem disabled\"><span class=\"fa fa-plus hidden\"></a>";
                    } else {
                        // Create a click-able span
                        return "<a href=\"#\" class=\"assignItem\"><span class=\"fa fa-plus\"></a>";
                    }
                }
            }
        ]
    });

    mediaTable.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Clicky on the +spans
        $(".assignItem:not(.disabled)", "#mediaAssignments").on("click", function() {
            // Get the row that this is in.
            var data = mediaTable.row($(this).closest("tr")).data();

            // Append to our media list
            container.data().media[data.mediaId] = 1;

            // Add to aux array
            includedItems.push(data.mediaId);

            // Disable add button
            $(this).parents("tr").addClass('disabled');
            // Hide plus button
            $(this).hide();

            // Construct a new list item for the lower list and append it.
            var newItem = $("<li/>", {
                "text": data.name,
                "data-media-id": data.mediaId,
                "class": "btn btn-sm btn-white"
            });

            newItem.appendTo("#FileAssociationsSortable");

            // Add a span to that new item
            $("<span/>", {
                "class": "fa fa-minus ml-1",
            }).appendTo(newItem);
        });
    });
    mediaTable.on('processing.dt', dataTableProcessing);

    // Make our little list sortable
    $("#FileAssociationsSortable").sortable();

    // Bind to the existing items in the list
    $("#FileAssociationsSortable").on("click", "li span", function () {
        var mediaId = $(this).parent().data().mediaId;
        container.data().media[mediaId] = 0;
        $(this).parent().remove();

        // Remove from aux array
        includedItems = includedItems.filter(item => item != mediaId);

        // Reload table
        mediaTable.ajax.reload();
    });

    // Bind to the filter
    $("#mediaAssignments").closest(".XiboGrid").find(".FilterDiv input, .FilterDiv select").change(function() {
        mediaTable.ajax.reload();
    });
}

function mediaAssignSubmit() {
    // Collect our media
    var container = $("#FileAssociationsAssign");

    // Build an array of id's to assign and an array to unassign
    var assign = [];
    var unassign = [];

    $.each(container.data().media, function(name, value) {
        if (value == 1)
            assign.push(name);
        else
            unassign.push(name);
    });

    assignMediaToCampaign(container.data().url, assign, unassign);
}

var assignMediaToCampaign = function(url, media, unassignMedia) {
    toastr.info("Assign Media", media);

    $.ajax({
        type: "post",
        url: url,
        cache: false,
        dataType: "json",
        data: {mediaId: media, unassignMediaId: unassignMedia},
        success: XiboSubmitResponse
    });
};

// Callback for the media form
function layoutFormCallBack() {

    var container = $("#FileAssociationsAssign");
    if (container.data().layout == undefined)
        container.data().layout = {};

    var layoutTable = $("#layoutAssignments").DataTable({ "language": dataTablesLanguage,
        serverSide: true, stateSave: true,
        searchDelay: 3000,
        "order": [[ 0, "asc"]],
        "filter": false,
        ajax: {
            "url": $("#layoutAssignments").data().url,
            "data": function(d) {
                $.extend(d, $("#layoutAssignments").closest(".XiboGrid").find(".FilterDiv form").serializeObject());
            }
        },
        "columns": [
            { "data": "layout" },
            {
                "sortable": false,
                "data": function(data, type, row, meta) {
                    if (type != "display")
                        return "";

                    // Create a click-able span
                    return "<a href=\"#\" class=\"assignItem\"><span class=\"fa fa-plus\"></a>";
                }
            }
        ]
    });

    layoutTable.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Clicky on the +spans
        $(".assignItem", "#layoutAssignments").click(function() {
            // Get the row that this is in.
            var data = layoutTable.row($(this).closest("tr")).data();

            // Append to our layout list
            container.data().layout[data.layoutId] = 1;

            // Construct a new list item for the lower list and append it.
            var newItem = $("<li/>", {
                "text": data.layout,
                "data-layout-id": data.layoutId,
                "class": "btn btn-sm btn-white"
            });

            newItem.appendTo("#FileAssociationsSortable");

            // Add a span to that new item
            $("<span/>", {
                "class": "fa fa-minus",
                click: function(){
                    container.data().layout[$(this).parent().data().layoutId] = 0;
                    $(this).parent().remove();
                }
            }).appendTo(newItem);
        });
    });
    layoutTable.on('processing.dt', dataTableProcessing);

    // Make our little list sortable
    $("#FileAssociationsSortable").sortable();

    // Bind to the existing items in the list
    $("#FileAssociationsSortable").find('li span').click(function () {
        container.data().layout[$(this).parent().data().layoutId] = 0;
        $(this).parent().remove();
    });

    // Bind to the filter
    $("#layoutAssignments").closest(".XiboGrid").find(".FilterDiv input, .FilterDiv select").change(function() {
        layoutTable.ajax.reload();
    });
}

function layoutAssignSubmit() {
    // Collect our layout
    var container = $("#FileAssociationsAssign");

    // Build an array of id's to assign and an array to unassign
    var assign = [];
    var unassign = [];

    $.each(container.data().layout, function(name, value) {
        if (value == 1)
            assign.push(name);
        else
            unassign.push(name);
    });

    assignLayoutToCampaign(container.data().url, assign, unassign);
}

var assignLayoutToCampaign = function(url, layout, unassignLayout) {
    toastr.info("Assign Layout", layout);

    $.ajax({
        type: "post",
        url: url,
        cache: false,
        dataType: "json",
        data: {layoutId: layout, unassignLayoutId: unassignLayout},
        success: XiboSubmitResponse
    });
};

function regionEditFormSubmit() {
    XiboFormSubmit($("#regionEditForm"), null, function(xhr, form) {

        if (xhr.success)
            window.location.reload();
    });
}

function userProfileEditFormOpen() {

    $("#qRCode").addClass("d-none");
    $("#recoveryButtons").addClass("d-none");
    $("#recoveryCodes").addClass("d-none");

    $("#twoFactorTypeId").on("change", function (e) {
        e.preventDefault();
        if ($("#twoFactorTypeId").val() == 2 && $('#userEditProfileForm').data().currentuser != 2) {
            $.ajax({
                url: $('#userEditProfileForm').data().setup,
                type: "GET",
                beforeSend: function () {
                    $("#qr").addClass('fa fa-spinner fa-spin loading-icon')
                },
                success: function (response) {
                    let qRCode = response.data.qRUrl;
                    $("#qrImage").attr("src", qRCode);
                },
                complete: function () {
                    $("#qr").removeClass('fa fa-spinner fa-spin loading-icon')
                }
            });
            $("#qRCode").removeClass("d-none");
        } else {
            $("#qRCode").addClass("d-none");
        }

        if ($("#twoFactorTypeId").val() == 0) {
            $("#recoveryButtons").addClass("d-none");
            $("#recoveryCodes").addClass("d-none");
        }

        if ($('#userEditProfileForm').data().currentuser != 0 && $("#twoFactorTypeId").val() != 0) {
            $("#recoveryButtons").removeClass("d-none");
        }
    });

    if ($('#userEditProfileForm').data().currentuser != 0) {
        $("#recoveryButtons").removeClass("d-none");
    }
    let generatedCodes = '';

    $('#generateCodesBtn').on("click", function (e) {
        $("#codesList").html("");
        $("#recoveryCodes").removeClass('d-none');
        $(".recBtn").attr("disabled", true).addClass("disabled");
        generatedCodes = '';

        $.ajax({
            url: $('#userEditProfileForm').data().generate,
            async: false,
            type: "GET",
            beforeSend: function () {
                $("#codesList").removeClass('card').addClass('fa fa-spinner fa-spin loading-icon');
            },
            success: function (response) {
                generatedCodes = JSON.parse(response.data.codes);
                $("#recoveryCodes").addClass('d-none');
                $(".recBtn").attr("disabled", false).removeClass("disabled");
                $('#showCodesBtn').click();
            },
            complete: function () {
                $("#codesList").removeClass('fa fa-spinner fa-spin loading-icon');
            }
        });
    });

    $('#showCodesBtn').on("click", function (e) {
        $(".recBtn").attr("disabled", true).addClass("disabled");
        $("#codesList").html("");
        $("#recoveryCodes").toggleClass('d-none');
        let codesList = [];

        $.ajax({
            url: $('#userEditProfileForm').data().show,
            type: "GET",
            data: {
                generatedCodes: generatedCodes,
            },
            success: function (response) {
                if (generatedCodes != '') {
                    codesList = generatedCodes;
                } else {
                    codesList = response.data.codes;
                }

                $('#twoFactorRecoveryCodes').val(JSON.stringify(codesList));
                $.each(codesList, function (index, value) {
                    $("#codesList").append(value + "<br/>");
                });
                $("#codesList").addClass('card');
                $(".recBtn").attr("disabled", false).removeClass("disabled");
            }
        });
    });
}

function tagsWithValues(formId) {
    $('#tagValue, label[for="tagValue"], #tagValueRequired').addClass("d-none");
    $('#tagValueContainer').hide();

    let tag;
    let tagWithOption = '';
    let tagN = '';
    let tagV = '';
    let tagOptions = [];
    let tagIsRequired = 0;

    let formSelector = '#' + formId + ' input#tags' + ', #' + formId + ' input#tagsToAdd';

    $(formSelector).on('beforeItemAdd', function(event) {
        $('#tagValue').html('');
        $('#tagValueInput').val('');
        tag = event.item;
        tagOptions = [];
        tagIsRequired = 0;
        tagN = tag.split('|')[0];
        tagV = tag.split('|')[1];

        if ($(formSelector).val().indexOf(tagN) >= 0) {
            // if we entered a Tag that already exists there are two options
            // exists without value and entered without value - handled automatically allowDuplicates = false
            // as we allow entering Tags with value, we need additional handling for that

            // go through tagsinput items and return the one that matches Tag name about to be added
            let item = $(formSelector).tagsinput('items').filter(item => {
                return item.split('|')[0].toLowerCase() === tagN.toLowerCase()
            });

            // remove the existing Tag from tagsinput before adding the new one
            $(formSelector).tagsinput('remove', item.toString());
        }

        if ($(formSelector).val().indexOf(tagN) === -1 && tagV === undefined) {
            $.ajax({
                url: $('form#'+formId).data().gettag,
                type: "GET",
                data: {
                    name: tagN,
                },
                beforeSend: function () {
                    $("#loadingValues").addClass('fa fa-spinner fa-spin loading-icon')
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.tag != null) {
                            tagOptions = JSON.parse(response.data.tag.options);
                            tagIsRequired = response.data.tag.isRequired;

                            if (tagOptions != null && tagOptions != []) {
                                $('#tagValue, label[for="tagValue"]').removeClass("d-none");

                                if ($('#tagValue option[value=""]').length <= 0) {
                                    $('#tagValue')
                                        .append($("<option></option>")
                                            .attr("value", '')
                                            .text(''));
                                }

                                $.each(tagOptions, function (key, value) {
                                    if ($('#tagValue option[value='+value+']').length <= 0) {
                                        $('#tagValue')
                                            .append($("<option></option>")
                                                .attr("value", value)
                                                .text(value));
                                    }
                                });

                                $('#tagValue').focus();
                            } else {
                                // existing Tag without specified options (values)
                                $('#tagValueContainer').show();

                                // if the isRequired flag is set to 0 change the helpText to be more user friendly.
                                if (tagIsRequired === 0) {
                                    $('#tagValueInput').parent().find('span.help-block').text(translations.tagInputValueHelpText)
                                } else {
                                    $('#tagValueInput').parent().find('span.help-block').text(translations.tagInputValueRequiredHelpText)
                                }

                                $('#tagValueInput').focus();
                            }
                        } else {
                            // new Tag
                            $('#tagValueContainer').show();
                            $('#tagValueInput').focus();

                            // isRequired flag is set to 0 (new Tag) change the helpText to be more user friendly.
                            $('#tagValueInput').parent().find('span.help-block').text(translations.tagInputValueHelpText)
                        }
                    }
                },
                complete: function () {
                    $("#loadingValues").removeClass('fa fa-spinner fa-spin loading-icon')
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error(jqXHR, textStatus, errorThrown);
                }
            });
        }
    });

    $(formSelector).on('itemAdded', function(event) {
        if (tagOptions != null && tagOptions !== []) {
            $('#tagValue').focus();
        }
    });

    $(formSelector).on('itemRemoved', function(event) {
        if(tagN === event.item) {
            $('#tagValueRequired, label[for="tagValue"]').addClass('d-none');
            $('.save-button').prop('disabled', false);
            $('#tagValue').html('').addClass("d-none");
            $('#tagValueInput').val('');
            $('#tagValueContainer').hide();
            tagN = '';
        } else if ($(".save-button").is(":disabled")) {
            // do nothing with jQuery
        } else {
            $('#tagValue').html('').addClass("d-none");
            $('#tagValueInput').val('');
            $('#tagValueContainer').hide();
            $('label[for="tagValue"]').addClass("d-none");
        }
    });

    $("#tagValue").on("change", function (e) {
        e.preventDefault();
        tagWithOption = tagN + '|' + $(this).val();

        // additional check, helpful for multi tagging.
        if (tagN != '') {
            if (tagIsRequired === 0 || (tagIsRequired === 1 && $(this).val() !== '')) {
                $(formSelector).tagsinput('add', tagWithOption);
                $(formSelector).tagsinput('remove', tagN);
                $('#tagValue').html('').addClass("d-none");
                $('#tagValueRequired, label[for="tagValue"]').addClass('d-none');
                $('.save-button').prop('disabled', false);
            } else {
                $('#tagValueRequired').removeClass('d-none');
                $('#tagValue').focus();
            }
        }
    });

    $('#tagValue').blur(function() {
        if($(this).val() === '' && tagIsRequired === 1 ) {
            $('#tagValueRequired').removeClass('d-none');
            $('#tagValue').focus();
            $('.save-button').prop('disabled', true);
        } else {
            $('#tagValue').html('').addClass("d-none");
            $('label[for="tagValue"]').addClass("d-none");
        }
    });

    $('#tagValueInput').on('keypress focusout', function(event) {

        if ( (event.keyCode === 13 || event.type === 'focusout') && tagN != '') {
            event.preventDefault();
            let tagInputValue = $(this).val();
            tagWithOption = (tagInputValue !== '') ? tagN + '|' + tagInputValue : tagN;

            if (tagIsRequired === 0 || (tagIsRequired === 1 && tagInputValue !== '')) {
                $(formSelector).tagsinput('add', tagWithOption);
                // remove only if we have value (otherwise it would be left empty)
                if (tagInputValue !== '') {
                    $(formSelector).tagsinput('remove', tagN);
                }

                $('#tagValueInput').val('');
                $('#tagValueContainer').hide();
                $('#tagValueRequired').addClass('d-none');
                $('.save-button').prop('disabled', false);
            } else {
                $('#tagValueContainer').show();
                $('#tagValueRequired').removeClass('d-none');
                $('#tagValueInput').focus();
            }
        }
    })
}



/**
 * Called when the ACL form is opened on Users/User Groups
 * @param dialog
 */
function featureAclFormOpen(dialog) {
    // Start everything collapsed.
    $(dialog).find("tr.feature-row").hide();

    // Bind to clicking on the feature header cells
    $(dialog).find("td.feature-group-header-cell").on("click", function() {
        // Toggle state
        var $header = $(this);
        var isOpen = $header.hasClass("open");

        if (isOpen) {
            // Make closed
            $header.find(".feature-group-description").show();
            $header.find("i.fa").removeClass("fa-arrow-circle-up").addClass("fa fa-arrow-circle-down");
            $header.closest("tbody.feature-group").find("tr.feature-row").hide();
            $header.removeClass("open").addClass("closed");
        } else {
            // Make open
            $header.find(".feature-group-description").hide();
            $header.find("i.fa").removeClass("fa-arrow-circle-down").addClass("fa fa-arrow-circle-up");
            $header.closest("tbody.feature-group").find("tr.feature-row").show();
            $header.removeClass("closed").addClass("open");
        }
    }).each(function(index, el) {
        // Set the initial state of the 3 way checkboxes
        setFeatureGroupCheckboxState($(this));
    });

    // Bind to checkbox change event
    $(dialog).find("input[name='features[]']").on("click", function() {
        setFeatureGroupCheckboxState($(this));
    });

    // Bind to group checkboxes to check/uncheck all below.
    $(dialog).find("input.feature-select-all").on("click", function() {
        // Force this down to all child checkboxes
        $(this)
            .closest("tbody.feature-group")
            .find("input[name='features[]']")
            .prop("checked", $(this).is(":checked"));
    });
}

/**
 * Set the checkbox state based on the adjacent features
 * @param triggerElement
 */
function setFeatureGroupCheckboxState(triggerElement) {
    // collect up the checkboxes belonging to the same group
    var $featureGroup = triggerElement.closest("tbody.feature-group");
    var countChecked = $featureGroup.find("input[name='features[]']:checked").length;
    var countTotal = $featureGroup.find("input[name='features[]']").length;
    setCheckboxState(countChecked, countTotal, $featureGroup, '.feature-select-all')

    // collect up the inherit checkboxes belonging to the same group
    var countInheritChecked = $featureGroup.find("input.inherit-group:checked").length;
    var countInheritTotal = $featureGroup.find("input.inherit-group").length;
    setCheckboxState(countInheritChecked, countInheritTotal, $featureGroup, '.inherit-group-all')
}

/**
 * Set checkbox state helper function
 * @param count
 * @param countTotal
 * @param $selector
 * @param checkboxClass
 */
function setCheckboxState(count, countTotal, $selector, checkboxClass)
{
    if (count <= 0) {
        $selector.find(checkboxClass)
            .prop("checked", false)
            .prop("indeterminate", false);
    } else if (count === countTotal) {
        $selector.find(checkboxClass)
            .prop("checked", true)
            .prop("indeterminate", false);
    } else {
        $selector.find(checkboxClass)
            .prop("checked", false)
            .prop("indeterminate", true);
    }
}

function userApprovedApplicationsFormOpen(dialog) {
    $('.revokeAccess').on('click', function (e) {
        var $this = $(this);
        var clientKey = $this.data('applicationKey');
        var userId = $this.data('applicationUser');

        $.ajax({
            url: revokeApplicationAccess.replace(':id', clientKey).replace(':userId', userId),
            type: "DELETE",
            success: function (res) {
                if (res.success) {
                    $this.closest('tr').remove();
                    toastr.success(res.message);
                } else {
                    toastr.error(res.message);
                }
            }
        });
    })
}

function folderMoveSubmit() {
    XiboFormSubmit($("#moveFolderForm"), null, function(xhr, form) {
        if (xhr.success) {
            $('#container-folder-tree').jstree(true).refresh()
        }
    });
}