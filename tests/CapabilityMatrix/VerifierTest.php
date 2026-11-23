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

namespace Xibo\Tests\CapabilityMatrix;

use PHPUnit\Framework\TestCase;
use Xibo\CapabilityMatrix\Verifier;

/**
 * Checks the build-time capability matrix verifier against Artemis's published signing test vector
 * (tests/resources/capability-matrix/signing-test-vector.json). The vector's key is test-only and
 * must never be added to scripts/capability-matrix/trusted-keys.json.
 */
class VerifierTest extends TestCase
{
    private array $vector;

    protected function setUp(): void
    {
        $this->vector = json_decode(
            file_get_contents(__DIR__ . '/../resources/capability-matrix/signing-test-vector.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testVectorVerifies()
    {
        $served = $this->vector['served'];

        $this->assertSame($this->vector['served_sha256'], hash('sha256', $served));

        $verified = Verifier::verify($served, $this->pointer($served), $this->trustedKeys());

        $this->assertSame($this->vector['signable'], Verifier::signable($verified['document']));
        $this->assertSame($this->vector['key_id'], $verified['signature']['key_id']);
        $this->assertSame(7, $verified['document']['matrix_version']);
    }

    public function testChangedByteFails()
    {
        // Change one byte of a string value inside the signed content, keeping the JSON valid.
        $served = str_replace('HEVC hardware decode', 'HEVC hardware decodf', $this->vector['served']);
        $this->assertNotSame($this->vector['served'], $served);

        $this->expectExceptionMessage('the signature does not verify');
        Verifier::verify($served, $this->pointer($served), $this->trustedKeys());
    }

    public function testWrongArtifactFails()
    {
        $served = $this->resign(fn (array $document) => ['artifact' => 'feature_registry'] + $document);

        $this->expectExceptionMessage('the document is not a capability matrix');
        Verifier::verify($served, $this->pointer($served), $this->trustedKeys());
    }

    public function testUnknownKeyFails()
    {
        $served = $this->vector['served'];

        $this->expectExceptionMessage('is not in trusted-keys.json');
        Verifier::verify($served, $this->pointer($served), [
            ['key_id' => '2026-k1', 'alg' => 'ed25519', 'public_key' => $this->vector['public_key']],
        ]);
    }

    public function testKeyNotYetValidFails()
    {
        $served = $this->vector['served'];

        $this->expectExceptionMessage('is not valid before 2026-10-10');
        Verifier::verify($served, $this->pointer($served), $this->trustedKeys('2026-10-10'));
    }

    public function testKeyValidFromBuildDatePasses()
    {
        $served = $this->vector['served'];

        $verified = Verifier::verify($served, $this->pointer($served), $this->trustedKeys('2026-10-09'));
        $this->assertSame(7, $verified['document']['matrix_version']);
    }

    public function testDatetimeNotBeforeFails()
    {
        $served = $this->vector['served'];

        $this->expectExceptionMessage('has a not_before that is not a YYYY-MM-DD date');
        Verifier::verify($served, $this->pointer($served), $this->trustedKeys('2026-10-09T00:00:00Z'));
    }

    public function testDigestMismatchFails()
    {
        $served = $this->vector['served'];
        $pointer = $this->pointer($served);
        $pointer['artifact_sha256'] = hash('sha256', $served . ' ');

        $this->expectExceptionMessage('the download does not match artifact_sha256');
        Verifier::verify($served, $pointer, $this->trustedKeys());
    }

    public function testPointerVersionMismatchFails()
    {
        $served = $this->vector['served'];
        $pointer = $this->pointer($served);
        $pointer['matrix_version'] = 8;

        $this->expectExceptionMessage('matrix_version does not match the pointer');
        Verifier::verify($served, $pointer, $this->trustedKeys());
    }

    public function testMissingVersionFails()
    {
        $served = $this->resign(function (array $document) {
            unset($document['matrix_version']);
            return $document;
        });
        $pointer = $this->pointer($served);
        unset($pointer['matrix_version']);

        $this->expectExceptionMessage('the artifact has no matrix_version');
        Verifier::verify($served, $pointer, $this->trustedKeys());
    }

    public function testUnsupportedAlgorithmFails()
    {
        $document = json_decode($this->vector['served'], true, 512, JSON_THROW_ON_ERROR);
        $document['signature']['alg'] = 'rsa';
        $served = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->expectExceptionMessage('unsupported signature algorithm');
        Verifier::verify($served, $this->pointer($served), $this->trustedKeys());
    }

    public function testUnsignedFails()
    {
        $document = json_decode($this->vector['served'], true, 512, JSON_THROW_ON_ERROR);
        unset($document['signature']);
        $served = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->expectExceptionMessage('the artifact is not signed');
        Verifier::verify($served, $this->pointer($served), $this->trustedKeys());
    }

    /**
     * The pointer GET /v1/matrix/current would return for these served bytes.
     */
    private function pointer(string $served): array
    {
        return [
            'matrix_version' => 7,
            'artifact_url' => 'https://artemis.invalid/v1/matrix/7',
            'artifact_sha256' => hash('sha256', $served),
        ];
    }

    private function trustedKeys(?string $notBefore = null): array
    {
        $key = [
            'key_id' => $this->vector['key_id'],
            'alg' => 'ed25519',
            'public_key' => $this->vector['public_key'],
        ];

        if ($notBefore !== null) {
            $key['not_before'] = $notBefore;
        }

        return [$key];
    }

    /**
     * Change the vector's document and sign it again with the vector's test key, so a single check
     * can be exercised behind a valid signature.
     */
    private function resign(callable $change): string
    {
        $document = json_decode($this->vector['served'], true, 512, JSON_THROW_ON_ERROR);
        unset($document['signature']);
        $document = $change($document);

        $keyPair = sodium_crypto_sign_seed_keypair(hex2bin($this->vector['seed_hex']));
        $this->assertSame($this->vector['public_key'], base64_encode(sodium_crypto_sign_publickey($keyPair)));

        $document['signature'] = [
            'alg' => 'ed25519',
            'key_id' => $this->vector['key_id'],
            'value' => base64_encode(sodium_crypto_sign_detached(
                Verifier::signable($document),
                sodium_crypto_sign_secretkey($keyPair)
            )),
        ];

        return Verifier::signable($document);
    }
}
