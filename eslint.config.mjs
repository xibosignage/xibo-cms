import globals from 'globals';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import js from '@eslint/js';
import {FlatCompat} from '@eslint/eslintrc';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const compat = new FlatCompat({
  baseDirectory: __dirname,
  recommendedConfig: js.configs.recommended,
  allConfig: js.configs.all,
});

export default [...compat.extends('google'), {
  languageOptions: {
    globals: {
      ...globals.browser,
    },

    ecmaVersion: 'latest',
    sourceType: 'module',
  },

  settings: {},
  rules: {
    indent: ['error', 2, {
      SwitchCase: 1,
    }],

    'quote-props': ['warn', 'as-needed'],
    'dot-location': ['warn', 'property'],
    'linebreak-style': [0, 'error', 'windows'],
    'valid-jsdoc': 'off',
    'require-jsdoc': 'off',
  },
}, {
  files: ['**/*.js'],
  rules: {},
}];
