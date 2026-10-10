<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\form;

use actra\backend\i18n\CommonMessages;
use actra\backend\libs\form\NewPasswordRules;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\FormInput;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

final class NewPasswordRulesTest extends TestCase
{
    /**
     * @return array{PasswordField, PasswordField} The new password field and the confirmation field
     */
    private function validate(CommonMessages $messages, string $newPassword, string $confirmation): array
    {
        $newPasswordField = $this->createField(name: 'new');
        $confirmField = $this->createField(name: 'confirm');
        NewPasswordRules::apply(
            newPasswordField: $newPasswordField,
            newPasswordConfirmField: $confirmField,
            messages: $messages,
        );
        $input = FormInput::fromArray(data: ['new' => $newPassword, 'confirm' => $confirmation]);
        $newPasswordField->validate(input: $input);
        $confirmField->validate(input: $input);

        return [$newPasswordField, $confirmField];
    }

    private function createField(string $name): PasswordField
    {
        return new PasswordField(
            name: $name,
            label: HtmlText::fromText(text: $name),
            requiredError: HtmlText::fromText(text: 'Required'),
            purpose: PasswordPurposeEnum::NEW,
        );
    }

    public function testEqualPasswordsWithMinimumLengthAreValid(): void
    {
        [$newPasswordField, $confirmField] = $this->validate(messages: new CommonMessages(), newPassword: '12345678', confirmation: '12345678');

        $this->assertFalse($newPasswordField->hasErrors(withChildElements: false));
        $this->assertFalse($confirmField->hasErrors(withChildElements: false));
    }

    public function testTooShortPasswordAddsTheLengthErrorToTheNewPasswordField(): void
    {
        [$newPasswordField, $confirmField] = $this->validate(messages: CommonMessages::german(), newPassword: '1234567', confirmation: '1234567');

        $this->assertSame(
            'Das neue Passwort muss mindestens 8 Zeichen lang sein.',
            $newPasswordField->errorCollection->getFirstError()->render(),
        );
        $this->assertFalse($confirmField->hasErrors(withChildElements: false));
    }

    public function testDifferentPasswordsAddTheErrorToTheConfirmationField(): void
    {
        [$newPasswordField, $confirmField] = $this->validate(messages: new CommonMessages(), newPassword: '12345678', confirmation: '12345679');

        $this->assertSame(
            'The new passwords do not match.',
            $confirmField->errorCollection->getFirstError()->render(),
        );
        $this->assertFalse($newPasswordField->hasErrors(withChildElements: false));
    }
}
