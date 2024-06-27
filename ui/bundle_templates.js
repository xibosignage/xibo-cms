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
  },
};
