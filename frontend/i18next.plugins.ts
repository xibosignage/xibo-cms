/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import type { CallExpression } from '@swc/core';
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

export const labelKeyExtractionPlugin = (
  namespace: string,
  seedPropertyNames: string[] = [],
): Plugin => {
  const fileTextByPath = new Map<string, string>();
  let currentFilePath: string | undefined;
  // Seeding lets a config harvest e.g. labelKey values even when the t(x.labelKey) call
  // itself lives outside that config's input, since discovery only sees scanned files.
  const keySuffixPropertyNames = new Set<string>(seedPropertyNames);
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
      keys.set(`${namespace}:${value}`, { key: value, defaultValue: value, ns: namespace });
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
