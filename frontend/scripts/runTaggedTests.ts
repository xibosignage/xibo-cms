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

/**
 * Runs only the tests carrying one tag:
 *   tsx scripts/runTaggedTests.ts <tag> [path filters...] [vitest options...]
 *
 * `vitest run --tags-filter=<tag>` on its own imports every test file to read its tags, so a run of
 * ~90 smoke tests costs as much as the whole suite. This passes Vitest only the files that mention
 * the tag, and keeps `--tags-filter` so untagged tests in those files are still skipped.
 *
 * A path filter narrows the run to tagged files whose path contains it, the way Vitest's own file
 * filters work (`npm run test:smoke -- src/pages/Design -t "saves"`). `./`, `../` and absolute
 * paths work too. Put path filters before options: a word right after an option without `=` is that
 * option's value, unless it contains a slash. Path filters that match no tagged file fail the run
 * (exit 1), so a typo can't pass as a green run that tested nothing.
 *
 * Write the tag inline, as a literal (`{ tags: ['smoke'] }`): files are found by searching their
 * text, so a tag kept in a constant (`const SMOKE = { tags: ['smoke'] }`) is not seen, and those
 * tests silently drop out of the run.
 */
import { spawnSync } from 'node:child_process';
import { readdirSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// frontend/, wherever the script is started from.
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const [tag, ...args] = process.argv.slice(2);
if (!tag || !/^[a-z][a-z-]*$/.test(tag)) {
  console.error(
    'Usage: tsx scripts/runTaggedTests.ts <tag> [path filters...] [--vitest-option=value...]',
  );
  process.exit(2);
}
// A word right after an option with no `=` is that option's value (`-t "saves the layout"`,
// `--reporter dot`), unless it contains a slash, which makes it a path. Anything else is a path
// filter. So a filter without a slash (`Layouts`) goes before the options, not after them.
const options: string[] = [];
const rawFilters: string[] = [];
const queue = [...args];
for (let arg = queue.shift(); arg !== undefined; arg = queue.shift()) {
  if (!arg.startsWith('-')) {
    rawFilters.push(arg);
    continue;
  }
  options.push(arg);
  const next = queue[0];
  if (!arg.includes('=') && next !== undefined && !next.startsWith('-') && !/[\\/]/.test(next)) {
    options.push(next);
    queue.shift();
  }
}

// `./src/...`, `../...` and absolute paths are resolved from where the command was run (npm sets
// INIT_CWD to that), then made relative to frontend/. Plain words match anywhere in the path.
const START_DIR = process.env.INIT_CWD ?? process.cwd();
const toPathFilter = (arg: string): string => {
  const slashed = arg.replace(/\\/g, '/');
  if (!path.isAbsolute(arg) && !slashed.startsWith('./') && !slashed.startsWith('../')) {
    return slashed;
  }
  return path.relative(ROOT, path.resolve(START_DIR, arg)).split(path.sep).join('/');
};
const pathFilters = rawFilters.map(toPathFilter);

const TEST_FILE = /\.test\.[jt]sx?$/;
// `tags: ['smoke']`, `tags: ['smoke', 'other']` or `tags: 'smoke'`, on a test or a describe.
const HAS_TAG = new RegExp(`tags:\\s*(\\[[^\\]]*)?['"\`]${tag}['"\`]`);

const findFiles = (dir: string): string[] =>
  readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) return entry.name === 'node_modules' ? [] : findFiles(full);
    return TEST_FILE.test(entry.name) && HAS_TAG.test(readFileSync(full, 'utf8')) ? [full] : [];
  });

// Relative to frontend/ with forward slashes: what Vitest prints, and what path filters match.
const tagged = findFiles(path.join(ROOT, 'src')).map((file) =>
  path.relative(ROOT, file).split(path.sep).join('/'),
);
const files =
  pathFilters.length === 0
    ? tagged
    : tagged.filter((file) => pathFilters.some((filter) => file.includes(filter)));

if (files.length === 0) {
  if (pathFilters.length) {
    console.error(
      `No test files tagged "${tag}" match ${pathFilters.join(', ')}. Check the path, and put ` +
        'path filters before options: a word after an option is read as its value.',
    );
    process.exit(1);
  }
  // Nothing carries the tag at all (e.g. no test is quarantined yet): nothing to run, not a failure.
  console.log(`No test files are tagged "${tag}".`);
  process.exit(0);
}
console.log(`Running "${tag}" tests from ${files.length} files.`);

// Vitest's CLI entry, found through its package.json `bin` (the file itself isn't in the package's
// exports, so it can't be resolved directly). Run with this Node and no shell, which avoids cmd.exe's
// 8,191-character command-line limit on Windows.
const require = createRequire(path.join(ROOT, 'package.json'));
const vitestPackage = require.resolve('vitest/package.json');
const vitestBin = (require(vitestPackage) as { bin: { vitest: string } }).bin.vitest;
const vitest = path.join(path.dirname(vitestPackage), vitestBin);
const result = spawnSync(
  process.execPath,
  // Files first, so an option that expects a value can never take a file path as it.
  [vitest, 'run', ...files, `--tags-filter=${tag}`, ...options],
  { cwd: ROOT, stdio: 'inherit' },
);
if (result.error) {
  console.error(`Could not start Vitest (${vitest}): ${result.error.message}`);
  process.exit(1);
}
process.exit(result.status ?? 1);
