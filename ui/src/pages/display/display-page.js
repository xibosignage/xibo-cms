import './display-page.scss';

$(function() {
  if (!folderViewEnabled) {
    disableFolders();
  }

  const table = $('#displays').DataTable({
    language: dataTablesLanguage,
    dom: dataTablesTemplate,
    serverSide: true,
    stateSave: true,
    stateDuration: 0,
    responsive: true,
    stateLoadCallback: dataTableStateLoadCallback,
    stateSaveCallback: dataTableStateSaveCallback,
    filter: false,
    searchDelay: 3000,
    order: [[1, 'asc']],
    ajax: {
      url: displaySearchURL,
      data: function(dataToSend) {
        // Make a new object so as not to destroy the input.
        const data = {};
        data.draw = dataToSend.draw;
        data.length = dataToSend.length;
        data.start = dataToSend.start;
        data.order = dataToSend.order;
        data.columns = [];

        $.each(dataToSend.columns, function(index, e) {
          const col = {};
          col.data = e.data;
          if (e.name != null && e.name != '') {
            col.name = e.name;
          }
          data.columns.push(col);
        });

        $.extend(data, $('#displays').closest('.XiboGrid')
          .find('.FilterDiv form').serializeObject());

        return data;
      },
    },
    createdRow: function(row, data, index) {
      if (data.mediaInventoryStatus === 1) {
        $(row).addClass('table-success');
      } else if (data.mediaInventoryStatus === 2) {
        $(row).addClass('table-danger');
      } else {
        $(row).addClass('table-warning');
      }
    },
    columns: [
      {data: 'displayId', responsivePriority: 2},
      {data: 'display', responsivePriority: 2},
      {data: 'displayType', responsivePriority: 2},
      {data: 'address', visible: false, responsivePriority: 5},
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
          } else if (data == 2) {
            icon = 'fa-times';
          } else {
            icon = 'fa-cloud-download';
          }

          return '<span class="fa ' + icon +
            '" title="' + (row.statusDescription) + '"></span>';
        },
      },
      {
        data: 'licensed',
        render: dataTableTickCrossColumn,
        responsivePriority: 3,
      },
      {
        data: 'currentLayout',
        visible: false,
        sortable: false,
        responsivePriority: 5,
      },
      {
        data: 'storageAvailableSpace',
        responsivePriority: 5,
        visible: false,
        render: function(data, type, row) {
          if (type != 'display' && type != 'export') {
            return data;
          }

          return row.storageAvailableSpaceFormatted;
        },
      },
      {
        data: 'storageTotalSpace',
        responsivePriority: 5,
        visible: false,
        render: function(data, type, row) {
          if (type != 'display' && type != 'export') {
            return data;
          }

          return row.storageTotalSpaceFormatted;
        },
      },
      {
        data: 'storagePercentage',
        visible: false,
        sortable: false,
        responsivePriority: 5,
      },
      {data: 'description', visible: false, responsivePriority: 4},
      {data: 'orientation', visible: false, responsivePriority: 6},
      {data: 'resolution', visible: false, responsivePriority: 6},
      // Add tags only if enabled
      ...(taggingEnabled ? [{
        name: 'tags',
        responsivePriority: 3,
        sortable: false,
        visible: false,
        data: dataTableCreateTags,
      }] : []),
      {data: 'defaultLayout', visible: false, responsivePriority: 4},
      {
        data: 'incSchedule',
        render: dataTableTickCrossColumn, visible: false, responsivePriority: 5,
      },
      {
        data: 'emailAlert',
        render: dataTableTickCrossColumn, visible: false, responsivePriority: 5,
      },
      {
        data: 'loggedIn',
        render: dataTableTickCrossColumn, responsivePriority: 3,
      },
      {
        data: 'lastAccessed',
        render: dataTableDateFromUnix, responsivePriority: 4,
      },
      {
        name: 'displayProfileId',
        responsivePriority: 5,
        data: function(data, type) {
          return data.displayProfile;
        },
        visible: false,
      },
      {
        name: 'clientSort',
        responsivePriority: 4,
        data: function(data) {
          if (data.clientType === 'lg') {
            data.clientType = 'webOS';
          }

          return data.clientType + ' ' +
            data.clientVersion + '-' +
            data.clientCode;
        },
        visible: false,
      },
      {
        data: 'clientCode',
        responsivePriority: 3,
        render: function(data, type) {
          if (type !== 'display') {
            return (data < playerVersionSupport) ?
              displayPageTrans.no : displayPageTrans.yes;
          }
          return '<span class=\'fa ' +
            (data < playerVersionSupport ? 'fa-times' : 'fa-check') +
            '\'></span>';
        },
        visible: false,
      },
      {data: 'deviceName', visible: false, responsivePriority: 5},
      {data: 'clientAddress', visible: false, responsivePriority: 6},
      {data: 'macAddress', responsivePriority: 5},
      {data: 'timeZone', visible: false, responsivePriority: 5},
      {
        data: 'languages',
        visible: false,
        responsivePriority: 5,
        render: function(data, type) {
          if (type !== 'display') {
            return data;
          }

          let returnData = '';
          if (typeof data !== undefined && data != null) {
            const arrayOfTags = data.split(',');
            returnData += '<div class="permissionsDiv">';
            for (let i = 0; i < arrayOfTags.length; i++) {
              const name = arrayOfTags[i];
              if (name !== '') {
                returnData += '<li class="badge">' +
                  name + '</span></li>';
              }
            }
            returnData += '</div>';
          }
          return returnData;
        },
      },
      {data: 'latitude', visible: false, responsivePriority: 6},
      {data: 'longitude', visible: false, responsivePriority: 6},
      {
        data: 'screenShotRequested',
        render: dataTableTickCrossColumn,
        visible: false, name: 'screenShotRequested',
        responsivePriority: 7,
      },
      {
        name: 'thumbnail',
        responsivePriority: 4,
        orderable: false,
        data: function(data, type) {
          if (type != 'display') {
            return data.thumbnail;
          }

          if (data.thumbnail != '') {
            return '<a class="display-screenshot-container" ' +
              'data-toggle="lightbox" data-type="image" href="' +
              data.thumbnail + '"><img class="display-screenshot" src="' +
              data.thumbnail + '" data-display-id="' + data.displayId +
              '" data-type="' + data.clientType + '" data-client-code="' +
              data.clientCode + '" /></a>';
          } else {
            return '';
          }
        },
        visible: false,
      },
      {
        data: 'isCmsTransferInProgress',
        render: dataTableTickCrossColumn,
        visible: false,
        name: 'isCmsTransferInProgress',
      },
      {
        name: 'bandwidthLimit',
        responsivePriority: 5,
        data: null,
        render: {
          _: 'bandwidthLimit',
          display: 'bandwidthLimitFormatted',
          sort: 'bandwidthLimit',
        },
        visible: false,
      },
      {
        data: 'lastCommandSuccess',
        responsivePriority: 6,
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
            icon = 'fa-question';
          }

          return '<span class=\'fa ' + icon + '\'></span>';
        },
        visible: false,
      },
      {
        data: 'xmrChannel',
        responsivePriority: 6,
        render: function(data, type, row) {
          if (type === 'export') {
            return (data !== null && data !== '') ? 1 : 0;
          }

          if (type != 'display') {
            return data;
          }

          let icon = '';
          if (data != null && data != '') {
            icon = 'fa-check';
          } else {
            icon = 'fa-times';
          }

          return '<span class=\'fa ' + icon + '\'></span>';
        },
        visible: false,
      },
      {
        data: 'commercialLicence',
        name: 'commercialLicence',
        responsivePriority: 5,
        render: function(data, type, row) {
          if (type != 'display') {
            return data;
          }

          let icon = '';
          if (data == 3) {
            return 'N/A';
          } else {
            if (data == 1) {
              icon = 'fa-check';
            } else if (data == 0) {
              icon = 'fa-times';
            } else if (data == 2) {
              icon = 'fa-clock-o';
            }

            return '<span class="fa ' + icon +
              '" title="' + (row.commercialLicenceDescription) +
              '"></span>';
          }
        },
        visible: false,
      },
      {
        name: 'remote',
        data: null,
        responsivePriority: 4,
        render: function(data, type, row) {
          if (type === 'display') {
            let html = '<div class=\'remote-icons\'>';
            if (
              SHOW_DISPLAY_AS_VNCLINK !== '' &&
              row.clientAddress != null &&
              row.clientAddress !== ''
            ) {
              const link = SHOW_DISPLAY_AS_VNCLINK
                .replace('%s', row.clientAddress);
              html += '<a href="' + link + '" title="' +
                VNCtoThisDisplay + '" target="' +
                SHOW_DISPLAY_AS_VNC_TGT + '">' +
                '<i class="fa fa-eye"></i></a>';
            }

            if (row.teamViewerLink !== '') {
              html += '<a href="' + row.teamViewerLink +
                '" title="' + TeamViewertoThisDisplay + '" target="_blank">' +
                '<img src="' + publicPath +
                'theme/default/img/remote_icons/teamviewer.png"' +
                ' alt="TeamViewer Icon"></a>';
            }

            if (row.webkeyLink !== '') {
              html += '<a href="' + row.webkeyLink + '" title="' +
                WebkeytoThisDisplay + '" target="_blank">' +
                '<img src="' + publicPath +
                'theme/default/img/remote_icons/webkey.png" ' +
                'alt="Webkey Icon"></a>';
            }

            return html + '</div>';
          } else if (type === 'export') {
            if (row.teamViewerLink !== '') {
              return 'TeamViewer: ' + row.teamViewerLink;
            }
            if (row.webkeyLink !== '') {
              return 'Webkey: ' + row.webkeyLink;
            }
            if (row.teamViewerLink === '' && row.webkeyLink === '') {
              return '';
            }
          } else {
            return '';
          }
        },
        visible: true,
        orderable: false,
      },
      {
        data: 'groupsWithPermissions',
        visible: false,
        responsivePriority: 10,
        render: dataTableCreatePermissions,
      },
      {data: 'screenSize', visible: false, responsivePriority: 6},
      {
        data: 'isMobile',
        render: dataTableTickCrossColumn,
        visible: false,
        name: 'isMobile',
      },
      {
        data: 'isOutdoor',
        render: dataTableTickCrossColumn,
        visible: false,
        name: 'isOutdoor',
      },
      {data: 'ref1', visible: false, responsivePriority: 6},
      {data: 'ref2', visible: false, responsivePriority: 6},
      {data: 'ref3', visible: false, responsivePriority: 6},
      {data: 'ref4', visible: false, responsivePriority: 6},
      {data: 'ref5', visible: false, responsivePriority: 6},
      {data: 'customId', visible: false, responsivePriority: 6},
      {data: 'costPerPlay', visible: false, responsivePriority: 6},
      {data: 'impressionsPerPlay', visible: false, responsivePriority: 6},
      {data: 'createdDt', visible: false, responsivePriority: 6},
      {data: 'modifiedDt', visible: false, responsivePriority: 6},
      {
        data: 'countFaults',
        name: 'countFaults',
        responsivePriority: 3,
        render: function(data, type, row) {
          if (row.clientCode < 300) {
            return '';
          }

          if (type !== 'display') {
            return data;
          }

          if (data > 0) {
            return '<span class="badge" ' +
              'style="background-color: red; color: white">' +
              (row.countFaults) + '</span>';
          } else {
            return '';
          }
        },
      },
      {data: 'osVersion', visible: false, responsivePriority: 6},
      {data: 'osSdk', visible: false, responsivePriority: 6},
      {data: 'manufacturer', visible: false, responsivePriority: 6},
      {data: 'brand', visible: false, responsivePriority: 6},
      {data: 'model', visible: false, responsivePriority: 6},
      {
        orderable: false,
        responsivePriority: 1,
        data: dataTableButtonsColumn,
      },
    ],
  });

  table.on('draw', function(e, settings) {
    dataTableDraw(e, settings, function() {
      const target = $('#' + e.target.id);
      const $mapController = target.closest('.XiboGrid')
        .find('.map-controller');
      const $listController = target.closest('.XiboGrid')
        .find('.list-controller');

      // Move and show map button inside of the table container
      if ($mapController.length > 0 && target.closest('.dataTables_wrapper')
        .find('.dataTables_folder .map-controller').length == 0) {
        $mapController.appendTo('.dataTables_folder');
        $mapController.removeClass('d-none').addClass('d-inline-flex');
      }
      // Move and show list button inside of the table container
      if (
        $listController.length > 0 &&
        target.closest('.dataTables_wrapper',
        ).find('.dataTables_folder .list-controller').length == 0) {
        $listController.appendTo('.dataTables_folder');
        $listController.removeClass('d-none').addClass('d-inline-flex');
      }
    });
  });
  table.on(
    'draw',
    {
      form: $('#displays').closest('.XiboGrid').find('.FilterDiv form'),
    },
    dataTableCreateTagEvents,
  );
  table.on('processing.dt', dataTableProcessing);
  dataTableAddButtons(
    table,
    $('#displays_wrapper').find('.dataTables_buttons'),
  );

  $('#refreshGrid').on('click', function() {
    table.ajax.reload();
  });

  // </editor-fold>

  // <editor-fold desc="The button click event displaying the map or grid>

  // Detect the dimensions of our container and set to match.
  let map;
  let displayIdCache = [];
  let markerClusterGroup;
  const $displayMap = $('#display-map');
  const $mapBtn = $('#map_button');
  const $listBtn = $('#list_button');
  const $mapParent = $displayMap.parent();
  const $dataTablesFolder = $('.dataTables_folder');

  $listBtn.hide();

  $mapBtn.on('click', function() {
    if (!map) {
      initializeMap();
    } else {
      setTimeout(function() {
        addMarkersToMap(map.getBounds().toBBoxString());
      }, 500);
    }
    $('.map-legend').show();
    $displayMap.show();
    $mapBtn.hide();
    $listBtn.show();
    $dataTablesFolder.siblings().hide();
    $dataTablesFolder.parent().siblings().hide();
  });

  $listBtn.on('click', function() {
    $('.map-legend').hide();
    $displayMap.hide();
    $listBtn.hide();
    $mapBtn.show();
    $dataTablesFolder.siblings().show();
    $dataTablesFolder.parent().siblings().show();
  });

  // </editor-fold>


  // <editor-fold desc="Leaflet Map>

  // Map resizing when folder is toggled
  window.refreshDisplayMap = function() {
    if (map) {
      // is display map visible??
      if ($displayMap.is(':visible')) {
        map.invalidateSize();
      } else {
        map.setView(map.getCenter(), map.getZoom());
      }
    }
  };

  // Initialise and build map
  function initializeMap() {
    $displayMap.width($mapParent.width() - 10);
    $displayMap.height($(window).height() - 100);

    // Create the map
    map = L.map('display-map', {
      center: [mapConfig.setArea.lat, mapConfig.setArea.long],
      zoom: mapConfig.setArea.zoom,
      fullscreenControl: true,
      fullscreenControlOptions: {
        position: 'topleft',
      },
    });

    // Tile layer
    const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    // Print button
    L.easyPrint({
      tileLayer: tiles,
      sizeModes: ['Current', 'A4Landscape', 'A4Portrait'],
      filename: 'Displays on Map',
      hideControlContainer: true,
    }).addTo(map);

    // Create the marker cluster group
    markerClusterGroup = L.markerClusterGroup({
      maxClusterRadius: function(mapZoom) {
        return mapZoom > 9 ? 20 : 80;
      },

      // This defines the icon appearance of the cluster markers
      iconCreateFunction: function(cluster) {
        let upToDate = 0;
        let outOfDate = 0;
        let downloading = 0;

        const children = cluster.getAllChildMarkers();
        for (const marker of children) {
          switch (marker.mediaInventoryStatus) {
            case 1:
              upToDate++;
              break;
            case 3:
              outOfDate++;
              break;
            default:
              downloading++;
              break;
          }
        }

        // Create a div showing number of displays
        // by status in the cluster group
        const pieHtml = createPieChart(
          [upToDate, outOfDate, downloading],
          [
            'rgba(181, 226, 140, 0.9)',
            'rgba(243, 194, 18, 0.9)',
            'rgba(219, 70, 79, 0.9)',
          ],
        );

        // Create custom icons for the cluster markers
        return L.divIcon({
          html: pieHtml,
          className: '',
          iconSize: L.point(40, 40),
        });
      },
    });

    let bounds = map.getBounds().toBBoxString();

    map.on('moveend', _.debounce(function() {
      bounds = map.getBounds().toBBoxString();
      // is display map visible??
      const isDisplayMapVisible = $displayMap.is(':visible');
      addMarkersToMap(bounds, !isDisplayMapVisible);
    }, 500));

    map.on('resize', function() {
      map.invalidateSize();
    });

    // Get display points and add to the map
    // Do not clear layers
    addMarkersToMap(bounds, false);

    // Bind the filter form
    $('.XiboGrid').find('.XiboFilter form input').on('keyup', function() {
      addMarkersToMap(bounds);
    });
    $('.XiboGrid').find('.XiboFilter form select').on('change', function() {
      addMarkersToMap(bounds);
    });

    // Hide map/ Show Display List
    $displayMap.hide();
  }

  // Add display markers to the cluster group
  function addMarkersToMap(bounds, clear = true) {
    if (clear) {
      markerClusterGroup.clearLayers();
      displayIdCache = [];
    }

    if (!$displayMap.is(':visible')) {
      return;
    }

    // Make an ajax request for the displays feature
    // Load GeoJSON data and add it to the marker cluster group
    $.ajax($displayMap.data('displaysUrl'), {
      method: 'GET',
      data: $('.XiboGrid').find('.XiboFilter form')
        .serialize() + '&bounds=' + bounds,
      success: function(response) {
        // displays
        if (response.features.length > 0) {
          // Define icons for display
          const uptoDateLoggedInIcon = L.icon({
            iconUrl: publicPath + 'dist/assets/map-marker-green-check.png',
            iconRetinaUrl: publicPath +
              'dist/assets/map-marker-green-check-2x.png',
            iconSize: [24, 40],
            iconAnchor: [12, 40],
          });
          const uptoDateLoggedOutIcon = L.icon({
            iconUrl: publicPath + 'dist/assets/map-marker-green-cross.png',
            iconRetinaUrl: publicPath +
              'dist/assets/map-marker-green-cross-2x.png',
            iconSize: [24, 40],
            iconAnchor: [12, 40],
          });
          const outOfDateLoggedInIcon = L.icon({
            iconUrl: publicPath + 'dist/assets/map-marker-yellow-check.png',
            iconRetinaUrl: publicPath +
              'dist/assets/map-marker-yellow-check-2x.png',
            iconSize: [24, 40],
            iconAnchor: [12, 40],
          });
          const outOfDateLoggedOutIcon = L.icon({
            iconUrl: publicPath + 'dist/assets/map-marker-yellow-cross.png',
            iconRetinaUrl: publicPath +
              'dist/assets/map-marker-yellow-cross-2x.png',
            iconSize: [24, 40],
            iconAnchor: [12, 40],
          });
          const downloadingLoggedInIcon = L.icon({
            iconUrl: publicPath + 'dist/assets/map-marker-red-check.png',
            iconRetinaUrl: publicPath +
              'dist/assets/map-marker-red-check-2x.png',
            iconSize: [24, 40],
            iconAnchor: [12, 40],
          });
          const downloadingLoggedOutIcon = L.icon({
            iconUrl: publicPath + 'dist/assets/map-marker-red-cross.png',
            iconRetinaUrl: publicPath +
              'dist/assets/map-marker-red-cross-2x.png',
            iconSize: [24, 40],
            iconAnchor: [12, 40],
          });

          // Loop through features (GeoJSON data) and
          // add each marker to the cluster
          // eslint-disable-next-line no-unused-vars
          const feature = L.geoJSON(response, {
            pointToLayer: function(feature, latlng) {
              const icons = {
                1: {
                  true: uptoDateLoggedInIcon,
                  false: uptoDateLoggedOutIcon,
                },
                3: {
                  true: outOfDateLoggedInIcon,
                  false: outOfDateLoggedOutIcon,
                },
                default: {
                  true: downloadingLoggedInIcon,
                  false: downloadingLoggedOutIcon,
                },
              };

              // The value of "mediaInventoryStatus" and
              // "loggedIn" determines the "icon"
              const loggedIn = feature.properties.loggedIn ?
                true : false;
              const iconType =
                icons[feature.properties.mediaInventoryStatus] || icons.default;
              const icon = iconType[loggedIn];

              const options = {
                icon: icon,
              };

              let popup = '<strong>' + feature.properties.display + '</strong>';
              if (feature.properties.orientation) {
                popup += '<br/><div style="width: 180px;"><span>Orientation: ' +
                  feature.properties.orientation + '</span></div>';
              }
              if (feature.properties.status) {
                popup += '<div style="width: 180px;"><span>Status: ' +
                  feature.properties.status + '</span>';
                if (feature.properties.loggedIn) {
                  popup += '<span> (Logged in) </span></div>';
                } else {
                  popup += '<span> (Not logged in) </span></div>';
                }
              }
              if (feature.properties.displayProfile) {
                popup += '<div style="width: 180px;"><span>Display profile: ' +
                  feature.properties.displayProfile + '</span></div>';
              }
              if (feature.properties.resolution) {
                popup += '<div style="width: 180px;"><span>Resolution: ' +
                  feature.properties.resolution + '</span></div>';
              }
              if (feature.properties.lastAccessed) {
                const lastAccessed =
                  moment(feature.properties.lastAccessed, 'X').tz ?
                    moment(feature.properties.lastAccessed, 'X').tz(timezone)
                      .format(jsDateFormat) :
                    moment(feature.properties.lastAccessed, 'X')
                      .format(jsDateFormat);

                popup += '<div style="width: 180px;"><span>Last accessed: ' +
                  lastAccessed + '</span></div>';
              }
              if (feature.properties.thumbnail) {
                popup += '<div style="width: 180px;">' +
                  '<img class="display-screenshot" src="' +
                  feature.properties.thumbnail + '" /></div>';
              }

              if (!displayIdCache.includes(feature.properties.displayId)) {
                // Cache displayId
                displayIdCache.push(feature.properties.displayId);

                const marker = L.marker(latlng, options);

                // Add the inventory status to each marker so that we can count
                // the status based displays in iconCreateFunction
                marker.mediaInventoryStatus =
                  feature.properties.mediaInventoryStatus;

                // Add a marker
                return marker.bindPopup(popup)
                  .openPopup()
                  .addTo(markerClusterGroup);
              }
            },
          });
        }
      },
    });

    // Add the cluster group to the map
    markerClusterGroup.addTo(map);

    markerClusterGroup.on('clustermouseover', function(event) {
      const clusterMarkers = event.layer.getAllChildMarkers();

      let upToDate = 0;
      let outOfDate = 0;
      let downloading = 0;

      clusterMarkers.forEach(function(marker) {
        switch (marker.mediaInventoryStatus) {
          case 1:
            upToDate++;
            break;
          case 3:
            outOfDate++;
            break;
          default:
            downloading++;
            break;
        }
      });

      let popContent = '<div><strong>Total number of displays</strong>';
      const statuses = [
        {count: upToDate, text: 'Up to date'},
        {count: outOfDate, text: 'Out of date'},
        {count: downloading, text: 'Downloading'},
      ];
      for (const {count, text} of statuses) {
        if (count > 0) {
          popContent += `<div>${text}: ${count}</div>`;
        }
      }
      popContent += '</div>';

      // eslint-disable-next-line no-unused-vars
      const popup = L.popup()
        .setLatLng(event.latlng)
        .setContent(popContent)
        .openOn(map);
    }).on('clustermouseout', function(event) {
      map.closePopup();
    }).on('clusterclick', function(event) {
      map.closePopup();
    });
  }

  // Creating a pie chart for cluster childrens using HTML, CSS
  const createPieChart = function(data, colors) {
    // Get the total of all the data
    const total = data.reduce(function(a, b) {
      return a + b;
    });

    // Get the percentage of each data point
    const percentages = data.map(function(d) {
      return d / total;
    });

    // Create the pie chart
    const pie = $('<div></div>');
    pie.css('width', '30px');
    pie.css('height', '30px');
    pie.css('border-radius', '50%');
    pie.css('display', 'flex');
    pie.css('align-items', 'center');
    pie.css('justify-content', 'center');

    // Create conic-gradient for each data point
    let gradient = 'conic-gradient(';
    let percentageSum = 0;
    percentages.forEach(function(percentages, i) {
      const color = colors[i];
      const percentStart = percentageSum * 100;
      const percentEnd = percentStart + percentages * 100;
      percentageSum += percentages;
      gradient +=
        color + ' ' + percentStart + '%, ' +
        color + ' ' + percentEnd + '%, ';
    });
    gradient += 'white 0%)';

    // Set the pie chart's background to the conic-gradient
    pie.css('background', gradient);
    pie.css('box-shadow', '5px 5px 10px rgba(0, 0, 0, 0.3)');
    pie.append(
      '<div style="color: black; font-weight: bold;">' + total + '</div>',
    );
    return $('<div />').append(pie.clone()).html();
  };

  // </editor-fold>
});

window.displayRequestScreenshotFormSubmit = function() {
  $('#displayRequestScreenshotForm').submit();
  XiboDialogClose();

  if (showThumbnailColumn == 1) {
    const table = $('#displays').DataTable();
    if (!table.column(['thumbnail:name']).visible()) {
      table.columns(
        ['screenShotRequested:name', 'thumbnail:name'],
      ).visible(true);
    }
  }
};


window.setDefaultMultiSelectFormOpen = function(dialog) {
  console.debug('Multi-select form opened for default layout');

  // Inject a list of layouts into the form, in a drop down.
  const $select =
    $('<select name="layoutId" class="form-control" data-search-url="' +
      layoutSearchURL + '" data-search-term="layout" ' +
      'data-search-term-tags="tags" data-id-property="layoutId" ' +
      'data-text-property="layout">');
  $select.on('change', function(ev) {
    console.debug('Setting commit data to ' + $(ev.currentTarget).val());
    dialog.data().commitData = {layoutId: $(ev.currentTarget).val()};
  });

  // Add the list to the body.
  $(dialog).find('.modal-body').append($select);

  makePagedSelect($select, dialog);
};

window.displayFormLicenceCheckSubmit = function(form) {
  // Display commercial licence table column
  $('table#displays').DataTable().column('commercialLicence:name')
    .visible(true);

  // Submit form
  form.submit();
};

window.setMoveCmsMultiSelectFormOpen = function(dialog) {
  console.debug('Multi-select form opened for move CMS');

  const $message = $(
    '<div class="col-sm-12 alert alert-info">' +
    '<p>' + displayPageTrans.setMoveCmsMultiSelectFormOpen.message + '</p>' +
    '</div>',
  );

  $(dialog).find('.modal-body').append($message);

  const $cmsAddress = $(
    '<div class="form-group row">' +
    '<label class="col-sm-2 control-label" for="newCmsAddress" ' +
    'accesskey="">New CMS Address</label>' +
    '<div class="col-sm-10">' +
    '<input class="form-control" name="newCmsAddress" type="text" ' +
    'id="newCmsAddress" value="">' +
    '<span class="help-block">' +
    displayPageTrans.newCmsAddressHelp + '</span>' +
    '</div>' +
    '</div>',
  );

  const $cmsKey = $(
    '<div class="form-group row">' +
    '<label class="col-sm-2 control-label" for="newCmsKey" ' +
    'accesskey="">New CMS Key</label>' +
    '<div class="col-sm-10">' +
    '<input class="form-control" name="newCmsKey" type="text" ' +
    'id="newCmsKey" value="">' +
    '<span class="help-block">' + displayPageTrans.newCmsKeyHelp + '</span>' +
    '</div>' +
    '</div>',
  );

  const $authenticationCode = $(
    '<div class="form-group row">' +
    '<label class="col-sm-2 control-label" for="twoFactorCode" ' +
    'accesskey="">Two Factor Code</label>' +
    '<div class="col-sm-10">' +
    '<input class="form-control" name="twoFactorCode" type="text" ' +
    'id="twoFactorCode" value="">' +
    '<span class="help-block">' + displayPageTrans.twoFactorCodeHelp +
    '</span>' +
    '</div>' +
    '</div>',
  );

  $(dialog).find('.modal-body').append(
    $cmsAddress,
    $cmsKey,
    $authenticationCode,
  );

  $('#twoFactorCode, #newCmsAddress, #newCmsKey').on('change', function() {
    dialog.data().commitData = {
      newCmsAddress: $('#newCmsAddress').val(),
      newCmsKey: $('#newCmsKey').val(),
      twoFactorCode: $('#twoFactorCode').val(),
    };
  });
};

window.makeVenueSelect = function($element) {
  // Get the openOOH venue types
  $element.append(new Option('', '0', false, false));
  $.ajax({
    method: 'GET',
    url: $element.data('searchUrl'),
    dataType: 'json',
    success: function(response) {
      $.each(response.data, function(key, el) {
        const selected = el.venueId === $element.data('venueId');
        $element.append(
          new Option(el.venueName, el.venueId, selected, selected),
        );
      });

      $element.select2();
    },
    error: function(xhr) {
      SystemMessage(xhr.message || displayPageTrans.unknownError, false);
    },
  });
};

window.displayEditFormOpen = function(dialog) {
  // Setup display profile form
  displayProfileFormOpen();
  XiboInitialise('#settings-from-display-profile');

  const $settings =
    $(dialog).find('#settings-from-display-profile').find('.form-group');
  const $table = $(dialog).find('#settings-from-profile tbody').empty();
  const override = $(dialog).data('extra');
  const $venueIdSelect2 = $(dialog).find('.venue-select select.form-control');
  if ($venueIdSelect2.data('initialValue')) {
    $venueIdSelect2.data('initialised', true);
    makeVenueSelect($venueIdSelect2);
  } else {
    dialog.find('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
      const activeTab = $(e.target).attr('href');
      if (activeTab === '#location' && !$venueIdSelect2.data('initialised')) {
        $venueIdSelect2.data('initialised', true);
        makeVenueSelect($venueIdSelect2);
      }
    });
  }

  // Parse special fields
  override.forEach(function(field, index) {
    if (['lockOptions', 'pictureOptions', 'timers'].includes(field.name)) {
      const parsedValues = JSON.parse(field.value);

      // Add values to the override array
      $.each(parsedValues, function(name, value) {
        if (name == 'keylock') {
          $.each(value, function(keylockName, keylockValue) {
            if (keylockName == 'local') {
              keylockName = 'keylockLocal';
            }

            if (keylockName == 'remote') {
              keylockName = 'keylockRemote';
            }

            override.push({name: keylockName, value: keylockValue});
          });
        }
        // Convert boolean and numbers to string
        value = (['boolean', 'number'].includes(typeof value)) ?
          value.toString() : value;

        override.push({name: name, value: value});
      });
    }

    // format the date field for elevating log level.
    // set to null if the date is in the past.
    if (field.name === 'elevateLogsUntil') {
      let value = null;
      if (moment(field.value, 'X').isAfter(moment())) {
        value = moment(field.value, 'X').format(systemDateFormat);
      }

      override[index] = {name: field.name, value: value};
    }
  });

  // Method to create a new input field based on the hidden form
  const createInputField = function(target, inputName, newValue = null) {
    let select2FixFlag = false;
    let timepickerFixFlag = false;
    let timepickerOptionsFixFlag = false;
    let sliderFixFlag = false;
    let sliderFixOptions = {};

    // Grab input field from hidden table
    let $inputFields = $settings.find('#' + inputName);

    // If no input field is capture by ID, get special fields
    if ($inputFields.length == 0) {
      if ($settings.find('.multiSelect').length > 0) {
        // Copy special fields ( timers, pictureOptions ) by selected option
        $inputFields =
          $settings.find(
            '.multiSelect option:selected[value="' + inputName + '"]',
          ).parents('.form-group:first')
            .find('.multiSelect, .multiSelectInputs');

        $inputFields.each(function(key, el) {
          if ($(el).find('.timePickerDisplayEditForm').length > 0) {
            timepickerOptionsFixFlag = true;

            $(el).find('.input-group.timerInit').removeClass('timerInit');

            destroyDatePicker(
              $(el).find('.timePickerDisplayEditForm:not(.datePickerHelper)'),
            );
          } else if ($(el).find('.pictureControlsSlider').length > 0) {
            sliderFixFlag = true;
            sliderFixOptions = $(el).find('.pictureControlsSlider')
              .bootstrapSlider('getAttribute');
            $(el).find('.pictureControlsSlider').bootstrapSlider('destroy');
          }
        });
      }
    } else if (
      $inputFields.length == 1 && $inputFields.hasClass('dateControl')
    ) {
      timepickerFixFlag = true;

      destroyDatePicker($inputFields);

      // Time inputs
      $inputFields = $inputFields.parent();
    } else if (
      $inputFields.length == 1 &&
      $inputFields.hasClass('select2-hidden-accessible')
    ) {
      select2FixFlag = true;
      // Destroy select2 before copying
      $inputFields.select2('destroy');
    }

    // Save old field before cloning
    const $inputFieldsOld = $inputFields;

    // Clone input fields ( to be able to reuse the originals )
    $inputFields = $inputFields.clone(true);

    if (select2FixFlag) {
      // Mark old field as select2 with the select2 class
      // so it can be reinitialised if needed
      $inputFieldsOld.addClass('select2-hidden-accessible');
    }
    // Add input field to the target
    $(target).html($inputFields);

    if (timepickerFixFlag) {
      forms.initFields(
        $inputFieldsOld.parents('form'), $inputFieldsOld.find('.dateControl'),
      );
      forms.initFields(
        $(target).parents('form'), $(target).find('.dateControl'),
      );
    }

    if (timepickerOptionsFixFlag) {
      timersFormInit($inputFieldsOld);
      timersFormInit($(target));

      // Parent container
      const $inputParent = $inputFields.parent();
      $inputParent.addClass('timerOverride');
      $inputParent.find('.date-clear-button').remove();
      $inputParent.append(
        '<div class="text-muted">' + displayPageTrans.adjustTimesofTimer +
        '</div>');
    }

    if (sliderFixFlag) {
      $inputFieldsOld.find('.pictureControlsSlider')
        .bootstrapSlider(sliderFixOptions);
      $inputFields.find('.pictureControlsSlider')
        .bootstrapSlider(sliderFixOptions);
    }

    // Set value if defined
    if (newValue != null) {
      if (
        $(target).find('.multiSelectInputs .pictureControlsSlider').length > 0
      ) {
        // SLIDER
        if (!$.isNumeric(newValue)) { // If value is a label, get index
          $(target).find('.multiSelectInputs .slider-tick-label')
            .each(function(idx, el) {
              if ($(el).html() == newValue) {
                newValue = idx;
                return false;
              }
            });
        }

        // Set value
        $(target).find('.pictureControlsSlider')
          .bootstrapSlider('setValue', newValue);
      } else if ($inputFields.attr('type') == 'checkbox') {
        // CHECKBOX
        $($inputFields).prop('checked', newValue);
      } else if (timepickerFixFlag) {
        $($inputFields).find('input').val(newValue);
      } else if (timepickerOptionsFixFlag) {
        $.each(newValue, function(name, value) {
          if (name == 'on') {
            $($inputFields).find('input.timersOn').val(value);
          } else if (name == 'off') {
            $($inputFields).find('input.timersOff').val(value);
          }
        });
      } else {
        $($inputFields).val(newValue);
      }
    }

    // Reload select 2 with new value
    if (select2FixFlag) {
      // Restore select2 after value set
      makePagedSelect($inputFields);
    }

    // Android dimensions init
    if (inputName == 'screenDimensions') {
      setAndroidDimensions($(target));

      // CSS fix
      $(target).find('.androidDimensionInput')
        .removeClass('col-3').addClass('col-6');
    }

    // If there's a multiselect, set the value of
    // the dropdown ( it's not kept on cloning ) and hide it
    $(target).find('.multiSelect').val(inputName).hide();

    // Style multi selects
    if ($(target).find('.multiSelectInputs').length > 0) {
      // Calculate column size for bootstrap
      const colSize = Math.round(12 / $(target)
        .find('.multiSelectInputs').length);

      // Remove all style classes and add the new size class
      $(target).find('.multiSelectInputs')
        .attr('class', 'multiSelectInputs col-sm-' + colSize);
    }

    // Set data propeties
    $(target).attr('data-editing', 'true');
    $(target).attr('data-input-name', inputName);

    // Remove click to edit event
    $(target).unbind('click');

    // Fixes for slider
    $(target).find('.slider').addClass('scaled-slider');
    $(target).find('.pictureControlsSlider')
      .bootstrapSlider('refresh', {useCurrentValue: true});

    // Add help text if exists on label
    const inputHelpText = $(target).parent('tr')
      .find('td:first strong').attr('title');
    if (inputHelpText) {
      $(target).append('<div class="text-muted">' + inputHelpText + '</div>');
    }

    const $newButton =
      $('<button type="button" ' +
        'class="btn btn-outline-danger btn-sm ' +
        'pull-right button-close-override">' +
        '<i class="fa fa-times"></i></button>')
        .on('click',
          function(e) {
            e.stopPropagation();
            restoreInputField(target, inputName);
          });
    $(target).append($newButton);
  };


  // Method to restore the input field to the edit button
  const restoreInputField = function(target, inputName) {
    $(target).html(
      '<button class="btn btn-block btn-outline-secondary" type="button">' +
      '<i class="fa fa-edit"></i></button > ');

    // Handlers for field edit
    $(target).off().on('click', function(ev) {
      if (!$(ev.currentTarget).data('editing')) {
        createInputField($(ev.currentTarget), inputName);
      }
    });

    $(target).attr('data-editing', 'false');
  };

  // Build table
  $.each($settings, function(index, element) {
    const $label = $(element).find('label');
    const $input = $(element).find('input,select');
    const $help = $(element).find('small.form-text.text-muted');
    let over = $label.prop('for');
    let value = '';
    let text = '';
    const help = $help.length > 0 ? $help.text() : '';

    // Skip for some fields
    if (['name', 'isDefault'].includes(over)) {
      return true;
    }

    if ($(element).hasClass('form-group-timers')) {
      // Get text and override name
      over = $(element).find('.multiSelect').val();
      text = $(element).find('.multiSelect option:selected').text();

      // Get value
      value = $(element).find('.timersOn').val() + ' - ' + $(element)
        .find('.timersOff').val();
    } else if ($(element).hasClass('form-group-picture-options')) {
      // Get text and override name
      over = $(element).find('.multiSelect').val();
      text = $(element).find('.multiSelect option:selected').text();

      // Get labels
      const labels = [];
      $(element).find('.multiSelectInputs .slider-tick-label')
        .each(function(idx, el) {
          labels.push($(el).html());
        });

      // Get value
      value = $(element).find('.multiSelectInputs .pictureControlsSlider')
        .bootstrapSlider('getValue');

      // If specific value has a label, use it as value
      if (labels[value] != undefined) {
        value = labels[value];
      }
    } else if ($input.attr('type') == 'checkbox') {
      // Get text and value
      text = $label.text();
      value = $input.is(':checked');
    } else if ($input.is('select')) {
      // Get text and value
      text = $label.text();
      value = $input.find('option:selected').text();
    } else if (over === 'screenDimensions') {
      // Get text and value
      text = $label.text();
      value = $(element).find('#screenDimensions').val();
    } else {
      // Get text and value
      text = $label.text();
      value = $input.val();
    }

    // Skip empty fields
    if (over == '' || over == undefined || over == null) {
      return true;
    }

    // Append new row to the table
    const $tableNewRow =
      $('<tr><td style="width: 40%;"><strong title="' + help + '">' +
        text + '</strong></td>' +
        '<td style="width: 25%; overflow-x: auto; max-width: 250px;"><div>' +
        value + '</div></td><td class="override-input text-center" ' +
        'style="width: 35%;" data-editing="false" data-input-name="' +
        over + '"><button class="btn btn-block btn-outline-secondary"' +
        ' type="button"><i class="fa fa-edit"></i></button></td></tr>')
        .appendTo($table);

    // Get override element
    const overrideEl = override.find(function(x) {
      return x.name === over;
    });
    if (overrideEl != undefined) { // If element was found, create an input
      // Create input with override value
      createInputField(
        $tableNewRow.find('.override-input'),
        over,
        overrideEl.value,
      );
    }
  });

  // Handlers for field edit
  $(dialog).find('.override-input[data-editing="false"]')
    .on('click', function(ev) {
      const inputName = $(ev.currentTarget).data('inputName');
      createInputField($(ev.currentTarget), inputName);
    });

  // Refresh on tab switch to fix broken labels
  $('a[data-toggle="tab"]').on('shown.bs.tab', function() {
    $('.pictureControlsSlider').bootstrapSlider(
      'refresh',
      {useCurrentValue: true},
    );
  });

  // Call xiboInitialise on table
  XiboInitialise('#settings-from-profile');
};

// Custom submit for display form
window.displayEditFormSubmit = function() {
  const $form = $('#displayEditForm');
  // Grab and alter the value in the bandwidth limit field
  const bandwidthLimitField = $form.find('input[name=bandwidthLimit]');
  const bandwidthLimitUnitsField =
    $form.find('select[name=bandwidthLimitUnits]');
  let bandwidthLimit = bandwidthLimitField.val();

  if (bandwidthLimitUnitsField.val() == 'mb') {
    bandwidthLimit = bandwidthLimit * 1024;
  } else if (bandwidthLimitUnitsField.val() == 'gb') {
    bandwidthLimit = bandwidthLimit * 1024 * 1024;
  }

  // Set the field
  bandwidthLimitField.prop('value', bandwidthLimit);

  // Remove temp fields and enable checkbox after submit
  $form.submit(function(event) {
    event.preventDefault();
    // Re-enable checkboxes
    $form.find('input[type="checkbox"]').each(function(_idx, el) {
      // Enable checkbox
      $(el).attr('disabled', false);
    });

    // Remove temp input fields
    $form.find('input.temp-input').each(function(_idx, el) {
      $(el).remove();
    });
  });

  // Replace all checkboxes with hidden input fields
  $form.find('input[type="checkbox"]').each(function(_idx, el) {
    // Get checkbox values
    const value = $(el).is(':checked') ? 'on' : 'off';
    const id = $(el).attr('id');

    // Create hidden input
    $('<input type="hidden" class="temp-input">')
      .attr('id', id)
      .attr('name', id)
      .val(value)
      .appendTo($(el).parent());

    // Disable checkbox so it won't be submitted
    $(el).attr('disabled', true);
  });

  // Submit form
  $form.submit();
};

$('body').on('click', '.display-screenshot', function(el) {
  const displayId = el.target.dataset.displayId;
  const displayType = el.target.dataset.type;
  const clientCode = el.target.dataset.clientCode;

  const statusWindowData = {};

  $.ajax({
    url: '/display/status/' + displayId,
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      if (data != null) {
        // do some processing on data received from webOS and Tizen Players.
        if (
          clientCode < 400 &&
          (displayType === 'webOS' || displayType === 'sssp')
        ) {
          data.logMessagesArray = JSON.stringify(data.logMessagesArray);
          data.allLayouts = JSON.stringify(data.allLayouts);
          data.scheduledLayouts = JSON.stringify(data.scheduledLayouts);
          data.validLayouts = JSON.stringify(data.validLayouts);
          data.invalidLayouts = JSON.stringify(data.invalidLayouts);
          data.blacklistArray = JSON.stringify(data.blacklistArray);
          data.spaceTotal = formatBytes(data.spaceTotal, 2);
          data.spaceFree = formatBytes(data.spaceFree, 2);
          data.spaceUsed = formatBytes(data.spaceUsed, 2);
        }

        statusWindowData['data'] = data;
        statusWindowData['type'] = displayType;

        $('.modal-body').append(
          templates.display.statusWindow({
            ...statusWindowData,
            ...{
              trans: displayPageTrans,
            },
          }),
        );
      }
    },
  });
});
