<?php
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
 * Fetches the current signed capability matrix from the Xibo APIs, verifies it against the trusted
 * keys, and writes it to the output directory for the CMS image.
 *
 * Usage: php bake.php <output directory>
 *
 * Env: CAPABILITY_MATRIX_URL (base URL of the Xibo APIs publication host; empty skips),
 *      CAPABILITY_MATRIX (required | optional). Optional tolerates "nothing to fetch yet"
 *      (no URL, no release, host unreachable); a matrix that fails verification always fails
 *      the build.
 */

declare(strict_types=1);

require __DIR__ . '/Verifier.php';

use Xibo\CapabilityMatrix\Verifier;

$base = rtrim((string)getenv('CAPABILITY_MATRIX_URL'), '/');
$required = getenv('CAPABILITY_MATRIX') === 'required';
$out = $argv[1] ?? '/out';

$fail = function (string $message): never {
    fwrite(STDERR, 'capability matrix: ' . $message . PHP_EOL);
    exit(1);
};

$skip = function (string $message) use ($required, $fail): never {
    if ($required) {
        $fail($message);
    }
    fwrite(STDERR, 'capability matrix: ' . $message . '; building without one.' . PHP_EOL);
    exit(0);
};

$isHttps = fn (string $url): bool => str_starts_with(strtolower($url), 'https://');

// Fetches over HTTPS only, following redirects only to HTTPS, so a man in the middle can't serve an
// older (validly signed) matrix. Returns null when there is nothing to fetch.
$fetch = function (string $url) use ($isHttps, $fail): ?string {
    if (!$isHttps($url)) {
        $fail('refusing to fetch ' . $url . ': only https:// is allowed');
    }

    $context = stream_context_create(['http' => [
        'timeout' => 30,
        'ignore_errors' => true,
        'max_redirects' => 5,
        'header' => "Accept: application/json\r\n",
    ]]);
    $body = @file_get_contents($url, false, $context);

    // $http_response_header holds every response in a redirect chain: the last status line is the
    // final response's. A status line need not have a reason phrase.
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
            $status = (int)$m[1];
        } elseif (preg_match('#^Location:\s*(\S+)#i', $header, $m)
            && preg_match('#^[a-z][a-z0-9+.-]*:#i', $m[1])
            && !$isHttps($m[1])
        ) {
            $fail('refusing to follow a redirect from ' . $url . ' to ' . $m[1]);
        }
    }

    return $body !== false && $status === 200 ? $body : null;
};

$write = function (string $path, string $contents) use ($fail): void {
    if (file_put_contents($path, $contents) !== strlen($contents)) {
        $fail('cannot write ' . $path);
    }
};

try {
    $trusted = json_decode(
        (string)file_get_contents(__DIR__ . '/trusted-keys.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    )['keys'] ?? [];
} catch (\JsonException $e) {
    $fail('trusted-keys.json is not valid JSON: ' . $e->getMessage());
}

if (!is_dir($out) && !mkdir($out, 0755, true)) {
    $fail('cannot create ' . $out);
}

if ($base === '') {
    $skip('CAPABILITY_MATRIX_URL is not set');
}

$pointerBytes = $fetch($base . '/v1/matrix/current') ?? $skip('no current matrix at ' . $base);

try {
    $pointer = json_decode($pointerBytes, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    $fail('the pointer at ' . $base . '/v1/matrix/current is not valid JSON');
}

if (!is_array($pointer) || !is_string($pointer['artifact_url'] ?? null)) {
    $fail('the pointer at ' . $base . '/v1/matrix/current has no artifact_url');
}

$bytes = $fetch($pointer['artifact_url']) ?? $skip('could not fetch ' . $pointer['artifact_url']);

// From here on, anything wrong is a bad artifact: fail whatever the mode.
try {
    $verified = Verifier::verify($bytes, $pointer, $trusted);
} catch (\UnexpectedValueException $e) {
    $fail($e->getMessage());
}

$document = $verified['document'];
$signature = $verified['signature'];

// The exact served bytes, the trust anchor the CMS verifies with, and where they came from.
$write($out . '/matrix.json', $bytes);
$write($out . '/signing-keys.json', (string)file_get_contents(__DIR__ . '/trusted-keys.json'));
$write($out . '/provenance.json', json_encode([
    'matrix_version' => $document['matrix_version'],
    'built_at' => $document['built_at'] ?? null,
    'artifact_sha256' => $pointer['artifact_sha256'],
    'signing_key_id' => $signature['key_id'],
    'source' => $pointer['artifact_url'],
    'fetched_at' => gmdate('Y-m-d\TH:i:s\Z'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

echo 'capability matrix ' . $document['matrix_version'] . ' verified with ' . $signature['key_id'] . PHP_EOL;
