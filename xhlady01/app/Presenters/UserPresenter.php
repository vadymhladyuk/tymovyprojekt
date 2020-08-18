<?php
/**
 * Created by PhpStorm.
 * User: vadymhladyuk
 * Date: 2019-11-29
 * Time: 10:56
 */

namespace App\Presenters;

use App\Forms\FormFactory;

use App\Forms\SearchBoxFormFactory;
use App\Model\User;
use Nette;
use App\Model\ModelUserManager;
use Nette\ComponentModel\IComponent;
use Tracy\Debugger;
use Nette\Forms\Form;

final class UserPresenter extends BasePresenter
{
    private $database;

    private const PASSWORD_MIN_LENGTH = 7;

    /** @var FormFactory */
    private $factory;

    /** @var ModelUserManager */
    private $modelUserManager;

    /** @var User */
    private $user;

    /** @var SearchBoxFormFactory */
    private $searchBoxFormFactory;

    private $filters = [];

    public function __construct(Nette\Database\Context $database, FormFactory $factory, ModelUserManager $modelUserManager, SearchBoxFormFactory $searchBoxFormFactory)
    {
        parent::__construct();
        $this->factory = $factory;
        $this->database = $database;
        $this->modelUserManager = $modelUserManager;
        $this->searchBoxFormFactory = $searchBoxFormFactory;

        $this->user = new User();
    }

    protected function createComponentComboBox(): Form
    {

        $id = $this->getParameter('id');

        $user = $this->modelUserManager->get($id);
        $form = $this->factory->create();

        if (!$this->modelUserManager->canEditAdmin($user)) {
            return $form;
        }


        $roles = array(
            'guest' => 'Zákazník',
            'worker' => 'Pracovník',
            'manager' => 'Manažer',
            'executive' => 'Vedoucí',
            'admin' => 'Administrátor',
        );

        $form->addSelect('role', 'Role:', $roles)
            ->setPrompt('Zvolte roli')
            ->setDefaultValue($user->role);

        $states = array(
            1 => 'Aktivní',
            0 => 'Neaktivní',
        );

        $form->addSelect('state', 'Stav:', $states)
            ->setPrompt('Zvolte stav')
            ->setDefaultValue($user->active);

        $form->addSubmit('sent', 'Uložit');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $id = $this->getParameter('id');
            $user = ($this->modelUserManager->get($id));

            $user->role = $values->role;
            $user->active = $values->state;
            $this->modelUserManager->update($user);

            $this->flashMessage("Úspěch.", 'success');
            $this->redirect('User:detail', $user->id);
        };
        return $form;
    }

    protected function createComponentEditForm(): Form
    {
        $id = $this->getParameter('id');
        $user = $this->modelUserManager->get($id);
        $form = $this->factory->create();


        if (!$this->modelUserManager->canEdit($user)) {
            $this->flashMessage("Nemáte přístupová práva.", "danger");
            $this->redirect("Homepage:default");
            return $form;
        }


        $form->addText('name', "Jméno:")
            ->setDefaultValue($user->name)
            ->setRequired('Pole s názvem je povinné.');

        $form->addText('surname', "Přijmení:")
            ->setDefaultValue($user->surname)
            ->setRequired('Pole s názvem je povinné.');

        $form->addEmail('email', "Email:")
            ->setDefaultValue($user->email)
            ->setRequired('Pole s názvem je povinné.');


        $form->addSubmit('sent', 'Uložit');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {

            $id = $this->getParameter('id');
            $user = ($this->modelUserManager->get($id));

            $user->name = $values->name;

            $user->surname = $values->surname;
            $user->email = $values->email;


            $user->active = (int)$user->active;


            $this->modelUserManager->updateNameSurnameEmail($user);
            $this->flashMessage("Úspěch.", 'success');
            $this->redirect('User:detail', $user->id);

        };
        return $form;
    }

    protected function createComponentChangePassword(): Form
    {
        $id = $this->getParameter('id');
        $user = $this->modelUserManager->get($id);
        $form = $this->factory->create();


        if (!$this->modelUserManager->canEdit($user)) {
            $this->flashMessage("Nemáte přístupová práva.", "danger");
            $this->redirect("Homepage:default");
            return $form;
        }

        $form->addPassword('oldPassword', "Původní heslo:")
            ->setOption('description', sprintf('at least %d characters', self::PASSWORD_MIN_LENGTH))
            ->setRequired('Please create a password.')
            ->addRule($form::MIN_LENGTH, null, self::PASSWORD_MIN_LENGTH);


        $form->addPassword('newPassword', "Nové heslo:")
            ->setOption('description', sprintf('at least %d characters', self::PASSWORD_MIN_LENGTH))
            ->setRequired('Please create a password.')
            ->addRule($form::MIN_LENGTH, null, self::PASSWORD_MIN_LENGTH);


        $form->addPassword('newPasswordAgain', "Znovu nové heslo:")
            ->setOption('description', sprintf('at least %d characters', self::PASSWORD_MIN_LENGTH))
            ->setRequired('Please create a password.')
            ->addRule($form::MIN_LENGTH, null, self::PASSWORD_MIN_LENGTH);


        $form->addSubmit('sent', 'Uložit');


        $form->onSuccess[] = function (Form $form, \stdClass $values): void {

            $id = $this->getParameter('id');
            $user = ($this->modelUserManager->get($id));

            $user->active = (int)$user->active;

            if ($values->oldPassword === $values->newPassword) {
                $this->flashMessage("Zadejte prosím jiné nové heslo.", 'danger');
                return;
            }


            if ($values->newPasswordAgain !== $values->newPassword) {
                $this->flashMessage("Zadejte stejná nová hesla.", 'danger');
                return;
            }

            if ($this->modelUserManager->updatePassword($user, $values->oldPassword, $values->newPassword)) {
                $this->flashMessage("Úspěch.", 'success');
                $this->redirect('User:detail', $user->id);
                return;
            } else {
                $this->flashMessage("Špatné původní heslo.", 'danger');
                return;
            }

        };
        return $form;
    }

    public function renderDefault(): void
    {
        if (!$this->modelUserManager->canShowUsers()) {
            $this->flashMessage("Nemáte příštupová práva.", "danger");
            $this->redirect("Homepage:default");
        }

        $roles = $this->database->table('Role')->fetchPairs('name', 'longname');

        $res = $this->modelUserManager->getAll($this->filters);
        $users = [];
        foreach ($res as $key => $value) {
            $users[$key] = $value->toArray();

            $users[$key]['roleName'] = $roles[$value['role']];
            $users[$key]['active'] = $value['active'] ? "Aktivní" : "Neaktivní";
        }

        $this['grid']->setData($users);
    }

    public function renderDetail($id): void
    {
        $user = $this->modelUserManager->get($id);

        if (!$user) {
            $this->flashMessage("Uživatel nenalezen.");
            $this->redirect("Homepage:default");
        }

        if (!$this->modelUserManager->canShowUserDetail($user)) {
            $this->flashMessage("Nemáte přístupová práva.", "danger");
            $this->redirect("Homepage:default");
        }

        if (!$this->modelUserManager->canEdit($user)) {
            $this->flashMessage("Nemáte přístupová práva.", "danger");
            $this->redirect("Homepage:default");
        }

        $this->template->userRecord = $user;
        $this->template->userRole = $user->dbContext->ref('Role', 'role');
        $this->template->userRole = $this->template->userRole['longname'];
    }


    public function renderEdit($id): void
    {
        $user = $this->modelUserManager->get($id);

        if (!$user) {
            $this->flashMessage("Uživatel nenalezen.");
            $this->redirect("Homepage:default");
        }

        if (!$this->modelUserManager->canShowUserDetail($user)) {
            $this->flashMessage("Nemáte přístupová práva.", "danger");
            $this->redirect("Homepage:default");
        }

        if (!$this->modelUserManager->canEdit($user)) {
            $this->flashMessage("Nemáte přístupová práva.", "danger");
            $this->redirect("Homepage:default");
        }

    }

    public function renderChangePassword($id): void
    {
        $user = $this->modelUserManager->get($id);

        if (!$user) {
            $this->flashMessage("Uživatel nenalezen.");
            $this->redirect("Homepage:default");
        }

        if (!$this->modelUserManager->canShowUserDetail($user)) {
            $this->flashMessage("Nemáte přístupová práva.", "danger");
            $this->redirect("Homepage:default");
        }

        if (!$this->modelUserManager->canEdit($user)) {
            $this->flashMessage("Nemáte přístupová práva.", "danger");
            $this->redirect("Homepage:default");
        }

    }

    protected function createComponentSearchBox(): Form
    {
        $orderby = [
            'name' => "Jméno",
            'surname' => "Příjmení",
            'login' => "Login",
        ];

        $filterArray = [
            ['type' => 'text', 'name' => 'name', 'text' => "Jméno:"],
            ['type' => 'text', 'name' => 'surname', 'text' => "Příjmení:"],
            ['type' => 'text', 'name' => 'login', 'text' => "Login:"],
            ['type' => 'select', 'name' => 'active', 'text' => "Zobrazit:", 'items' => [1 => "Aktivní", 0 => "Neaktivní"]],
            ['type' => 'text', 'name' => 'email', 'text' => "Email:"],
            ['type' => 'select', 'name' => 'order', 'text' => "Seřadit:", 'items' => $orderby]
        ];

        return $this->searchBoxFormFactory->create(
            $filterArray,
            function ($resultArray) { $this->filters = $resultArray; }
        );
    }

    protected function createComponentGrid()
    {
        $grid = new \Grid();

        $grid->addHref('User:detail' ,'id');

        $grid->addColumn('login', "Login", 2);
        $grid->addColumn('name', "Jméno", 2);
        $grid->addColumn('surname', "Příjmení", 2);
        $grid->addColumn('email', "E-mail", 3);
        $grid->addColumn('roleName', "Role", 2);
        $grid->addColumn('active', "Aktivní", 1);

        $grid->redrawControl();
        return $grid;
    }

}