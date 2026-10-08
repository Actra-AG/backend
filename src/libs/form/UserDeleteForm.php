<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\libs\auth\UserController;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\view\backend\php\user;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

final class UserDeleteForm extends Form
{
    public function __construct(private readonly DbAuthUser $dbAuthUser)
    {
        parent::__construct(name: 'UserDeleteForm', messages: ActraBackend::messages()->form);
        $this->addCssClass(className: 'form');
        $this->addComponent(
            formComponent: new FormControl(
                name: 'delete',
                submitLabel: HtmlText::unencoded(textContent: ActraBackend::messages()->user->deleteButton),
                cancelLink: user::getPath(ID: $dbAuthUser->ID),
            ),
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        UserController::deleteUser(userID: $this->dbAuthUser->ID);

        return true;
    }
}
