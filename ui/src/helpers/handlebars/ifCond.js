module.exports = function(v1, operator, v2, opts) {
  switch (operator) {
    case '==':
      return (v1 == v2) ? opts.fn(this) : opts.inverse(this);
    case '===':
      return (v1 === v2) ? opts.fn(this) : opts.inverse(this);
    case '!=':
      return (v1 != v2) ? opts.fn(this) : opts.inverse(this);
    case '!==':
      return (v1 !== v2) ? opts.fn(this) : opts.inverse(this);
    case '<':
      return (v1 < v2) ? opts.fn(this) : opts.inverse(this);
    case '<=':
      return (v1 <= v2) ? opts.fn(this) : opts.inverse(this);
    case '>':
      return (v1 > v2) ? opts.fn(this) : opts.inverse(this);
    case '>=':
      return (v1 >= v2) ? opts.fn(this) : opts.inverse(this);
    case '&&':
      return (v1 && v2) ? opts.fn(this) : opts.inverse(this);
    case '||':
      return (v1 || v2) ? opts.fn(this) : opts.inverse(this);
    default:
      return opts.inverse(this);
  }
};
