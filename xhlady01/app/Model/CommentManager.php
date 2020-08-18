<?php

namespace App\Model;

use Nette;

class Comment
{
    public $id = 0;
    public $authorId = 0;
    public $ticketId = 0;
    public $content = "";
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
        $this->authorId = $activeRow['authorId'];
        $this->ticketId = $activeRow['ticketId'];
        $this->content = $activeRow['content'];
        $this->createDate = $activeRow['createDate'];

        $this->dbContext = $activeRow;
    }

    public function toArray()
    {
        return [
            'authorId' => $this->authorId,
            'ticketId' => $this->ticketId,
            'content' => $this->content,
            'createDate' => $this->createDate,
        ];
    }
}

class CommentManager
{
    /** @var Nette\Database\Context */
    private $database;

    /** @var Nette\Security\User */
    private $user;

    private const
        TABLE_NAME = 'Comment';

    public function __construct(Nette\Database\Context $database, Nette\Security\User $user)
    {
        $this->database = $database;
        $this->user = $user;
    }

    public function get($id): Comment
    {
        return new Comment($this->database->table(self::TABLE_NAME)->get($id));
    }

    public function getAll() : Nette\Database\Table\Selection
    {
        return $this->database->table(self::TABLE_NAME);
    }

    public function add(Comment $comment): ? Nette\Database\Table\ActiveRow
    {
        if (!$this->canCreate()) return null;

        $comment->createDate = date('Y-m-d H:i:s');
        $comment->authorId = $this->user->getId();

        return $this->database->table(self::TABLE_NAME)->insert($comment->toArray());
    }

    public function canCreate()
    {
        return ($this->user->isLoggedIn() and $this->user->isAllowed('create_comment'));
    }
}