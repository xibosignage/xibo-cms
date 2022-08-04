module.exports = function(...args) {
  let res = '';
  for (let i = 0; i < args.length - 1; i++) {
    res += args[i];
  }
  return res;
};
