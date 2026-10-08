<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

/**
 * The links to the pages of the backend on one backend route (`/backend/user-5.html`).
 */
final readonly class BackendPaths
{
    public const string LOGIN_FILE_NAME = 'login.html';

    /**
     * @param string $path The path of the backend route, e.g. `/backend/` or `/en/backend/`
     */
    public function __construct(public string $path) {}

    public function login(): string
    {
        return $this->path . BackendPaths::LOGIN_FILE_NAME;
    }

    public function loginPassword(): string
    {
        return $this->path . 'loginPassword.html';
    }

    public function loginPasswordToken(): string
    {
        return $this->path . 'loginPasswordToken.html';
    }

    public function loginToken(): string
    {
        return $this->path . 'loginToken.html';
    }

    public function logout(): string
    {
        return $this->path . 'logout.html';
    }

    public function notification(int $ID): string
    {
        return $this->path . 'notification-' . $ID . '.html';
    }

    public function notificationSend(): string
    {
        return $this->path . 'notificationSend.html';
    }

    public function notifications(): string
    {
        return $this->path . 'notifications.html';
    }

    public function passwordForgotten(): string
    {
        return $this->path . 'passwordForgotten.html';
    }

    public function passwordForgottenRes(): string
    {
        return $this->path . 'passwordForgottenRes.html';
    }

    public function passwordReset(string $token): string
    {
        return $this->path . 'passwordReset-' . $token . '.html';
    }

    public function passwordResetRes(): string
    {
        return $this->path . 'passwordResetRes.html';
    }

    public function profile(): string
    {
        return $this->path . 'profile.html';
    }

    public function profileChangePassword(): string
    {
        return $this->path . 'profileChangePassword.html';
    }

    public function profileCreatePassword(): string
    {
        return $this->path . 'profileCreatePassword.html';
    }

    public function profileGenerateApiKey(): string
    {
        return $this->path . 'profileGenerateApiKey.html';
    }

    public function profileRemoveApiKey(): string
    {
        return $this->path . 'profileRemoveApiKey.html';
    }

    public function profileRemovePassword(): string
    {
        return $this->path . 'profileRemovePassword.html';
    }

    public function tokens(?int $userID): string
    {
        return $this->path . ($userID === null ? 'tokens.html' : 'tokens-' . $userID . '.html');
    }

    public function user(int|string $ID): string
    {
        return $this->path . 'user-' . $ID . '.html';
    }

    public function userAdd(): string
    {
        return $this->path . 'userAdd.html';
    }

    public function userDelete(int $ID): string
    {
        return $this->path . 'userDelete-' . $ID . '.html';
    }

    public function userGenerateApiKey(int $ID): string
    {
        return $this->path . 'userGenerateApiKey-' . $ID . '.html';
    }

    public function userInvite(int $ID): string
    {
        return $this->path . 'userInvite-' . $ID . '.html';
    }

    public function userMod(int $ID): string
    {
        return $this->path . 'userMod-' . $ID . '.html';
    }

    public function userRemoveApiKey(int $ID): string
    {
        return $this->path . 'userRemoveApiKey-' . $ID . '.html';
    }

    public function users(): string
    {
        return $this->path . 'users.html';
    }

    public function visits(?int $userID): string
    {
        return $this->path . ($userID === null ? 'visits.html' : 'visits-' . $userID . '.html');
    }
}
