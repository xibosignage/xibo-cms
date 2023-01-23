// --- Build all Global templates ----
window.templates = {
  forms: {
    addOns: {
      helpText: require('./src/templates/forms/inputs/add-ons/helpText.hbs'),
      playerCompatibility:
        require('./src/templates/forms/inputs/add-ons/playerCompatibility.hbs'),
      customPopOver:
        require('./src/templates/forms/inputs/add-ons/customPopOver.hbs'),
    },
    button: require('./src/templates/forms/button.hbs'),
    text: require('./src/templates/forms/inputs/text.hbs'),
    checkbox: require('./src/templates/forms/inputs/checkbox.hbs'),
    number: require('./src/templates/forms/inputs/number.hbs'),
    dropdown: require('./src/templates/forms/inputs/dropdown.hbs'),
    color: require('./src/templates/forms/inputs/color.hbs'),
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
    datasetOrder: require('./src/templates/forms/inputs/datasetOrder.hbs'),
    datasetFilter: require('./src/templates/forms/inputs/datasetFilter.hbs'),
    datasetColumnSelector:
      require('./src/templates/forms/inputs/datasetColumnSelector.hbs'),
    fontSelector: require('./src/templates/forms/inputs/fontSelector.hbs'),
    // More to be added
  },
};
