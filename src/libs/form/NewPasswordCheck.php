<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\i18n\CommonMessages;
use actra\backend\i18n\MessageTemplate;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\html\HtmlText;

/**
 * Checks a new password and its confirmation (minimum length, both equal) and adds the error to the wrong field.
 *
 * @internal
 */
final readonly class NewPasswordCheck
{
    public const int MIN_LENGTH = 8;

    public function __construct(private CommonMessages $messages) {}

    public function isValid(PasswordField $newPasswordField, PasswordField $newPasswordConfirmField): bool
    {
        $newPassword = $newPasswordField->getValueAsString();
        if (mb_strlen(string: $newPassword) < NewPasswordCheck::MIN_LENGTH) {
            $newPasswordField->addError(
                errorMessage: HtmlText::fromText(
                    text: MessageTemplate::fill(
                        template: $this->messages->newPasswordTooShort,
                        values: ['minLength' => (string) NewPasswordCheck::MIN_LENGTH],
                    ),
                ),
            );

            return false;
        }
        if ($newPassword !== $newPasswordConfirmField->getValueAsString()) {
            $newPasswordConfirmField->addError(
                errorMessage: HtmlText::fromText(text: $this->messages->newPasswordsDoNotMatch),
            );

            return false;
        }

        return true;
    }
}
