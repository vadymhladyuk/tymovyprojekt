<?php

namespace App\Model;

use Nette;

class Ticket
{
    public $id = 0;
    public $productId = 0;
    public $authorId = 0;
    //public $managerId = 0;
    public $stateId = 0;
    public $name = "";
    public $description = "";
    public $createDate = 0;

    /** @var Nette\Database\Table\ActiveRow */
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
        $this->productId = $activeRow['productId'];
        $this->authorId = $activeRow['authorId'];
        $this->stateId = $activeRow['stateId'];
        $this->name = $activeRow['name'];
        $this->description = $activeRow['description'];
        $this->createDate = $activeRow['createDate'];

        $this->dbContext = $activeRow;
    }

    public function toArray()
    {
        return [
            'productId' => $this->productId,
            'authorId' => $this->authorId,
            'stateId' => $this->stateId,
            'name' => $this->name,
            'description' => $this->description,
            'createDate' => $this->createDate,
        ];
    }
}

class TicketManager
{
    /** @var Nette\Database\Context */
    private $database;

    /** @var Nette\Security\User */
    private $user;

    private const
        TABLE_NAME = 'Ticket',
        TABLE_STATE_NAME = 'State',
        TABLE_TASK_NAME = 'Task',
        TABLE_COMMENT_NAME = 'Comment';

    public function __construct(Nette\Database\Context $database, Nette\Security\User $user)
    {
        $this->database = $database;
        $this->user = $user;
    }

    public function get($id): ? Ticket
    {
        $dbrow = $this->database->table(self::TABLE_NAME)->get($id);
        if($dbrow)
            return new Ticket($dbrow);
        else
            return null;
    }

    public function getAll($filters = []) : Nette\Database\Table\Selection
    {
        $result = $this->database->table(self::TABLE_NAME);

        foreach ($filters as $key => $value)
        {
            if ($value === null) continue;

            switch ($key) {
                case 'name':
                    $result->where('name LIKE ?', '%'.$value.'%');
                    break;
                case 'stateId':
                    $result->where('stateId', $value);
                    break;
                case 'productId':
                    $result->where('productId', $value);
                    break;


                case 'order':
                    $result->order($value);
                    break;
            }
        }

        return $result;
    }

    public function add(Ticket $ticket): ? Nette\Database\Table\ActiveRow
    {
        if (!$this->canCreate()) return null;

        if ($ticket->stateId === 0) {
            // najdi id pro stav "nový"
            $newStateId = $this->database->table(self::TABLE_STATE_NAME)->where('name', 'new')
                ->fetch()
                ->id;
            $ticket->stateId = $newStateId;
        }

        $ticket->createDate = date('Y-m-d H:i:s');
        $ticket->authorId = $this->user->getId();

        return $this->database->table(self::TABLE_NAME)->insert($ticket->toArray());
    }

    public function update(Ticket $ticket)
    {
        if (!$this->canEdit($ticket)) return null;

        return $this->database->table(self::TABLE_NAME)
            ->where('id', $ticket->id)
            ->update($ticket->toArray());
    }

    public function delete(Ticket $ticket): bool
    {
        if (!$this->canEdit($ticket)) return false;

        $this->database->beginTransaction();

        try {
            $this->database->table(self::TABLE_TASK_NAME)
                ->where('ticketId', $ticket->id)
                ->delete();

            $this->database->table(self::TABLE_COMMENT_NAME)
                ->where('ticketId', $ticket->id)
                ->delete();

            $this->database->table(self::TABLE_NAME)
                ->where('id', $ticket->id)
                ->delete();
        } catch (\Exception $exception) {
            $this->database->rollBack();
            return false;
        }

        $this->database->commit();
        return true;
    }

    public function canCreate(): bool
    {
        return ($this->user->isLoggedIn() and $this->user->isAllowed('create_ticket'));
    }

    public function canEdit(Ticket $ticket): bool
    {
        if ($this->user->isLoggedIn()) {
            if ($this->user->isAllowed('edit_any_ticket')) {
                return true;
            } else if ($this->user->isAllowed('edit_own_ticket')) {
                return ($ticket->authorId === $this->user->getId() or $this->isManager($ticket));
            }
        }

        return false;
        /*if ($ticket->authorId === $this->user->getId()) {
            return ($this->user->isLoggedIn() and $this->user->isAllowed('edit_own_ticket'));
        } else {
            return ($this->user->isLoggedIn() and $this->user->isAllowed('edit_any_ticket'));
        }*/
    }

    private function isManager(Ticket $ticket): bool
    {
        if ($ticket->dbContext) {
            $managerId = $ticket->dbContext->ref('Product', 'productId')->managerId;
            return ($managerId === $this->user->getId());
        } else {
            return false;
        }
    }

    public function canEditState(Ticket $ticket): bool
    {
        if ($this->user->isLoggedIn()) {
            if ($this->user->isAllowed('edit_any_ticket_state')) {
                return true;
            } else if ($this->user->isAllowed('edit_own_ticket_state')) {
                return ($this->isManager($ticket) or $ticket->id === 0);
            }
        }

        return false;
    }
}