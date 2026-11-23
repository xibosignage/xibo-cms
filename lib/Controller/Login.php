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
namespace Xibo\Controller;

use RobThree\Auth\TwoFactorAuth;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Xibo\Entity\User;
use Xibo\Factory\UserFactory;
use Xibo\Helper\Environment;
use Xibo\Helper\HttpsDetect;
use Xibo\Helper\LogoutTrait;
use Xibo\Helper\Random;
use Xibo\Helper\SafeRedirect;
use Xibo\Helper\Session;
use Xibo\Helper\SsoSignInProviders;
use Xibo\Service\BrandServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\ExpiredException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Login
 * @package Xibo\Controller
 */
class Login extends Base
{
    use LogoutTrait;

    public function __construct(
        private readonly Session $session,
        private readonly UserFactory $userFactory,
        private readonly \Stash\Interfaces\PoolInterface $pool,
        private readonly BrandServiceInterface $brandService,
    ) {
    }

    /**
     * Output a login form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function loginForm(Request $request, Response $response): \Psr\Http\Message\ResponseInterface
    {
        // Sanitize the body
        $sanitizedRequestBody = $this->getSanitizer($request->getParams());

        // Check to see if the user has provided a special token
        $nonce = $sanitizedRequestBody->getString('nonce');
        $loginError = '';

        if ($nonce != '') {
            // We have a nonce provided, so validate that in preference to showing the form.
            $nonce = explode('::', $nonce);
            $this->getLog()->debug('Nonce is ' . var_export($nonce, true));

            $cache = $this->pool->getItem('/nonce/' . $nonce[0]);

            $validated = $cache->get();

            if ($cache->isMiss()) {
                $this->getLog()->error('Expired nonce used.');
                $loginError = __('This link has expired.');
            } else if (!password_verify($nonce[1], $validated['hash'])) {
                $this->getLog()->error('Invalid nonce used.');
                $loginError = __('This link has expired.');
            } else {
                // We're valid.
                $this->pool->deleteItem('/nonce/' . $nonce[0]);

                try {
                    $user = $this->userFactory->getById($validated['userId']);

                    // A reserved user never signs in here.
                    $this->userFactory->assertNotReserved((int)$user->userId);

                    // Log in this user
                    $user->touch(true);

                    $this->getLog()->info($user->userName . ' user logged in via token.');

                    // Set the userId on the log object
                    $this->getLog()->setUserId($user->userId);
                    $this->getLog()->setIpAddress($request->getAttribute('ip_address'));

                    // Expire all sessions
                    $session = $this->session;

                    // this is a security measure in case the user is logged in somewhere else.
                    // (not this one though, otherwise we will deadlock
                    $session->expireAllSessionsForUser($user->userId);

                    // Switch Session ID's
                    $session->setIsExpired(0);
                    $session->regenerateSessionId();
                    $session->setUser($user->userId);
                    $this->getLog()->setSessionHistoryId($session->get('sessionHistoryId'));

                    // Audit Log
                    $this->getLog()->audit('User', $user->userId, 'Login Granted via token', [
                        'UserAgent' => $request->getHeader('User-Agent')
                    ]);

                    // Commit the new session to the DB before the redirect, so
                    // the immediate /user/me the React app fires on load can't
                    // race the session write. See Session::persist().
                    $this->session->persist();

                    return $response->withRedirect($this->urlFor($request, 'home'));
                } catch (NotFoundException $notFoundException) {
                    $this->getLog()->error('Valid nonce for non-existing user');
                    $loginError = __('This link has expired.');
                }
            }
        }

        $passwordReminderEnabled = $this->isPasswordReminderEnabled();
        // A CAS-only page (just the CAS button), unless CAS opts in to showing the full login page.
        $authCASEnabled = $this->getConfig()->isCasEnabled() && !$this->getConfig()->isCasLoginPageShown();
        $priorRoute = SafeRedirect::sanitizeRoute($sanitizedRequestBody->getString('priorRoute'));
        $logoUrl = $this->brandService->getAssetUrl(BrandServiceInterface::SLOT_LOGO);

        // Build config blob for the React SPA shell
        $loginConfig = [
            'priorRoute' => $priorRoute,
            'loginError'              => $loginError,
            'logoUrl'                 => $logoUrl,
            'logoDarkUrl'             => $this->brandService->getAssetUrl(BrandServiceInterface::SLOT_LOGO_DARK),
            'passwordReminderEnabled' => $passwordReminderEnabled,
            'authCASEnabled'          => $authCASEnabled,
            // SAML/CAS buttons, when they opt in to showing the login page.
            'signInProviders'         => SsoSignInProviders::build(
                $this->getConfig(),
                RouteContext::fromRequest($request)->getRouteParser(),
                $priorRoute
            ),
            'version'                 => Environment::getVersion(),
            'appName'                 => $this->getConfig()->getThemeConfig('app_name', 'Xibo'),
            'supportUrl'              => $this->getConfig()->getThemeConfig(
                'theme_url',
                'https://xibosignage.com'
            ),
            'sourceUrl'               => $this->getConfig()->getThemeConfig(
                'cms_source_url',
                'https://github.com/xibosignage/xibo-cms'
            ),
            'removeLicenceFromLogin'  => (bool)$this->getConfig()->getThemeConfig(
                'remove_licence_from_login',
                false
            ),
            'i18n' => [
                // Common
                'password'             => __('Password'),
                'passwordPlaceholder'  => __('Enter password'),
                'showPassword'         => __('Show password'),
                'hidePassword'         => __('Hide password'),
                'loginButton'          => __('Login'),
                'backToLogin'          => __('Back to login'),
                'loginInstead'         => __('Login instead?'),
                'unexpectedError'      => __('An unexpected error occurred. Please try again.'),
                'rateLimitError'       => __('Too many attempts. Please wait before trying again.'),
                // Login form
                'loginPrompt'          => __('Please provide your credentials'),
                'identifier'           => __('Username or email'),
                'identifierPlaceholder' => __('Enter username or email'),
                'nextButton'           => __('Next'),
                'changeIdentifier'     => __('Change'),
                'changeIdentifierLabel' => __('Change username or email'),
                'identifierRequired'   => __('Please enter your username or email.'),
                'forgotPasswordLink'   => __('Forgot Password?'),
                'invalidCredentials'   => __('Username or password incorrect.'),
                'casPrompt'            => __('Connect with the Central Authentication Server'),
                'casLoginButton'       => __('CAS Login'),
                'signInProvidersDivider' => __('or'),
                // Two-factor
                'tfaPrompt'            => __('Please provide your Two Factor Authorisation Code'),
                'tfaRecoveryPrompt'    => __('Please provide your Two Factor Recovery Code'),
                'tfaCode'              => __('Code'),
                'tfaRecoveryCode'      => __('Recovery Code'),
                'tfaCodePlaceholder'         => __('Enter code'),
                'tfaRecoveryCodePlaceholder' => __('Enter recovery code'),
                'tfaVerifyButton'      => __('Verify'),
                'tfaSwitchToRecovery'  => __('Use Recovery Code instead?'),
                'tfaSwitchToCode'      => __('Use Two Factor Code instead?'),
                'tfaInvalidCode'       => __('Authentication code incorrect.'),
                // Forgot password
                'forgotPrompt'         => __(
                    'Please provide your username or email and we will send a password reset link.'
                ),
                'forgotSendButton'     => __('Send Reset'),
                'forgotSentMessage'    =>
                    __('A reminder email will be sent to the associated email address if this user exists.'),
                'forgotSentReturnLink' => __('Return to login'),
                // Footer
                'versionLabel'         => __('Version'),
                'sourceLabel'          => __('Source'),
                'aboutLabel'           => __('About'),
                'closeLabel'           => __('Close'),
            ],
        ];

        $this->getState()->template = 'login-spa';
        $this->getState()->setData([
            'loginConfigJson' => json_encode(
                $loginConfig,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
            ),
            'loginJsUrl'      => \Xibo\Helper\ViteManifest::getJsUrl('login.html', $this->getConfig()->rootUri()),
            'loginCssUrls'    => \Xibo\Helper\ViteManifest::getCssUrls('login.html', $this->getConfig()->rootUri()),
            'assetBase'       => \Xibo\Helper\ViteManifest::getAssetBase($this->getConfig()->rootUri()),
            'viteClientUrl'   => \Xibo\Helper\ViteManifest::getClientUrl(),
            'viteRefreshUrl'  => \Xibo\Helper\ViteManifest::getRefreshUrl(),
        ]);
        return $this->render($request, $response);
    }

    /**
     * Login
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function login(Request $request, Response $response): \Psr\Http\Message\ResponseInterface
    {
        $parsedRequest = $this->getSanitizer($request->getParams());
        $priorRoute = $parsedRequest->getString('priorRoute');

        // The username field takes a username or an email (the field name stays for API clients and password managers).
        $identifier = $parsedRequest->getString('username') ?? '';
        $password = $parsedRequest->getString('password') ?? '';

        try {
            // Per-IP rate limit: 5 failed login attempts per 15 minutes.
            // Checked before the user lookup so attackers cannot enumerate usernames freely.
            $this->enforceRateLimit($request, 'login', 5, 900);
        } catch (AccessDeniedException) {
            return $this->loginRateLimitedResponse($response);
        }

        // Per-identifier rate limit: 10 failed login attempts per 15 minutes, from any IP.
        // Keyed on what was typed, so unknown and shared identifiers are limited the same way.
        if ($this->isLoginThrottled($this->getIdentifierThrottleKey($identifier))) {
            return $this->loginRateLimitedResponse($response);
        }

        $userId = null;

        try {
            // Get our user
            try {
                $user = $this->userFactory->getByLoginIdentifier($identifier);
                $userId = $user->userId;

                // Per-user rate limit, so other spellings that match the same user share one limit.
                if ($this->isLoginThrottled($this->getUserThrottleKey($userId))) {
                    return $this->loginRateLimitedResponse($response);
                }

                // Retired user
                if ($user->retired === 1) {
                    $this->checkDummyPassword($password);
                    throw new AccessDeniedException(
                        __('Sorry this account does not exist or does not have permission to access the web portal.')
                    );
                }

                // Check password
                $user->checkPassword($password);

                // Successful auth — drop the failure counters for this IP, identifier and user.
                $this->resetRateLimit($request, 'login');
                $this->pool->deleteItem($this->getIdentifierThrottleKey($identifier));
                $this->pool->deleteItem($this->getUserThrottleKey($userId));

                // check if 2FA is enabled
                if ($user->twoFactorTypeId != 0) {
                    $_SESSION['tfaUsername'] = $user->userName;

                    if ($user->twoFactorTypeId === 1) {
                        $this->sendTwoFactorEmail($user, $request);
                    }
                    return $response->withJson([
                        'status'     => '2fa_required',
                        'priorRoute' => SafeRedirect::sanitizeRoute($priorRoute),
                    ]);
                }

                // We are logged in, so complete the login flow
                $this->completeLoginFlow($user, $request);

                // Commit the new session to the DB before responding, so the
                // immediate /user/me the React app fires after redirect can't
                // race the session write. See Session::persist().
                $this->session->persist();

                return $response->withJson([
                    'status' => 'ok',
                    'isPasswordChangeRequired' => $user->isPasswordChangeRequired === 1,
                ]);
            } catch (NotFoundException) {
                $this->checkDummyPassword($password);
                throw new AccessDeniedException(__('User not found'));
            }
        } catch (AccessDeniedException $e) {
            $this->recordRateLimitHit($request, 'login', 900);
            $this->recordLoginThrottleHit($this->getIdentifierThrottleKey($identifier));
            if ($userId !== null) {
                $this->recordLoginThrottleHit($this->getUserThrottleKey($userId));
            }
            $this->getLog()->warning($e->getMessage());

            return $response->withJson(['status' => 'error', 'message' => __('Username or Password incorrect')], 401);
        } catch (ExpiredException) {
            return $response->withJson(['status' => 'error', 'message' => __('Session expired')], 401);
        }
    }

    /**
     * Login step 2: the sign-in methods for a username or email.
     * Unknown, retired and shared-email users get the same answer as a password-only user.
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function methods(Request $request, Response $response): \Psr\Http\Message\ResponseInterface
    {
        $parsedRequest = $this->getSanitizer($request->getParams());
        $priorRoute = SafeRedirect::sanitizeRoute($parsedRequest->getString('priorRoute'));

        try {
            $this->enforceRateLimit($request, 'login-methods', 60, 60);
        } catch (AccessDeniedException $e) {
            return $response->withJson(['status' => 'rate_limited', 'message' => $e->getMessage()], 429);
        }
        $this->recordRateLimitHit($request, 'login-methods', 60);

        try {
            $user = $this->userFactory->getByLoginIdentifier($parsedRequest->getString('identifier') ?? '');
            if ($user->retired === 1) {
                $user = null;
            }
        } catch (NotFoundException) {
            $user = null;
        }

        $methods = [];

        $methods[] = ['type' => 'password'];

        return $response->withJson(['status' => 'ok', 'methods' => $methods]);
    }

    private function getIdentifierThrottleKey(string $identifier): string
    {
        return 'throttle/login-identifier/' . hash('sha256', strtolower(trim($identifier)));
    }

    private function getUserThrottleKey(int $userId): string
    {
        return 'throttle/login-user/' . $userId;
    }

    /**
     * 10 failed logins per 15 minutes, from any IP.
     */
    private function isLoginThrottled(string $key): bool
    {
        $count = (int)($this->pool->getItem($key)->get() ?? 0);
        if ($count >= 10) {
            $this->getLog()->warning('Rate limit hit: ' . $key . ' count=' . $count);
            return true;
        }

        return false;
    }

    private function recordLoginThrottleHit(string $key): void
    {
        $item = $this->pool->getItem($key);
        $item->set((int)($item->get() ?? 0) + 1);
        $item->expiresAfter(900);
        $this->pool->save($item);
    }

    private function loginRateLimitedResponse(Response $response): \Psr\Http\Message\ResponseInterface
    {
        return $response->withJson(
            ['status' => 'rate_limited', 'message' => __('Too many attempts. Please wait and try again.')],
            429
        );
    }

    /**
     * Spend the same time as a real password check, so a missing user can't be told apart by timing.
     * @param string $password
     */
    private function checkDummyPassword(string $password): void
    {
        password_verify($password, '$2y$12$pTzENcCzLqgtSdPKD5gdkOBLlWC/LllnFPIU01XXyEcXjlHRpY55W');
    }

    /**
     * Forgotten password link requested
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws ConfigurationException
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function forgottenPassword(Request $request, Response $response): \Psr\Http\Message\ResponseInterface
    {
        $mailFrom = $this->getConfig()->getSetting('mail_from');
        $parsedRequest = $this->getSanitizer($request->getParams());

        if (!$this->isPasswordReminderEnabled()) {
            throw new ConfigurationException(__('This feature has been disabled by your administrator'));
        }

        // Per-IP rate limit: 3 password-reset requests per hour. Independent of whether the
        // requested username exists, so an attacker cannot use this endpoint for unbounded
        // enumeration even with timing-based username inference.
        try {
            $this->enforceRateLimit($request, 'pwreset', 3, 3600);
        } catch (AccessDeniedException) {
            // Constant-time pad on the throttle path so attackers can't distinguish
            // "rate-limited" from "user not found" via response timing.
            usleep(random_int(200000, 400000));
            return $response->withJson([
                'status' => 'ok',
                'message' => __('A reminder email will been sent to this user if they exist'),
            ]);
        }
        $this->recordRateLimitHit($request, 'pwreset', 3600);

        // Check to see if the provided username or email is valid, and if so, record a nonce and send them a link
        try {
            $user = $this->userFactory->getByLoginIdentifier($parsedRequest->getString('username') ?? '');

            $this->getLog()->info('Forgotten Password Request for ' . $user->userName);

            // Does this user have an email address associated to their user record?
            if ($user->email == '') {
                throw new NotFoundException(__('No email'));
            }

            // Nonce parts (nonce isn't ever stored, only the hash of it is stored, it only exists in the email)
            // Both halves are 20 hex chars (80 bits each) — the action half is the cache lookup
            // key, the nonce half is the secret that's bcrypt-hashed and compared.
            $action = 'user-reset-password-' . Random::generateString(20);
            $nonce = Random::generateString(20);

            // Create a nonce for this user and store it somewhere
            $cache = $this->pool->getItem('/nonce/' . $action);

            $cache->set([
                'action' => $action,
                'hash' => password_hash($nonce, PASSWORD_DEFAULT),
                'userId' => $user->userId
            ]);
            $cache->expiresAfter(1800); // 30 minutes?

            // Save cache
            $this->pool->save($cache);

            // Make a link. Pass config so WHITELIST_HOSTS (if set) defeats Host-header
            // injection into the reset link sent off-system to the user's email.
            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $link = ((new HttpsDetect($this->getConfig()))->getRootUrl())
                . $routeParser->urlFor('login') . '?nonce=' . $action . '::' . $nonce;

            // Uncomment this to get a debug message showing the link.
            //$this->getLog()->debug('Link is:' . $link);

            // Send the mail
            $mail = new \PHPMailer\PHPMailer\PHPMailer();
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->From = $mailFrom;
            $msgFromName = $this->getConfig()->getSetting('mail_from_name');

            if ($msgFromName != null) {
                $mail->FromName = $msgFromName;
            }

            $mail->Subject = __('Password Reset');
            $mail->addAddress($user->email);

            // Body
            $mail->isHTML(true);

            // We need to specify the style for the pw reset button since mailers usually ignore bootstrap classes
            $linkButton = '<a href="' . $link . '"
                    style="
                        display: inline-block;
                        padding: 8px 15px;
                        font-size: 15px;
                        color: #FFFFFF;
                        background-color: #428BCA;
                        text-decoration: none;
                        border-radius: 5px;
                    ">
                    ' . __('Reset Password') . '
                </a>';

            $mail->Body = $this->generateEmailBody(
                $mail->Subject,
                '<p>' . __('You are receiving this email because a password reminder was requested for your account. If you did not make this request, please report this email to your administrator immediately.') . '</p>' //phpcs:ignore
                . $linkButton
                . '<p style="margin-top:10px; font-size:14px; color:#555555;">'
                . __('If the button does not work, copy and paste the following URL into your browser:')
                . '<br><a href="' . $link . '">' . $link . '</a></p>'
            );

            if (!$mail->send()) {
                throw new ConfigurationException('Unable to send password reminder to ' . $user->email);
            }

            // Audit Log
            $this->getLog()->audit('User', $user->userId, 'Password Reset Link Granted', [
                'UserAgent' => $request->getHeader('User-Agent')
            ]);
        } catch (GeneralException) {
            // Constant-time pad: the success path sends mail (PHPMailer SMTP round trip,
            // typically a few hundred ms). The failure path returns immediately. Without
            // padding here, the response-time delta lets an attacker enumerate which
            // usernames exist despite the identical flash message. Pad to 200-400ms.
            usleep(random_int(200000, 400000));
        }

        return $response->withJson([
            'status' => 'ok',
            'message' => __('A reminder email will been sent to this user if they exist'),
        ]);
    }

    /**
     * Log out
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function logout(Request $request, Response $response): \Psr\Http\Message\ResponseInterface
    {
        $redirect = true;

        if ($request->getQueryParam('redirect') != null) {
            $redirect = $request->getQueryParam('redirect');
        }

        $this->completeLogoutFlow($this->getUser(), $this->session, $this->getLog(), $request);

        if ($redirect) {
            return $response->withRedirect($this->urlFor($request, 'home'));
        }

        return $response->withStatus(200);
    }

    /**
     * Ping Pong
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function pingPong(Request $request, Response $response): \Psr\Http\Message\ResponseInterface
    {
        $parseRequest = $this->getSanitizer($request->getQueryParams());
        $this->session->refreshExpiry = ($parseRequest->getCheckbox('refreshSession') == 1);
        $this->getState()->success = true;

        return $this->render($request, $response);
    }

    /**
     * Public JSON endpoint returning just the CMS version, wrapped in a `data` envelope.
     * No authentication required. Consumed by external integrations (e.g. the Canva
     * connector) that need to identify the CMS version without authenticating first.
     */
    public function about(Request $request, Response $response): \Psr\Http\Message\ResponseInterface
    {
        return $response->withJson([
            'data' => [
                'version' => Environment::getVersion(),
            ],
        ]);
    }

    /**
     * Public JSON endpoint returning branding + version info for the About modal.
     * No authentication required.
     */
    public function aboutConfig(Request $request, Response $response): \Psr\Http\Message\ResponseInterface
    {
        $payload = [
            'version'     => Environment::getVersion(),
            'revision'    => Environment::getGitCommit(),
            'appName'     => $this->getConfig()->getThemeConfig('app_name', 'Xibo'),
            'productName' => $this->getConfig()->getThemeConfig('theme_title', 'Xibo Digital Signage'),
            'logoUrl'     => $this->aboutAssetUrl(BrandServiceInterface::SLOT_LOGO),
            'logoIconUrl' => $this->aboutAssetUrl(BrandServiceInterface::SLOT_LOGO_ICON),
            'supportUrl'  => $this->getConfig()->getThemeConfig('theme_url', 'https://xibosignage.com'),
            'sourceUrl'   => $this->getConfig()->getThemeConfig(
                'cms_source_url',
                'https://github.com/xibosignage/xibo-cms'
            ),
            'aboutText'   => $this->getConfig()->getThemeConfig('about_text') ?? '',
        ];

        return $response->withJson($payload);
    }

    /**
     * A brand image's URL for the About modal, which links the brand folder under the root URI as before.
     */
    private function aboutAssetUrl(string $slot): string
    {
        $url = $this->brandService->getAssetUrl($slot);
        return str_starts_with($url, '/brand/') ? $this->getConfig()->rootUri() . ltrim($url, '/') : $url;
    }

    /**
     * Generate an email body
     * @param string $subject
     * @param string $body
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function generateEmailBody(string $subject, string $body): string
    {
        return $this->renderTemplateToString('email-template', [
            'config' => $this->getConfig(),
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    /**
     * Enforce a per-IP rate limit using the Stash cache.
     * Throws AccessDeniedException when the configured threshold is exceeded inside the window.
     *
     * @param Request $request
     * @param string $bucket cache-key namespace, e.g. 'login' or 'pwreset'
     * @param int $maxAttempts threshold
     * @param int $windowSeconds sliding window length
     * @throws AccessDeniedException
     */
    private function enforceRateLimit(Request $request, string $bucket, int $maxAttempts, int $windowSeconds): void
    {
        $ip = $request->getAttribute('ip_address') ?? 'unknown';
        $item = $this->pool->getItem('throttle/' . $bucket . '/' . $ip);
        $count = (int)($item->get() ?? 0);
        if ($count >= $maxAttempts) {
            $this->getLog()->warning(sprintf(
                'Rate limit hit: bucket=%s ip=%s count=%d',
                $bucket,
                $ip,
                $count
            ));
            throw new AccessDeniedException(__('Too many attempts. Please wait and try again.'));
        }
    }

    /**
     * Increment the per-IP rate-limit counter for the given bucket.
     * Called after the protected action to count one attempt against the window.
     *
     * @param Request $request
     * @param string $bucket cache-key namespace
     * @param int $windowSeconds sliding window length
     */
    private function recordRateLimitHit(Request $request, string $bucket, int $windowSeconds): void
    {
        $ip = $request->getAttribute('ip_address') ?? 'unknown';
        $item = $this->pool->getItem('throttle/' . $bucket . '/' . $ip);
        $count = (int)($item->get() ?? 0);
        $item->set($count + 1);
        $item->expiresAfter($windowSeconds);
        $this->pool->save($item);
    }

    /**
     * Reset the per-IP counter for a bucket — used after a successful login so
     * the user isn't penalised for prior failures on the same IP.
     *
     * @param Request $request
     * @param string $bucket cache-key namespace
     */
    private function resetRateLimit(Request $request, string $bucket): void
    {
        $ip = $request->getAttribute('ip_address') ?? 'unknown';
        $this->pool->deleteItem('throttle/' . $bucket . '/' . $ip);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \RobThree\Auth\TwoFactorAuthException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function twoFactorAuthValidate(Request $request, Response $response): \Psr\Http\Message\ResponseInterface
    {
        // Guard: ensure the 2FA session bridge is present before proceeding
        if (!isset($_SESSION['tfaUsername'])) {
            return $response->withJson(
                ['status' => 'error', 'message' => __('Session has expired, please log in again')],
                401
            );
        }

        // Brute-force protection on the TOTP / recovery-code submission. Matches the
        // bare-login bucket (5 / 15 min). Separate bucket so a failed 2FA attempt
        // doesn't bleed over into the password path and vice versa.
        try {
            $this->enforceRateLimit($request, 'twofactor', 5, 900);
        } catch (AccessDeniedException $e) {
            return $response->withJson(['status' => 'rate_limited', 'message' => $e->getMessage()], 429);
        }

        $user = $this->userFactory->getByName($_SESSION['tfaUsername']);
        $result = false;
        $updatedCodes = [];

        $sanitizedParams = $this->getSanitizer($request->getParams());
        $hasCode     = $sanitizedParams->hasParam('code');
        $hasRecovery = $sanitizedParams->hasParam('recoveryCode');

        if ($hasCode) {
            $issuerSettings = $this->getConfig()->getSetting('TWOFACTOR_ISSUER');
            $appName = $this->getConfig()->getThemeConfig('app_name');

            if ($issuerSettings !== '') {
                $issuer = $issuerSettings;
            } else {
                $issuer = $appName;
            }

            $tfa = new TwoFactorAuth($issuer);

            if ($user->twoFactorTypeId === 1 && $user->email !== '') {
                $result = $tfa->verifyCode($user->twoFactorSecret, $sanitizedParams->getString('code'), 9);
            } else {
                $result = $tfa->verifyCode($user->twoFactorSecret, $sanitizedParams->getString('code'), 3);
            }
        } elseif ($hasRecovery) {
            // get the array of recovery codes, go through them and try to match provided code
            $codes = $user->twoFactorRecoveryCodes;

            foreach (json_decode($codes) as $code) {
                // if the provided recovery code matches one stored in the database, we want to log in the user
                if (hash_equals($code, $sanitizedParams->getString('recoveryCode'))) {
                    $result = true;
                } else {
                    $updatedCodes[] = $code;
                }
            }

            // recovery codes are one time use, as such we want to update user recovery codes and remove the one that
            // was just used.
            $user->updateRecoveryCodes(json_encode($updatedCodes));
        }

        if ($result) {
            // Successful 2FA — drop the failure counter for this IP.
            $this->resetRateLimit($request, 'twofactor');

            // We are logged in at this point
            $this->completeLoginFlow($user, $request);

            //unset the session tfaUsername
            unset($_SESSION['tfaUsername']);

            // Commit the new session to the DB before responding, so the
            // immediate /user/me the React app fires after redirect can't race
            // the session write. Must run after the tfaUsername unset above so
            // that removal is persisted. See Session::persist().
            $this->session->persist();

            return $response->withJson([
                'status' => 'ok',
                'isPasswordChangeRequired' => $user->isPasswordChangeRequired === 1,
            ]);
        } else {
            // Record one failure against the bucket so brute-forcers progress toward the wall.
            $this->recordRateLimitHit($request, 'twofactor', 900);

            $this->getLog()->error('Authentication code incorrect, redirecting to login page');

            return $response->withJson(
                ['status' => 'error', 'message' => __('Authentication code incorrect')],
                401
            );
        }
    }

    /**
     * @param \Xibo\Entity\User $user
     * @param Request $request
     */
    private function completeLoginFlow(User $user, Request $request): void
    {
        // A reserved user never signs in here.
        if ($this->userFactory->isReserved((int)$user->userId)) {
            throw new AccessDeniedException(
                __('Sorry this account does not exist or does not have permission to access the web portal.')
            );
        }

        $user->touch();

        $this->getLog()->info($user->userName . ' user logged in.');

        // Set the userId on the log object
        $this->getLog()->setUserId($user->userId);
        $this->getLog()->setIpAddress($request->getAttribute('ip_address'));

        // Switch Session ID's
        $session = $this->session;
        $session->setIsExpired(0);
        $session->regenerateSessionId();
        $session->setUser($user->userId);

        $this->getLog()->setSessionHistoryId($session->get('sessionHistoryId'));

        // Audit Log
        $this->getLog()->audit('User', $user->userId, 'Login Granted', [
                'UserAgent' => $request->getHeader('User-Agent')
        ]);
    }

    private function isPasswordReminderEnabled(): bool
    {
        $setting = $this->getConfig()->getSetting('PASSWORD_REMINDER_ENABLED');
        $mailFrom = $this->getConfig()->getSetting('mail_from');
        return ($setting === 'On' || $setting === 'On except Admin') && $mailFrom !== '';
    }

    /**
     * Send the email 2FA code to the user's email address.
     * Called when email 2FA (typeId=1) is required during login.
     *
     * @throws NotFoundException if the user has no email address configured
     * @throws InvalidArgumentException if mail_from is not configured
     * @throws GeneralException if the email fails to send
     */
    private function sendTwoFactorEmail(User $user, Request $request): void
    {
        if ($user->email == '') {
            throw new NotFoundException(__('No email'));
        }

        $issuerSettings = $this->getConfig()->getSetting('TWOFACTOR_ISSUER');
        $appName = $this->getConfig()->getThemeConfig('app_name');
        $issuer = ($issuerSettings !== '') ? $issuerSettings : $appName;

        $tfa = new TwoFactorAuth($issuer);
        $code = $tfa->getCode($user->twoFactorSecret);

        // Dev mode: log the code instead of emailing it
        if (Environment::isDevMode()) {
            $this->getLog()->info('DEV MODE — 2FA email code for ' . $user->userName . ': ' . $code);
            return;
        }

        $mailFrom = $this->getConfig()->getSetting('mail_from');
        if ($mailFrom == '') {
            throw new InvalidArgumentException(
                __('Sending email address in CMS Settings is not configured'),
                'mail_from'
            );
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->From = $mailFrom;
        $msgFromName = $this->getConfig()->getSetting('mail_from_name');
        if ($msgFromName != null) {
            $mail->FromName = $msgFromName;
        }

        $mail->Subject = __('Two Factor Authentication');
        $mail->addAddress($user->email);
        $mail->isHTML(true);
        $mail->Body = $this->generateEmailBody(
            $mail->Subject,
            '<p>'
            . __('You are receiving this email because two factor email authorisation is enabled'
                . ' in your CMS user account. If you did not make this request, please report'
                . ' this email to your administrator immediately.')
            . '</p>'
            . '<p>' . $code . '</p>'
        );

        if (!$mail->send()) {
            throw new GeneralException(
                __('Unable to send two factor code to email address associated with this user')
            );
        }

        $this->getLog()->audit('User', $user->userId, 'Two Factor Code email sent', [
            'UserAgent' => $request->getHeader('User-Agent')
        ]);
    }
}
