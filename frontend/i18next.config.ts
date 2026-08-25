/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import type { CallExpression } from '@swc/core';
import { defineConfig } from 'i18next-cli';
import type { Plugin } from 'i18next-cli';

// Discovers property names used as t(identifier.property) and harvests their literal values:
// *Key-suffixed names (labelKey, titleKey...) and known option-label names (label, title...) tree-wide;
// other names same-file only (too generic otherwise, e.g. value/name/type).
const TREE_WIDE_PROPERTY_NAMES = new Set([
  'label',
  'title',
  'scheduleTitle',
  'category',
  'desc',
  'example',
]);

const labelKeyExtractionPlugin = (): Plugin => {
  const fileTextByPath = new Map<string, string>();
  let currentFilePath: string | undefined;
  const keySuffixPropertyNames = new Set<string>();
  const propertyNamesByFile = new Map<string, Set<string>>();

  const registerLiteralsFor = (
    keys: Parameters<NonNullable<Plugin['onEnd']>>[0],
    text: string,
    propertyName: string,
  ) => {
    const pattern = new RegExp(`\\b${propertyName}:\\s*(['"])((?:\\\\.|(?!\\1)[\\s\\S])*)\\1`, 'g');
    for (const match of text.matchAll(pattern)) {
      const value = match[2].trim();
      if (!value) continue;
      keys.set(`translation:${value}`, { key: value, defaultValue: value, ns: 'translation' });
    }
  };

  return {
    name: 'dynamic-property-label-extraction-plugin',
    onLoad(code, path) {
      currentFilePath = path;
      fileTextByPath.set(path, code);
      return undefined;
    },
    onVisitNode(node) {
      if (node.type !== 'CallExpression') return;
      const callExpr = node as CallExpression;
      const callee = callExpr.callee;
      const isTranslationCall =
        (callee.type === 'Identifier' && callee.value === 't') ||
        (callee.type === 'MemberExpression' &&
          callee.property.type === 'Identifier' &&
          callee.property.value === 't');
      if (!isTranslationCall) return;

      const arg = callExpr.arguments[0]?.expression;
      if (!arg || arg.type !== 'MemberExpression' || arg.property.type !== 'Identifier') return;

      const propertyName = arg.property.value;
      if (/Key$/.test(propertyName) || TREE_WIDE_PROPERTY_NAMES.has(propertyName)) {
        keySuffixPropertyNames.add(propertyName);
      } else if (currentFilePath) {
        if (!propertyNamesByFile.has(currentFilePath)) {
          propertyNamesByFile.set(currentFilePath, new Set());
        }
        propertyNamesByFile.get(currentFilePath)?.add(propertyName);
      }
    },
    onEnd(keys) {
      for (const propertyName of keySuffixPropertyNames) {
        for (const text of fileTextByPath.values()) {
          registerLiteralsFor(keys, text, propertyName);
        }
      }
      for (const [path, propertyNames] of propertyNamesByFile) {
        const text = fileTextByPath.get(path);
        if (!text) continue;
        for (const propertyName of propertyNames) {
          registerLiteralsFor(keys, text, propertyName);
        }
      }
    },
  };
};

export default defineConfig({
  locales: ['en'],
  extract: {
    keySeparator: false,
    nsSeparator: false,
    input: 'src/**/*.{js,jsx,ts,tsx}',
    ignore: ['src/login/**', '**/__tests__/**'],
    extractFromComments: false,
    output: 'public/locale/translations/{{language}}/{{namespace}}.json',
  },
  plugins: [labelKeyExtractionPlugin()],
});
