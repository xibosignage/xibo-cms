// --- Build all Global templates ----
window.templates = {
  forms: {
    button: require('./src/templates/forms/button.hbs'),
    input: require('./src/templates/forms/inputs/text.hbs'),
    checkbox: require('./src/templates/forms/inputs/checkbox.hbs'),
    number: require('./src/templates/forms/inputs/number.hbs'),
    dropdown: require('./src/templates/forms/inputs/dropdown.hbs'),
    color: require('./src/templates/forms/inputs/color.hbs'),
    code: require('./src/templates/forms/inputs/code.hbs'),
    message: require('./src/templates/forms/inputs/message.hbs'),
    hidden: require('./src/templates/forms/inputs/hidden.hbs'),
    date: require('./src/templates/forms/inputs/date.hbs'),
    // More to be added
  },
};
