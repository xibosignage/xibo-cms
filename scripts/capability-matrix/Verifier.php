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

declare(strict_types=1);

namespace Xibo\CapabilityMatrix;

/**
 * Verifies a signed capability matrix published by the Xibo APIs against a list of trusted keys.
 *
 * Has no dependencies beyond PHP's built-in sodium, so it runs in the image's capability-matrix
 * build stage without the CMS's vendor tree. Every failure throws \UnexpectedValueException.
 */
class Verifier
{
    public const ARTIFACT = 'capability_matrix';

    /**
     * Verify the served bytes of a matrix against the pointer they were fetched from.
     *
     * @param string $bytes the exact bytes served at the pointer's artifact_url
     * @param array $pointer the decoded GET /v1/matrix/current response
     * @param array $trustedKeys the keys list from trusted-keys.json
     * @return array the verified document, without its signature, and the signature
     *  ['document' => array, 'signature' => array]
     * @throws \UnexpectedValueException if the matrix must not be accepted
     */
    public static function verify(string $bytes, array $pointer, array $trustedKeys): array
    {
        if (!hash_equals((string)($pointer['artifact_sha256'] ?? ''), hash('sha256', $bytes))) {
            throw new \UnexpectedValueException('the download does not match artifact_sha256');
        }

        try {
            $document = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException('the artifact is not valid JSON: ' . $e->getMessage());
        }

        if (!is_array($document) || array_is_list($document)) {
            throw new \UnexpectedValueException('the artifact is not a JSON object');
        }

        $signature = $document['signature'] ?? null;
        if (!is_array($signature)) {
            throw new \UnexpectedValueException('the artifact is not signed');
        }
        unset($document['signature']);

        if (($signature['alg'] ?? null) !== 'ed25519') {
            throw new \UnexpectedValueException('unsupported signature algorithm');
        }

        $keyId = (string)($signature['key_id'] ?? '');
        $key = self::findKey($trustedKeys, $keyId);
        if ($key === null) {
            throw new \UnexpectedValueException('key ' . $keyId . ' is not in trusted-keys.json');
        }

        $sig = base64_decode((string)($signature['value'] ?? ''), true);
        $public = base64_decode((string)($key['public_key'] ?? ''), true);

        if ($sig === false
            || $public === false
            || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES
            || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || !sodium_crypto_sign_verify_detached($sig, self::signable($document), $public)
        ) {
            throw new \UnexpectedValueException('the signature does not verify');
        }

        // Checked after the signature, so built_at is only read from a verified document. not_before
        // must be a bare date: it is compared as a string against the date part of built_at, and a
        // full datetime would reject every matrix built on that day.
        if (isset($key['not_before'])) {
            $notBefore = $key['not_before'];
            if (!is_string($notBefore) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $notBefore) !== 1) {
                throw new \UnexpectedValueException(
                    'key ' . $keyId . ' has a not_before that is not a YYYY-MM-DD date'
                );
            }

            if (substr((string)($document['built_at'] ?? ''), 0, 10) < $notBefore) {
                throw new \UnexpectedValueException(
                    'key ' . $keyId . ' is not valid before ' . $notBefore
                );
            }
        }

        // Checked after the signature: the same key signs every artifact type the Xibo APIs publish.
        if (($document['artifact'] ?? null) !== self::ARTIFACT) {
            throw new \UnexpectedValueException('the document is not a capability matrix');
        }

        if (!is_int($document['matrix_version'] ?? null)) {
            throw new \UnexpectedValueException('the artifact has no matrix_version');
        }

        if ($document['matrix_version'] !== ($pointer['matrix_version'] ?? null)) {
            throw new \UnexpectedValueException('matrix_version does not match the pointer');
        }

        return ['document' => $document, 'signature' => $signature];
    }

    /**
     * The canonical bytes that are signed: the document without its signature, with object keys
     * sorted by byte order at every level and lists kept in order.
     * @param array $document
     * @return string
     */
    public static function signable(array $document): string
    {
        try {
            return json_encode(
                self::canonical($document),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException('the artifact cannot be encoded: ' . $e->getMessage());
        }
    }

    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            if (is_float($value)) {
                throw new \UnexpectedValueException('the artifact holds a floating-point number');
            }

            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map([self::class, 'canonical'], $value);
    }

    private static function findKey(array $trustedKeys, string $keyId): ?array
    {
        foreach ($trustedKeys as $key) {
            if (is_array($key) && ($key['key_id'] ?? null) === $keyId && ($key['alg'] ?? 'ed25519') === 'ed25519') {
                return $key;
            }
        }

        return null;
    }
}
