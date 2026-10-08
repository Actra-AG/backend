<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

use actra\yuf\form\FormMessages;

/**
 * All texts of the backend in one language, per backend route (`ActraBackendSettings::$messages` for the main route,
 * `BackendRoute::$messages`). Use `english()`, `german()` or `forLanguageCode()`, or change single texts of them:
 * `BackendMessages::german()->with(auth: AuthMessages::german()->with(loginPageTitle: 'Login'))`.
 */
final readonly class BackendMessages
{
    public function __construct(
        public FormMessages $form = new FormMessages(),
        public CommonMessages $common = new CommonMessages(),
        public LayoutMessages $layout = new LayoutMessages(),
        public AuthMessages $auth = new AuthMessages(),
        public UserMessages $user = new UserMessages(),
        public ProfileMessages $profile = new ProfileMessages(),
        public NotificationMessages $notification = new NotificationMessages(),
        public LogMessages $log = new LogMessages(),
        public EmailMessages $email = new EmailMessages(),
    ) {}

    public static function english(): BackendMessages
    {
        return new BackendMessages();
    }

    /**
     * The included texts of a language: German for `de`, English for every other language.
     */
    public static function forLanguageCode(string $languageCode): BackendMessages
    {
        return match ($languageCode) {
            'de' => BackendMessages::german(),
            default => BackendMessages::english(),
        };
    }

    /**
     * Returns a copy with the given parts replaced.
     */
    public function with(
        ?FormMessages $form = null,
        ?CommonMessages $common = null,
        ?LayoutMessages $layout = null,
        ?AuthMessages $auth = null,
        ?UserMessages $user = null,
        ?ProfileMessages $profile = null,
        ?NotificationMessages $notification = null,
        ?LogMessages $log = null,
        ?EmailMessages $email = null,
    ): BackendMessages {
        return new BackendMessages(
            form: $form ?? $this->form,
            common: $common ?? $this->common,
            layout: $layout ?? $this->layout,
            auth: $auth ?? $this->auth,
            user: $user ?? $this->user,
            profile: $profile ?? $this->profile,
            notification: $notification ?? $this->notification,
            log: $log ?? $this->log,
            email: $email ?? $this->email,
        );
    }

    public static function german(): BackendMessages
    {
        return new BackendMessages(
            form: FormMessages::german(),
            common: CommonMessages::german(),
            layout: LayoutMessages::german(),
            auth: AuthMessages::german(),
            user: UserMessages::german(),
            profile: ProfileMessages::german(),
            notification: NotificationMessages::german(),
            log: LogMessages::german(),
            email: EmailMessages::german(),
        );
    }
}
