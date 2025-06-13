// --- Build all Global templates ----
window.templates = {
  forms: {
    addOns: {
      helpText: require('./src/templates/forms/inputs/add-ons/helpText.hbs'),
      playerCompatibility:
        require('./src/templates/forms/inputs/add-ons/playerCompatibility.hbs'),
      customPopOver:
        require('./src/templates/forms/inputs/add-ons/customPopOver.hbs'),
      dropdownOptionImage:
        require('./src/templates/forms/inputs/add-ons/dropdownOptionImage.hbs'),
      dateFormatHelperPopup:
        require(
          './src/templates/forms/inputs/add-ons/dateFormatHelperPopup.hbs',
        ),
    },
    group: require('./src/templates/forms/group.hbs'),
    button: require('./src/templates/forms/button.hbs'),
    text: require('./src/templates/forms/inputs/text.hbs'),
    checkbox: require('./src/templates/forms/inputs/checkbox.hbs'),
    number: require('./src/templates/forms/inputs/number.hbs'),
    dropdown: require('./src/templates/forms/inputs/dropdown.hbs'),
    color: require('./src/templates/forms/inputs/color.hbs'),
    colorGradient: require('./src/templates/forms/inputs/colorGradient.hbs'),
    code: require('./src/templates/forms/inputs/code.hbs'),
    message: require('./src/templates/forms/inputs/message.hbs'),
    hidden: require('./src/templates/forms/inputs/hidden.hbs'),
    date: require('./src/templates/forms/inputs/date.hbs'),
    header: require('./src/templates/forms/inputs/header.hbs'),
    richText: require('./src/templates/forms/inputs/richText.hbs'),
    divider: require('./src/templates/forms/inputs/divider.hbs'),
    buttonSwitch: require('./src/templates/forms/inputs/buttonSwitch.hbs'),
    custom: require('./src/templates/forms/inputs/custom.hbs'),
    datasetSelector:
      require('./src/templates/forms/inputs/datasetSelector.hbs'),
    menuBoardSelector:
        require('./src/templates/forms/inputs/menuBoardSelector.hbs'),
    menuBoardCategorySelector:
        require('./src/templates/forms/inputs/menuBoardCategorySelector.hbs'),
    datasetOrder: require('./src/templates/forms/inputs/datasetOrder.hbs'),
    datasetFilter: require('./src/templates/forms/inputs/datasetFilter.hbs'),
    datasetColumnSelector:
      require('./src/templates/forms/inputs/datasetColumnSelector.hbs'),
    datasetField: require('./src/templates/forms/inputs/datasetField.hbs'),
    fontSelector: require('./src/templates/forms/inputs/fontSelector.hbs'),
    effectSelector: require('./src/templates/forms/inputs/effectSelector.hbs'),
    worldClock: require('./src/templates/forms/inputs/worldClock.hbs'),
    mediaSelector: require('./src/templates/forms/inputs/mediaSelector.hbs'),
    languageSelector:
      require('./src/templates/forms/inputs/languageSelector.hbs'),
    forecastUnitsSelector:
      require('./src/templates/forms/inputs/forecastUnitsSelector.hbs'),
    commandSelector:
      require('./src/templates/forms/inputs/commandSelector.hbs'),
    commandBuilder: require('./src/templates/forms/inputs/commandBuilder.hbs'),
    connectorProperties:
      require('./src/templates/forms/inputs/connectorProperties.hbs'),
    playlistMixer: require('./src/templates/forms/inputs/playlistMixer.hbs'),
    snippet: require('./src/templates/forms/inputs/snippet.hbs'),
    textArea: require('./src/templates/forms/inputs/textArea.hbs'),
    canvasWidgetsSelector:
      require('./src/templates/forms/inputs/canvasWidgetsSelector.hbs'),
    widgetInfo:
        require('./src/templates/forms/inputs/widgetInfo.hbs'),
    tickerTagSelector:
      require('./src/templates/forms/inputs/tickerTagSelector.hbs'),
    tickerTagStyle:
      require('./src/templates/forms/inputs/tickerTagStyle.hbs'),
    datasetColStyleSelector:
      require('./src/templates/forms/inputs/datasetColStyleSelector.hbs'),
    datasetColStyle:
      require('./src/templates/forms/inputs/datasetColStyle.hbs'),
    imageReplaceControl:
        require('./src/templates/forms/inputs/imageReplace.hbs'),
    fallbackDataContent:
        require('./src/templates/fallback-data-content.hbs'),
    fallbackDataRecord:
        require('./src/templates/fallback-data-record.hbs'),
    fallbackDataRecordPreview:
      require('./src/templates/fallback-data-record-preview.hbs'),
    commandInput: {
      main: require('./src/templates/commandInput/main.hbs'),
      freetext:
        require('./src/templates/commandInput/freetext.hbs'),
      tpv_led: require('./src/templates/commandInput/tpv_led.hbs'),
      rs232: require('./src/templates/commandInput/rs232.hbs'),
      intent: require('./src/templates/commandInput/intent.hbs'),
      'intent-extra':
        require('./src/templates/commandInput/intent-extra.hbs'),
      http: require('./src/templates/commandInput/http.hbs'),
      'http-key-value':
        require('./src/templates/commandInput/http-key-value.hbs'),
    },
  },
  dataTable: {
    buttons: require('./src/templates/dataTable/buttons.hbs'),
    multiSelectButton:
      require('./src/templates/dataTable/multiselect-button.hbs'),
  },
  schedule: {
    criteriaFields:
      require('./src/templates/schedule/schedule-criteria-fields.hbs'),
    fullscreenSchedule:
      require('./src/templates/schedule/fullscreen-schedule.hbs'),
    reminderEvent:
      require('./src/templates/schedule/reminder-event.hbs'),
  },
  calendar: {
    day: require('./src/templates/calendar/day.hbs'),
    month: require('./src/templates/calendar/month.hbs'),
    'month-day': require('./src/templates/calendar/month-day.hbs'),
    week: require('./src/templates/calendar/week.hbs'),
    'week-days': require('./src/templates/calendar/week-days.hbs'),
    year: require('./src/templates/calendar/year.hbs'),
    'year-month': require('./src/templates/calendar/year-month.hbs'),
    agenda: require('./src/templates/calendar/agenda.hbs'),
    'agenda-layouts': require('./src/templates/calendar/agenda-layouts.hbs'),
    'agenda-displaygroups':
      require('./src/templates/calendar/agenda-display-groups.hbs'),
    'agenda-campaigns':
      require('./src/templates/calendar/agenda-campaigns.hbs'),
    'breadcrumb-trail':
      require('./src/templates/calendar/breadcrumb-trail.hbs'),
    'events-list': require('./src/templates/calendar/events-list.hbs'),
    syncEventContentSelector:
        require('./src/templates/calendar/sync-event-content-selector.hbs'),
  },
  display: {
    statusWindow: require('./src/templates/display/status-window.hbs'),
  },
  'multiselect-tag-edit-form':
    require('./src/templates/multiselect-tag-edit-form.hbs'),
  'auto-submit-field': require('./src/templates/auto-submit-field.hbs'),
  'folder-tree': require('./src/templates/folder-tree.hbs'),
  'mini-player': require('./src/templates/mini-player.hbs'),
  'xibo-filter-clear-button':
    require('./src/templates/xibo-filter-clear-button.hbs'),
  'php-date-format-table': require('./src/templates/php-date-format-table.hbs'),
  campaign: {
    campaignAssignLayout:
      require('./src/templates/campaign/campaign-assign-layout.hbs'),
  },
  welcome: {
    welcomeCard:
      require('./src/templates/welcome/welcome-card.hbs'),
    serviceCard:
      require('./src/templates/welcome/service-card.hbs'),
    othersCard:
      require('./src/templates/welcome/others-card.hbs'),
    videoModal:
      require('./src/templates/welcome/video-modal.hbs'),
    videoModalContent:
      require('./src/templates/welcome/video-modal-content.hbs'),
  },
};
