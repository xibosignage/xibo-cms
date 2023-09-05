<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Service;

use Carbon\Carbon;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * A service to create and validate JWTs
 */
class JwtService implements JwtServiceInterface
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var array */
    private $keys;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @return \Xibo\Service\JwtServiceInterface
     */
    public function useLogger(LoggerInterface $logger): JwtServiceInterface
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return \Psr\Log\LoggerInterface|\Psr\Log\NullLogger
     */
    private function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            return new NullLogger();
        }
        return $this->logger;
    }

    /**
     * @param $keys
     * @return \Xibo\Service\JwtServiceInterface
     */
    public function useKeys($keys): JwtServiceInterface
    {
        $this->keys = $keys;
        return $this;
    }

    /** @inheritDoc */
    public function generateJwt($issuedBy, $permittedFor, $identifiedBy, $relatedTo, $ttl): Token
    {
        $this->getLogger()->debug('generateJwt: Private key path is: ' . $this->getPrivateKeyPath()
            . ', identifiedBy: ' . $identifiedBy . ', relatedTo: ' . $relatedTo);

        $tokenBuilder = (new Builder(new JoseEncoder(), ChainedFormatter::default()));
        $signingKey = Key\InMemory::file($this->getPrivateKeyPath());
        return $tokenBuilder
            ->issuedBy($issuedBy)
            ->permittedFor($permittedFor)
            ->identifiedBy($identifiedBy)
            ->issuedAt(Carbon::now()->toDateTimeImmutable())
            ->canOnlyBeUsedAfter(Carbon::now()->toDateTimeImmutable())
            ->expiresAt(Carbon::now()->addSeconds($ttl)->toDateTimeImmutable())
            ->relatedTo($relatedTo)
            ->getToken(new Sha256(), $signingKey);
    }

    /** @inheritDoc */
    public function validateJwt($jwt): ?Token
    {
        $this->getLogger()->debug('validateJwt: ' . $jwt);

        $signingKey = Key\InMemory::file($this->getPrivateKeyPath());
        $configuration = Configuration::forSymmetricSigner(new Sha256(), $signingKey);

        $configuration->setValidationConstraints(
            new LooseValidAt(new SystemClock(new \DateTimeZone(\date_default_timezone_get()))),
            new SignedWith(new Sha256(), InMemory::plainText(file_get_contents($this->getPublicKeyPath())))
        );

        // Parse the token
        $token = $configuration->parser()->parse($jwt);

        $this->getLogger()->debug('validateJwt: token parsed');

        // Test against constraints.
        $constraints = $configuration->validationConstraints();
        $configuration->validator()->assert($token, ...$constraints);

        $this->getLogger()->debug('validateJwt: constraints valid');
        return $token;
    }

    /**
     * @return string|null
     */
    private function getPublicKeyPath(): ?string
    {
        return $this->keys['publicKeyPath'] ?? null;
    }

    /**
     * @return string|null
     */
    private function getPrivateKeyPath(): ?string
    {
        return $this->keys['privateKeyPath'] ?? null;
    }
}
