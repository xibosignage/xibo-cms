<?php
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
namespace Xibo\Controller;

use OpenApi\Attributes as OA;
use RobThree\Auth\TwoFactorAuth;
use Slim\Flash\Messages;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Xibo\Entity\User;
use Xibo\Factory\UserFactory;
use Xibo\Helper\Environment;
use Xibo\Helper\HttpsDetect;
use Xibo\Helper\LogoutTrait;
use Xibo\Helper\Random;
use Xibo\Helper\Session;
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

    private Messages $flash;

    public function __construct(
        private readonly Session $session,
        private readonly UserFactory $userFactory,
        private readonly \Stash\Interfaces\PoolInterface $pool,
    ) {
    }

    protected function getFlash(): Messages
    {
        return $this->flash;
    }

    public function setFlash(Messages $messages): void
    {
        $this->flash = $messages;
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

        if ($nonce != '') {
            // We have a nonce provided, so validate that in preference to showing the form.
            $nonce = explode('::', $nonce);
            $this->getLog()->debug('Nonce is ' . var_export($nonce, true));

            $cache = $this->pool->getItem('/nonce/' . $nonce[0]);

            $validated = $cache->get();

            if ($cache->isMiss()) {
                $this->getLog()->error('Expired nonce used.');
                $this->getFlash()->addMessageNow('login_message', __('This link has expired.'));
            } else if (!password_verify($nonce[1], $validated['hash'])) {
                $this->getLog()->error('Invalid nonce used.');
                $this->getFlash()->addMessageNow('login_message', __('This link has expired.'));
            } else {
                // We're valid.
                $this->pool->deleteItem('/nonce/' . $nonce[0]);

                try {
                    $user = $this->userFactory->getById($validated['userId']);

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

                    return $response->withRedirect($this->urlFor($request, 'home'));
                } catch (NotFoundException $notFoundException) {
                    $this->getLog()->error('Valid nonce for non-existing user');
                    $this->getFlash()->addMessageNow('login_message', __('This link has expired.'));
                }
            }
        }

        $passwordReminderEnabled = $this->isPasswordReminderEnabled();
        $authCASEnabled = isset($this->getConfig()->casSettings);
        $logoUrl = $this->getBrandLogoUrl();

        // Build config blob for the React SPA shell
        $loginConfig = [
            'priorRoute' => $this->sanitizePriorRouteForOutput(
                $sanitizedRequestBody->getString('priorRoute')
            ),
            'logoUrl'                => $logoUrl,
            'passwordReminderEnabled' => $passwordReminderEnabled,
            'authCASEnabled'          => $authCASEnabled,
            'version'                 => Environment::$WEBSITE_VERSION_NAME,
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
        ];

        $this->getState()->template = 'login-spa';
        $this->getState()->setData([
            'loginConfigJson' => json_encode(
                $loginConfig,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
            ),
            'loginJsUrl'      => \Xibo\Helper\ViteManifest::getJsUrl('login.html'),
            'loginCssUrl'     => \Xibo\Helper\ViteManifest::getCssUrl('login.html'),
            'viteClientUrl'   => \Xibo\Helper\ViteManifest::getClientUrl(),
            'viteRefreshUrl'  => \Xibo\Helper\ViteManifest::getRefreshUrl(),
        ]);
        return $this->render($request, $response);
    }

    /**
     * Login
     * @param Request $request
     * @param Response $response
     * @return \Slim\Http\Response
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function login(Request $request, Response $response): Response
    {
        $isJson = $this->isJsonRequest($request);
        $body = $isJson ? (json_decode((string)$request->getBody(), true) ?? []) : ($request->getParsedBody() ?? []);
        $parsedRequest = $this->getSanitizer($body);
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        // Capture the prior route (if there is one)
        $redirect = $this->urlFor($request, 'login');
        $priorRoute = $parsedRequest->getString('priorRoute');

        try {
            // Per-IP rate limit: 5 failed login attempts per 15 minutes.
            // Checked before the user lookup so attackers cannot enumerate usernames freely.
            $this->enforceRateLimit($request, 'login', 5, 900);

            // Get our username and password
            $username = $parsedRequest->getString('username');
            $password = $parsedRequest->getString('password');

            $this->getLog()->debug('Login with username ' . $username);

            // Get our user
            try {
                $user = $this->userFactory->getByName($username);

                // Retired user
                if ($user->retired === 1) {
                    throw new AccessDeniedException(
                        __('Sorry this account does not exist or does not have permission to access the web portal.')
                    );
                }

                // Check password
                $user->checkPassword($password);

                // Successful auth — drop the failure counter for this IP.
                $this->resetRateLimit($request, 'login');

                // check if 2FA is enabled
                if ($user->twoFactorTypeId != 0) {
                    $_SESSION['tfaUsername'] = $user->userName;

                    if ($isJson) {
                        if ($user->twoFactorTypeId === 1) {
                            $this->sendTwoFactorEmail($user, $request);
                        }
                        return $this->jsonResponse($response, [
                            'status'     => '2fa_required',
                            'priorRoute' => $this->sanitizePriorRouteForOutput($priorRoute),
                        ]);
                    }

                    $this->getFlash()->addMessage('priorRoute', $priorRoute);
                    return $response->withRedirect($routeParser->urlFor('login'));
                }

                // We are logged in, so complete the login flow
                $this->completeLoginFlow($user, $request);

                if ($isJson) {
                    return $this->jsonResponse($response, ['status' => 'ok']);
                }
            } catch (NotFoundException) {
                throw new AccessDeniedException(__('User not found'));
            }

            $redirect = $this->getRedirect($request, $priorRoute);
        } catch (AccessDeniedException $e) {
            $isRateLimited = str_contains($e->getMessage(), 'Too many attempts');
            if (!$isRateLimited) {
                $this->recordRateLimitHit($request, 'login', 900);
            }
            $this->getLog()->warning($e->getMessage());

            if ($isJson) {
                return $this->jsonResponse(
                    $response,
                    [
                        'status' => $isRateLimited ? 'rate_limited' : 'error',
                        'message' => __('Username or Password incorrect'),
                    ],
                    $isRateLimited ? 429 : 401
                );
            }

            $this->getFlash()->addMessage('login_message', __('Username or Password incorrect'));
            $this->getFlash()->addMessage('priorRoute', $priorRoute);
        } catch (ExpiredException $e) {
            if ($isJson) {
                return $this->jsonResponse($response, ['status' => 'error', 'message' => __('Session expired')], 401);
            }
            $this->getFlash()->addMessage('priorRoute', $priorRoute);
        }
        $this->setNoOutput(true);
        $this->getLog()->debug('Redirect to ' . $redirect);
        return $response->withRedirect($redirect);
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
        $isJson = $this->isJsonRequest($request);
        $mailFrom = $this->getConfig()->getSetting('mail_from');

        if ($isJson) {
            $jsonBody = json_decode((string)$request->getBody(), true) ?? [];
            $parsedRequest = $this->getSanitizer($jsonBody);
        } else {
            $parsedRequest = $this->getSanitizer($request->getParsedBody());
        }
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

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
            $message = __('A reminder email will been sent to this user if they exist');
            if ($isJson) {
                return $this->jsonResponse($response, ['status' => 'ok', 'message' => $message]);
            }
            $this->getFlash()->addMessage('login_message', $message);
            $this->setNoOutput(true);
            return $response->withRedirect($routeParser->urlFor('login'));
        }
        $this->recordRateLimitHit($request, 'pwreset', 3600);

        // Get our username
        $username = $parsedRequest->getString('username');

        // Log
        $this->getLog()->info('Forgotten Password Request for ' . $username);

        // Check to see if the provided username is valid, and if so, record a nonce and send them a link
        try {
            // Get our user
            /* @var User $user */
            $user = $this->userFactory->getByName($username);

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
            } else {
                $this->getFlash()->addMessage(
                    'login_message',
                    __('A reminder email will been sent to this user if they exist'),
                );
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
            $this->getFlash()->addMessage(
                'login_message',
                __('A reminder email will been sent to this user if they exist'),
            );
        }

        $message = __('A reminder email will been sent to this user if they exist');
        if ($isJson) {
            return $this->jsonResponse($response, ['status' => 'ok', 'message' => $message]);
        }

        $this->setNoOutput(true);
        return $response->withRedirect($routeParser->urlFor('login'));
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
     * Public JSON endpoint returning branding + version info for the About modal.
     * No authentication required.
     */
    public function aboutConfig(Request $request, Response $response): \Psr\Http\Message\ResponseInterface
    {
        $brandDir = rtrim($this->getConfig()->getSetting('LIBRARY_LOCATION'), '/') . '/brand';
        $rootUri = $this->getConfig()->rootUri();
        $logoFile = file_exists($brandDir . '/logo.svg') ? 'logo.svg' : 'logo.png';
        $iconFile = file_exists($brandDir . '/logo-icon.svg') ? 'logo-icon.svg' : 'logo-icon.png';

        $payload = [
            'version'     => Environment::$WEBSITE_VERSION_NAME,
            'revision'    => Environment::getGitCommit(),
            'appName'     => $this->getConfig()->getThemeConfig('app_name', 'Xibo'),
            'productName' => $this->getConfig()->getThemeConfig('theme_title', 'Xibo Digital Signage'),
            'logoUrl'     => $rootUri . 'brand/' . $logoFile,
            'logoIconUrl' => $rootUri . 'brand/' . $iconFile,
            'supportUrl'  => $this->getConfig()->getThemeConfig('theme_url', 'https://xibosignage.com'),
            'sourceUrl'   => $this->getConfig()->getThemeConfig(
                'cms_source_url',
                'https://github.com/xibosignage/xibo-cms'
            ),
            'aboutText'   => $this->getConfig()->getThemeConfig('about_text') ?? '',
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
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
     * 2FA Auth required
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws \RobThree\Auth\TwoFactorAuthException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    /**
     * @param Request $request
     * @param Response $response
     * @return \Slim\Http\Response
     * @throws \RobThree\Auth\TwoFactorAuthException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function twoFactorAuthValidate(Request $request, Response $response): Response
    {
        $isJson = $this->isJsonRequest($request);

        // Guard: ensure the 2FA session bridge is present before proceeding
        if (!isset($_SESSION['tfaUsername'])) {
            if ($isJson) {
                return $this->jsonResponse(
                    $response,
                    ['status' => 'error', 'message' => __('Session has expired, please log in again')],
                    401
                );
            }
            $this->getFlash()->addMessage('login_message', __('Session has expired, please log in again'));
            return $response->withRedirect($this->urlFor($request, 'login'));
        }

        // Brute-force protection on the TOTP / recovery-code submission. Matches the
        // bare-login bucket (5 / 15 min). Separate bucket so a failed 2FA attempt
        // doesn't bleed over into the password path and vice versa.
        try {
            $this->enforceRateLimit($request, 'twofactor', 5, 900);
        } catch (AccessDeniedException $e) {
            if ($isJson) {
                return $this->jsonResponse($response, ['status' => 'rate_limited', 'message' => $e->getMessage()], 429);
            }
            $this->getFlash()->addMessage('login_message', __('Too many attempts. Please wait and try again.'));
            return $response->withRedirect($this->urlFor($request, 'login'));
        }

        $user = $this->userFactory->getByName($_SESSION['tfaUsername']);
        $result = false;
        $updatedCodes = [];

        // Parse body: JSON or form-encoded
        if ($isJson) {
            $jsonBody = json_decode((string)$request->getBody(), true) ?? [];
            $sanitizedParams = $this->getSanitizer($jsonBody);
            $hasCode     = array_key_exists('code', $jsonBody);
            $hasRecovery = array_key_exists('recoveryCode', $jsonBody);
        } else {
            $sanitizedParams = $this->getSanitizer($request->getParams());
            $hasCode     = isset($_POST['code']);
            $hasRecovery = isset($_POST['recoveryCode']);
        }

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
                if ($code === $sanitizedParams->getString('recoveryCode')) {
                    $result = true;
                }

                if ($code !== $sanitizedParams->getString('recoveryCode')) {
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

            if ($isJson) {
                return $this->jsonResponse($response, ['status' => 'ok']);
            }

            $this->setNoOutput(true);
            return $response->withRedirect($this->getRedirect($request, $sanitizedParams->getString('priorRoute')));
        } else {
            // Record one failure against the bucket so brute-forcers progress toward the wall.
            $this->recordRateLimitHit($request, 'twofactor', 900);

            $this->getLog()->error('Authentication code incorrect, redirecting to login page');

            if ($isJson) {
                return $this->jsonResponse(
                    $response,
                    ['status' => 'error', 'message' => __('Authentication code incorrect')],
                    401
                );
            }

            $this->getFlash()->addMessage('login_message', __('Authentication code incorrect'));
            return $response->withRedirect($this->urlFor($request, 'login'));
        }
    }

    /**
     * @param \Xibo\Entity\User $user
     * @param Request $request
     */
    private function completeLoginFlow(User $user, Request $request): void
    {
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

    /**
     * Get a redirect link from the given request and prior route
     *  validate the prior route by only taking its path
     * @param \Slim\Http\ServerRequest $request
     * @param string|null $priorRoute
     * @return string
     */
    private function getRedirect(Request $request, ?string $priorRoute): string
    {
        $home = $this->urlFor($request, 'home');

        // Parse the prior route
        $parsedPriorRoute = parse_url($priorRoute);
        if (!$parsedPriorRoute) {
            $priorRoute = $home;
        } else {
            $priorRoute = $parsedPriorRoute['path'];
        }

        // Certain routes always lead home
        if ($priorRoute == ''
            || $priorRoute == '/'
            || str_contains($priorRoute, $this->urlFor($request, 'login'))
        ) {
            $redirectTo = $home;
        } else {
            $redirectTo = $priorRoute;
        }

        return $redirectTo;
    }

    /**
     * Detect whether the current request expects a JSON response.
     */
    private function isJsonRequest(Request $request): bool
    {
        return str_contains($request->getHeaderLine('Accept'), 'application/json');
    }

    /**
     * Sanitize a priorRoute value before surfacing it to React via JSON.
     * Strips host, scheme, and /login prefixes to prevent open redirects.
     */
    private function sanitizePriorRouteForOutput(?string $raw): string
    {
        if (empty($raw)) {
            return '';
        }
        $parsed = parse_url($raw);
        if ($parsed === false || !empty($parsed['host'])) {
            return '';
        }
        $path = $parsed['path'] ?? '';
        if ($path === '' || $path === '/' || str_starts_with($path, '/login')) {
            return '';
        }
        $safe = $path;
        if (!empty($parsed['query'])) {
            $safe .= '?' . $parsed['query'];
        }
        if (!empty($parsed['fragment'])) {
            $safe .= '#' . $parsed['fragment'];
        }
        return $safe;
    }

    private function getBrandLogoUrl(): string
    {
        $brandDir = rtrim($this->getConfig()->getSetting('LIBRARY_LOCATION'), '/') . '/brand';
        return $this->getConfig()->rootUri() . 'brand/'
            . (file_exists($brandDir . '/logo.svg') ? 'logo.svg' : 'logo.png');
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

    /**
     * Write a JSON payload to the PSR-7 response body.
     * Consistent with the pattern used in aboutConfig().
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $this->setNoOutput(true);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
