<?php


namespace Xibo\Helper;


use Slim\Slim;
use Xibo\Entity\User;
use Xibo\Service\LogServiceInterface;

trait LogoutTrait
{
    public function completeLogoutFlow(User $user, Session $session, LogServiceInterface $logService, Slim $app)
    {
        $user->touch();

        // to log out a user we need only to clear out some session vars
        unset($_SESSION['userid']);
        unset($_SESSION['username']);
        unset($_SESSION['password']);

        $session->setIsExpired(1);

        $logService->audit('User', $user->userId, 'User logout', [
            'IPAddress' => $app->request()->getIp(),
            'UserAgent' => $app->request()->getUserAgent()
        ]);
    }
}