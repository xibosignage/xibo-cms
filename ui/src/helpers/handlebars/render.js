module.exports = function(partialId, options) {
  const selector = 'script[type="text/x-handlebars-template"]#' + partialId;
  const source = $(selector).html();
  const html = Handlebars.compile(source)(options.hash);

  return new Handlebars.SafeString(html);
};
