module.exports = function(
  optionArray,
  option,
  optionsObjectKey,
  optionValueKey,
) {
  let value;

  optionsObjectKey = (typeof optionsObjectKey != 'string') && 'option';
  optionValueKey = (typeof optionValueKey != 'string') && 'value';

  optionArray.forEach((el) => {
    if (option == el[optionsObjectKey]) {
      value = el[optionValueKey];
    }
  });

  return value;
};
