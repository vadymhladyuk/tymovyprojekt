<?php

namespace App\Forms;

use App\Model\Comment;
use App\Model\CommentManager;
use Nette;
use Nette\Application\UI\Form;
use Tracy\Debugger;

class CommentFormFactory
{
    /** @var FormFactory */
    private $factory;

    /** @var CommentManager */
    private $commentManager;

    public function __construct(FormFactory $factory, CommentManager $commentManager)
    {
        $this->factory = $factory;
        $this->commentManager = $commentManager;
    }

    public function create(callable $onSuccess, int $ticketId): Form
    {
        Debugger::barDump($ticketId);

        $form = $this->factory->create();

        $form->addTextArea('content', "Nový komentář (markdown):")
            ->setRequired("Pole s textem je povinné");

        $form->addSubmit('send', 'Přidej');

        $form->onSuccess[] = function (Form $form, \stdClass $values) use ($onSuccess, $ticketId): void {
            $comment = new Comment();

            $comment->content = $values->content;
            $comment->ticketId = $ticketId;

            $this->commentManager->add($comment);

            $form->reset();

            $onSuccess;
        };

        return $form;
    }
}