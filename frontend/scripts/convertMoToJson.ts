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

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import * as gettextParser from 'gettext-parser';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const inputDir = path.resolve(__dirname, '../../locale');
const outputRoot = path.resolve(__dirname, '../public/locale');
const outputLangDir = path.join(outputRoot, 'langs');

fs.mkdirSync(outputLangDir, { recursive: true });

function convertMoToJson() {
  const files = fs.readdirSync(inputDir).filter((f) => f.endsWith('.mo'));

  for (const file of files) {
    const lang = file.replace(/\.mo$/i, '');
    const mo = fs.readFileSync(path.join(inputDir, file));
    const parsed = gettextParser.mo.parse(mo);

    const messages: Record<string, string> = {};
    const domain = parsed.translations[''] || {};

    for (const key of Object.keys(domain)) {
      // Skip header
      if (!key) {
        continue;
      }

      const entry = domain[key];
      const val = entry?.msgstr?.[0];
      if (val) {
        messages[key] = normalisePlaceholders(val);
      }
    }

    const jsonPath = path.join(outputLangDir, `${lang}.json`);
    fs.writeFileSync(jsonPath, JSON.stringify(messages, null, 2), 'utf8');
    console.log(`Converted ${file} → ${path.relative(process.cwd(), jsonPath)}`);
  }
}

function generateAvailableLangs() {
  if (!fs.existsSync(outputLangDir)) {
    console.error('No language output directory found:', outputLangDir);
    process.exit(0);
  }

  const langs = fs
    .readdirSync(outputLangDir)
    .filter((f) => f.endsWith('.json'))
    .map((f) => path.basename(f, '.json'))
    .sort();

  const outputFile = path.join(outputRoot, 'available.json');
  fs.writeFileSync(outputFile, JSON.stringify(langs, null, 2), 'utf8');
  console.log(`Generated available.json with languages:`, langs);
}

function cleanLangDir() {
  if (fs.existsSync(outputLangDir)) {
    fs.readdirSync(outputLangDir).forEach((f) => {
      fs.unlinkSync(path.join(outputLangDir, f));
    });
    console.log(`Cleaned ${outputLangDir}`);
  }
}

function normalisePlaceholders(str: string): string {
  let counter = 0;

  return (
    str
      // Convert %word% → {{word}}
      .replace(/%([a-zA-Z0-9_]+)%/g, '{{$1}}')
      // Convert %s → {{0}}, {{1}}, etc.
      .replace(/%s/g, () => `{{${counter++}}}`)
  );
}

function main() {
  cleanLangDir();
  convertMoToJson();
  generateAvailableLangs();
  console.log('i18n conversion complete.');
}

main();
