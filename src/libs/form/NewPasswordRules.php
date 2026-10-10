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
use actra\yuf\form\rule\EqualsFieldRule;
use actra\yuf\html\HtmlText;

/**
 * The rules of a new password and its confirmation: minimum length, both equal (checked by the form's `validate()`).
 *
 * @internal
 */
final readonly class NewPasswordRules
{
    public const int MIN_LENGTH = 8;

    public static function apply(
        PasswordField $newPasswordField,
        PasswordField $newPasswordConfirmField,
        CommonMessages $messages,
    ): void {
        $newPasswordField->setMinLength(
            minLength: NewPasswordRules::MIN_LENGTH,
            errorMessage: HtmlText::fromText(
                text: MessageTemplate::fill(
                    template: $messages->newPasswordTooShort,
                    values: ['minLength' => (string) NewPasswordRules::MIN_LENGTH],
                ),
            ),
        );
        $newPasswordConfirmField->addRule(
            formRule: new EqualsFieldRule(
                otherField: $newPasswordField,
                errorMessage: HtmlText::fromText(text: $messages->newPasswordsDoNotMatch),
            ),
        );
    }
}
