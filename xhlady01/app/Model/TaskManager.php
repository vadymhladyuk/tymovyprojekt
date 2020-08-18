<?php

namespace App\Model;

use Nette;
use Tracy\Debugger;

class Task
{
    public $id = 0;
    public $name = "";
    public $ticketId = 0;
    public $authorId = 0;
    public $workerId = 0;
    public $stateId = 0;
    public $description = "";
    public $expectedTime = 0;
    public $spentTime = 0;
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
        $this->name = $activeRow['name'];
        $this->ticketId = $activeRow['ticketId'];
        $this->authorId = $activeRow['authorId'];
        $this->workerId = $activeRow['workerId'];
        $this->stateId = $activeRow['stateId'];
        $this->description = $activeRow['description'];
        $this->expectedTime = $activeRow['expectedTime'];
        $this->spentTime = $activeRow['spentTime'];
        $this->createDate = $activeRow['createDate'];

        $this->dbContext = $activeRow;
    }

    public function toArray()
    {
        return [
            'name' => $this->name,
            'ticketId' => $this->ticketId,
            'authorId' => $this->authorId,
            'workerId' => $this->workerId,
            'stateId' => $this->stateId,
            'description' => $this->description,
            'expectedTime' => $this->expectedTime,
            'spentTime' => $this->spentTime,
            'createDate' => $this->createDate
        ];
    }
}

class TaskManager
{
    private $database;

    /** @var Nette\Security\User */
    private $user;

    private const
        TABLE_NAME = 'Task',
        TABLE_STATE_NAME = 'State';

    public function __construct(Nette\Database\Context $database, Nette\Security\User $user)
    {
        $this->database = $database;
        $this->user = $user;
    }

    public function add(Task $task): Nette\Database\Table\ActiveRow
    {
        // najdi id pro stav "nový"
        $newStateId = $this->database->table(self::TABLE_STATE_NAME)->where('name', 'new')
            ->fetch()
            ->id;
        $task->stateId = $newStateId;
        $task->createDate = date('Y-m-d H:i:s');
        $task->spentTime = 0;

        return $this->database->table(self::TABLE_NAME)->insert($task->toArray());
    }

    public function update(Task $task)
    {
        return $this->database->table(self::TABLE_NAME)
            ->where('id', $task->id)
            ->update($task->toArray());
    }

    public function get($id): Task
    {
        return new Task($this->database->table(self::TABLE_NAME)->get($id));
    }

    public function canEdit(Task $task): bool
    {
        if ($task->authorId === $this->user->getId()) {
            return ($this->user->isLoggedIn() and $this->user->isAllowed('edit_related_task'));
        } else {
            return ($this->user->isLoggedIn() and $this->user->isAllowed('edit_any_task'));
        }
    }

    public function canShow(): bool
    {
        return ($this->user->isLoggedIn() and $this->user->isAllowed('show_tasks'));
    }

    public function canCreate(Ticket $ticket): bool
    {
        if($ticket->dbContext->ref('Product', 'productId')['managerId'] === $this->user->getId()) {
            return ($this->user->isLoggedIn() and $this->user->isAllowed('create_related_task'));
        } else {
            return ($this->user->isLoggedIn() and $this->user->isAllowed('create_any_task'));
        }
    }

    public function canUpdateProgress(Task $task): bool
    {
        return ($this->user->isLoggedIn() and ($this->user->id === $task->workerId));
    }

    public function delete(Task $task): bool
    {
        if (!$this->canEdit($task)) return false;

        $this->database->table(self::TABLE_NAME)
            ->where('id', $task->id)
            ->delete();

        return true;
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
                case 'workerId':
                    $result->where('workerId', $value);
                    break;
                case 'ticketId':
                    $result->where('ticketId', $value);
                    break;


                case 'order':
                    $result->order($value);
                    break;
            }
        }
        return $result;
    }
}