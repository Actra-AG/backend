<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\form;

use actra\backend\i18n\CommonMessages;
use actra\backend\libs\form\NewPasswordCheck;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\FormInput;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

final class NewPasswordCheckTest extends TestCase
{
    private function createField(string $name, string $input): PasswordField
    {
        $field = new PasswordField(
            name: $name,
            label: HtmlText::unencoded(textContent: $name),
            requiredError: HtmlText::unencoded(textContent: 'Required'),
            purpose: PasswordPurposeEnum::NEW
        );
        $field->validate(input: FormInput::fromArray(data: [$name => $input]));

        return $field;
    }

    public function testEqualPasswordsWithMinimumLengthAreValid(): void
    {
        $newPasswordField = $this->createField(name: 'new', input: '12345678');
        $confirmField = $this->createField(name: 'confirm', input: '12345678');

        $isValid = new NewPasswordCheck(messages: new CommonMessages())->isValid(
            newPasswordField: $newPasswordField,
            newPasswordConfirmField: $confirmField
        );

        $this->assertTrue($isValid);
        $this->assertFalse($newPasswordField->hasErrors(withChildElements: false));
        $this->assertFalse($confirmField->hasErrors(withChildElements: false));
    }

    public function testTooShortPasswordAddsTheLengthErrorToTheNewPasswordField(): void
    {
        $newPasswordField = $this->createField(name: 'new', input: '1234567');
        $confirmField = $this->createField(name: 'confirm', input: '1234567');

        $isValid = new NewPasswordCheck(messages: CommonMessages::german())->isValid(
            newPasswordField: $newPasswordField,
            newPasswordConfirmField: $confirmField
        );

        $this->assertFalse($isValid);
        $this->assertSame(
            'Das neue Passwort muss mindestens 8 Zeichen lang sein.',
            $newPasswordField->errorCollection->getFirstError()->render()
        );
        $this->assertFalse($confirmField->hasErrors(withChildElements: false));
    }

    public function testDifferentPasswordsAddTheErrorToTheConfirmationField(): void
    {
        $newPasswordField = $this->createField(name: 'new', input: '12345678');
        $confirmField = $this->createField(name: 'confirm', input: '12345679');

        $isValid = new NewPasswordCheck(messages: new CommonMessages())->isValid(
            newPasswordField: $newPasswordField,
            newPasswordConfirmField: $confirmField
        );

        $this->assertFalse($isValid);
        $this->assertSame('The new passwords do not match.', $confirmField->errorCollection->getFirstError()->render());
        $this->assertFalse($newPasswordField->hasErrors(withChildElements: false));
    }
}