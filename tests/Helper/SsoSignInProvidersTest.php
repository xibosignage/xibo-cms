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

namespace Xibo\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Interfaces\RouteParserInterface;
use Xibo\Helper\SsoSignInProviders;
use Xibo\Middleware\CASAuthentication;
use Xibo\Middleware\SAMLAuthentication;
use Xibo\Middleware\WebAuthentication;
use Xibo\Service\ConfigService;

/**
 * The SAML/CAS buttons on the login page only appear when that provider is the active authentication
 * and opts in to showing the login page.
 */
class SsoSignInProvidersTest extends TestCase
{
    private RouteParserInterface $routeParser;

    protected function setUp(): void
    {
        $this->routeParser = $this->createMock(RouteParserInterface::class);
        $this->routeParser->method('urlFor')->willReturnCallback(
            fn (string $name, array $data = [], array $query = []) =>
                '/' . $name . ($query ? '?' . http_build_query($query) : '')
        );
    }

    private function saml(array $workflow): ConfigService
    {
        $config = new ConfigService();
        $config->authentication = new SAMLAuthentication();
        $config->samlSettings = ['workflow' => $workflow];
        return $config;
    }

    private function cas(array $settings): ConfigService
    {
        $config = new ConfigService();
        $config->authentication = new CASAuthentication();
        $config->casSettings = $settings + ['config' => ['server' => 'cas.example.com']];
        return $config;
    }

    public function testNoSsoConfiguredHasNoButtons(): void
    {
        $this->assertSame([], SsoSignInProviders::build(new ConfigService(), $this->routeParser, ''));
    }

    public function testSamlWithoutTheFlagHasNoButton(): void
    {
        $config = $this->saml(['jit' => true]);

        $this->assertTrue($config->isSamlEnabled());
        $this->assertFalse($config->isSamlLoginPageShown());
        $this->assertSame([], SsoSignInProviders::build($config, $this->routeParser, ''));
    }

    public static function flagValues(): array
    {
        return [
            'true' => [true, true],
            '1' => [1, true],
            "'true'" => ['true', true],
            "'1'" => ['1', true],
            "'yes'" => ['yes', true],
            "'on'" => ['on', true],
            'false' => [false, false],
            '0' => [0, false],
            "'false'" => ['false', false],
            "'no'" => ['no', false],
            "''" => ['', false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('flagValues')]
    public function testShowLoginPageFlagValues(mixed $value, bool $shown): void
    {
        $this->assertSame($shown, $this->saml(['showLoginPage' => $value])->isSamlLoginPageShown());
        $this->assertSame($shown, $this->cas(['showLoginPage' => $value])->isCasLoginPageShown());
    }

    public function testSamlSettingsWithoutSamlAuthenticationHaveNoButton(): void
    {
        // SAML settings left behind after switching back to username/password: /saml/login doesn't exist.
        $config = new ConfigService();
        $config->authentication = new WebAuthentication();
        $config->samlSettings = ['workflow' => ['showLoginPage' => true]];

        $this->assertFalse($config->isSamlEnabled());
        $this->assertFalse($config->isSamlLoginPageShown());
        $this->assertSame([], SsoSignInProviders::build($config, $this->routeParser, ''));
    }

    public function testCasSettingsUnderSamlAuthenticationHaveNoCasButton(): void
    {
        // CAS settings left behind under SAML: /cas/login doesn't exist, so building its URL would throw.
        $config = $this->saml(['showLoginPage' => true]);
        $config->casSettings = ['showLoginPage' => true, 'config' => ['server' => 'cas.example.com']];

        $this->assertFalse($config->isCasEnabled());

        $providers = SsoSignInProviders::build($config, $this->routeParser, '');
        $this->assertSame(['saml'], array_column($providers, 'type'));
    }

    public function testEmptyCasSettingsAreNotCas(): void
    {
        $config = new ConfigService();
        $config->authentication = new CASAuthentication();
        $config->casSettings = [];

        $this->assertFalse($config->isCasEnabled());
    }

    public function testSamlButtonCarriesThePriorRoute(): void
    {
        $providers = SsoSignInProviders::build(
            $this->saml(['showLoginPage' => true]),
            $this->routeParser,
            '/displays/displays'
        );

        $this->assertCount(1, $providers);
        $this->assertSame('saml', $providers[0]['type']);
        $this->assertSame('SAML', $providers[0]['name']);
        $this->assertSame('Sign in with SAML', $providers[0]['label']);
        $this->assertSame('/saml.login?priorRoute=%2Fdisplays%2Fdisplays', $providers[0]['url']);
    }

    public function testSamlButtonWithoutPriorRoute(): void
    {
        $config = $this->saml(['showLoginPage' => true]);

        $this->assertSame('/saml.login', SsoSignInProviders::build($config, $this->routeParser, '')[0]['url']);
    }

    public function testSamlButtonUsesTheConfiguredName(): void
    {
        $providers = SsoSignInProviders::build(
            $this->saml(['showLoginPage' => true, 'name' => '  Okta ']),
            $this->routeParser,
            ''
        );

        $this->assertSame('Okta', $providers[0]['name']);
        $this->assertSame('Sign in with Okta', $providers[0]['label']);
    }

    public function testBlankNameFallsBackToTheDefault(): void
    {
        $config = $this->cas(['showLoginPage' => true, 'name' => '   ']);

        $this->assertSame('CAS', SsoSignInProviders::build($config, $this->routeParser, '')[0]['name']);
    }

    public function testCasWithoutTheFlagHasNoButton(): void
    {
        $config = $this->cas([]);

        $this->assertTrue($config->isCasEnabled());
        $this->assertFalse($config->isCasLoginPageShown());
        $this->assertSame([], SsoSignInProviders::build($config, $this->routeParser, ''));
    }

    public function testCasButtonUsesTheConfiguredName(): void
    {
        $providers = SsoSignInProviders::build(
            $this->cas(['showLoginPage' => true, 'name' => 'University Login']),
            $this->routeParser,
            '/x'
        );

        $this->assertCount(1, $providers);
        $this->assertSame('cas', $providers[0]['type']);
        $this->assertSame('Sign in with University Login', $providers[0]['label']);
        $this->assertSame('/cas.login', $providers[0]['url']);
    }
}
