//--- Build all Global templates ----
window.templates = {
    forms: {
        input: require('./src/templates/forms/inputs/text.hbs'),
        checkbox: require('./src/templates/forms/inputs/checkbox.hbs'),
        number: require('./src/templates/forms/inputs/double.hbs'),
        dropdown: require('./src/templates/forms/inputs/dropdown.hbs'),
        integer: require('./src/templates/forms/inputs/integer.hbs'),
        color: require('./src/templates/forms/inputs/color.hbs'),
        code: require('./src/templates/forms/inputs/code.hbs'),
        // More to be added
    },
};