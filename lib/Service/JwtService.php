<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
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


    public function generateJwt($issuedBy, $permittedFor, $identifiedBy, $relatedTo, $ttl): Token
    {
        $this->getLogger()->debug('Private key path is: ' . $this->getPrivateKeyPath());
        return (new Builder())
            ->issuedBy($issuedBy)
            ->permittedFor($permittedFor)
            ->identifiedBy($identifiedBy)
            ->issuedAt(Carbon::now()->toDateTimeImmutable())
            ->canOnlyBeUsedAfter(Carbon::now()->toDateTimeImmutable())
            ->expiresAt(Carbon::now()->addSeconds($ttl)->toDateTimeImmutable())
            ->relatedTo($relatedTo)
            ->getToken(new Sha256(), new Key(file_get_contents($this->getPrivateKeyPath())));
    }

    public function validateJwt($jwt): ?Token
    {
        $configuration = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText(''));

        $configuration->setValidationConstraints(
            new ValidAt(new SystemClock(new \DateTimeZone(\date_default_timezone_get()))),
            new SignedWith(new Sha256(), InMemory::plainText(file_get_contents($this->getPublicKeyPath())))
        );

        // Parse the token
        $token = $configuration->parser()->parse($jwt);

        // Test against constraints.
        $constraints = $configuration->validationConstraints();
        $configuration->validator()->assert($token, ...$constraints);
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
