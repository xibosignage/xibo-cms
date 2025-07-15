module.exports = function(v1, v2, opts) {
  if (v1 || v2) {
    return opts.fn(this);
  } else {
    return opts.inverse(this);
  }
};

