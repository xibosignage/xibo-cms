module.exports = {
  env: {
    browser: true,
    es2021: true,
  },
  extends: ['google'],
  parserOptions: {
    ecmaVersion: 12,
    sourceType: 'module',
  },
  rules: {
    indent: ['error', 2],
    'linebreak-style': ['error', process.platform === 'win32' ? 'windows' : 'unix'],
  },
};
