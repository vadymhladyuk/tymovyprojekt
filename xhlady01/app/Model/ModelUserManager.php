<?php
/**
 * Created by PhpStorm.
 * User: vadymhladyuk
 * Date: 2019-11-29
 * Time: 19:33
 */

namespace App\Model;

use Nette;

use Nette\Security\Passwords;
use Tracy\Debugger;


class User
{

    public $id = 0;
    public $login = "";
    public $password = "";
    public $role = "";
    public $active = true;
    public $name = "";
    public $surname = "";
    public $email = "";


    /** @var Nette\Database\Context */
    public $dbContext = null;


    public function __construct(Nette\Database\Table\ActiveRow $activeRow = null)
    {
        if ($activeRow) {
            $this->parseFromDb($activeRow);
        }


    }

    public function parseFromDb(Nette\Database\Table\ActiveRow $activeRow)
    {
        $this->id = $activeRow['id'];
        $this->login = $activeRow['login'];
        $this->password = $activeRow['password'];
        $this->active = $activeRow['active'];
        $this->role = $activeRow['role'];
        $this->name = $activeRow['name'];
        $this->surname = $activeRow['surname'];
        $this->email = $activeRow['email'];
        $this->dbContext = $activeRow;
    }

    public function toArray()
    {
        return [
            'login' => $this->login,
            'password' => $this->password,
            'id' => $this->id,
            'role' => $this->role,
            'name' => $this->name,
            'email' => $this->email,
            'active' => $this->active,
            'surname' => $this->surname,
        ];
    }
}

class ModelUserManager
{
    private $database;

    /** @var Nette\Security\User */
    private $user;

    /** @var Passwords */
    private $passwords;

    private const
        TABLE_NAME = 'User';

    public function __construct(Nette\Database\Context $database, Nette\Security\User $user, Passwords $passwords)
    {
        $this->database = $database;
        $this->user = $user;
        $this->passwords = $passwords;
    }


    public function get($id): User
    {
        return new User($this->database->table(self::TABLE_NAME)->get($id));

    }

    public function update(User $user)
    {
        $this->database->table(self::TABLE_NAME)
            ->where('id', $user->id)
            ->update($user->toArray());
    }

    public function updateNameSurnameEmail(User $user)
    {

        $this->database->table(self::TABLE_NAME)->where('id', $user->id)->update($user->toArray());
    }

    public function updatePassword(User $user, string $oldPassword, string $newPassword)
    {

        if ($this->passwords->verify($oldPassword, $user->password)) {
            $user->password = $this->passwords->hash($newPassword);
            $this->update($user);
            return true;
        } else {
            return false;
        }

    }

    public function canShowUsers(): bool
    {
        if ($this->user->isLoggedIn() and $this->user->isAllowed('show_users')) {
            return true;
        } else {
            return false;
        }
    }

    public function canShowUserDetail(User $user): bool
    {
        if ($this->user->isLoggedIn()) {
            return ($this->user->isAllowed('show_users') or $this->user->getId() === $user->id);
        }
        return false;
    }

    public function canEdit(User $user): bool
    {
        if ($this->user->isLoggedIn()) {
            return ($this->user->isAllowed('edit_users') or $this->user->getId() === $user->id);
        }

    }


    public function canEditAdmin(): bool
    {
        if ($this->user->isLoggedIn()) {
            return ($this->user->isAllowed('edit_users'));
        }
    }

    public function getAll($filters = []): Nette\Database\Table\Selection
    {
        $result = $this->database->table(self::TABLE_NAME);

        Debugger::barDump($filters);

        foreach ($filters as $key => $value) {
            if ($value === null) continue;

            switch ($key) {
                case 'name':
                    $result->where('name LIKE ?', '%' . $value . '%');
                    break;
                case 'surname':
                    $result->where('surname LIKE ?', '%' . $value . '%');
                    break;
                case 'login':
                    $result->where('login LIKE ?', '%' . $value . '%');
                    break;
                case 'active':
                    $result->where('active', $value);
                    break;
                case 'role':
                    $result->where('role', $value);
                    break;
                case 'email':
                    $result->where('email LIKE ?', '%' . $value . '%');
                    break;

                case 'order':
                    $result->order($value);
                    break;
            }
        }
        return $result;
    }


}