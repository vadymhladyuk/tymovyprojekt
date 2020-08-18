<?php

namespace App\Presenters;

use App\Forms\CommentFormFactory;
use App\Forms\FormFactory;
use App\Forms\SearchBoxFormFactory;
use App\Forms\TicketFormFactory;
use App\Model\CommentManager;
use App\Model\TaskManager;
use App\Model\Ticket;
use Nette;
use Nette\Application\UI\Form;
use App\Model\TicketManager;
use Tracy\Debugger;

class TicketPresenter extends BasePresenter
{
    private $database;

    /** @var FormFactory */
    private $factory;

    /** @var Ticket */
    private $ticket;

    /** @var TicketManager */
    private $ticketManager;

    /** @var TaskManager */
    private $taskManager;

    /** @var CommentManager */
    private $commentManager;

    /** @var CommentFormFactory */
    private $commentFormFactory;

    /** @var SearchBoxFormFactory */
    private $searchBoxFormFactory;

    /** @var TicketFormFactory */
    private $ticketFormFactory;

    private $filters = [];

    public function __construct(
        Nette\Database\Context $database,
        FormFactory $factory,
        TicketManager $ticketManager,
        CommentManager $commentManager,
        CommentFormFactory $commentFormFactory,
        SearchBoxFormFactory $searchBoxFormFactory,
        TaskManager $taskManager,
        TicketFormFactory $ticketFormFactory
    )
    {
        parent::__construct();
        $this->database = $database;
        $this->factory = $factory;
        $this->ticketManager = $ticketManager;
        $this->commentManager = $commentManager;
        $this->commentFormFactory = $commentFormFactory;
        $this->searchBoxFormFactory = $searchBoxFormFactory;
        $this->taskManager = $taskManager;
        $this->ticketFormFactory = $ticketFormFactory;

        $this->ticket = new Ticket();
    }

    protected function createComponentEditForm(): Form
    {
        $origId = $this->getParameter('id');

        return $this->ticketFormFactory->create(
            function ($id) { $this->redirect("Ticket:default", $id); },
            function () {
                $this->flashMessage("Editace ticketu se nezdařila.", 'danger');
                $this->redirect('Homepage:default');
            },
            $origId);
    }

    protected function createComponentNewCommentForm(): Form
    {
        return $this->commentFormFactory->create(function (): void {}, $this->getParameter('id'));
    }

    public function renderDefault($id): void
    {
        // ticket detail
        $ticket = $this->ticketManager->get($id);

        if (!$ticket) {
            $this->flashMessage("Ticket nenalezen.");
            $this->redirect("Homepage:default");
        }

        $this->template->ticket = $ticket;

        $this->template->product = $ticket->dbContext->ref('Product','productId');
        $this->template->author = $ticket->dbContext->ref('User', 'authorId');
        $this->template->state = $ticket->dbContext->ref('State', 'stateId');

        $this->template->showEdit = $this->ticketManager->canEdit($ticket);

        $this->template->showCreateTask = $this->taskManager->canCreate($ticket);

        // zobrazení přidružených úkolů
        if ($this->template->showTasks = $this->taskManager->canShow()) {
            // ticket tasks overview TODO search, how to get searchPhrase?
            $this->template->tasks = $this->taskManager->getAll($this->filters);
        }

        // zobrazení komentářů
        $this->template->comments = $this->commentManager->getAll()->where('ticketId', $id);
        $this->template->userNames = $this->database->table('User')->fetchPairs('id', 'login');
        $this->template->states = $this->database->table('State')->fetchPairs('id', 'name');
    }

    public function actionCreate(): void
    {
        $this->permissionRedirect($this->ticketManager->canCreate());
    }

    public function actionCreateWithProduct($productId): void
    {
        $this->permissionRedirect($this->ticketManager->canCreate());

        $this['editForm']->setDefaults(['productId' => $productId]);

        $this->setView('create');
    }

    public function actionEdit($id): void
    {
        $ticket = $this->ticketManager->get($id);
        if (!$ticket) {
            $this->flashMessage("Ticket nenalezen.");
            $this->redirect("Homepage:");
        }

        $this->permissionRedirect($this->ticketManager->canEdit($ticket));

        $this['editForm']->setDefaults($ticket->toArray());
    }

    public function actionDelete(int $id): void
    {
        $ticket = $this->ticketManager->get($id);
        if (!$ticket) {
            $this->flashMessage("Ticket nenalezen.");
            $this->redirect("Homepage:default");
        }

        if (!$this->ticketManager->delete($ticket)) {
            $this->flashMessage("Nepodařilo se smazat ticket.", 'danger');
        } else {
            $this->flashMessage("Ticket byl smazán.");
        }
        $this->redirect("Homepage:default");
    }

    protected function createComponentSearchBox(): Form
    {
        $workers = [];
        $result = $this->database->table('User')->where('role', "worker");
        foreach ($result as $worker) {
            $workers[$worker->id] = $worker->name . ' ' . $worker->surname;
        }
        $states = $this->database->table('State')->fetchPairs('id', 'name');
        $tickets = $this->database->table('Ticket')->fetchPairs('id', 'name');

        $orderby = [
            'name' => "Název",
        ];

        $filterArray = [
            ['type' => 'text' ,'name' => 'name', 'text' => "Vyhledat úkol."],
            ['type' => 'select', 'name' => 'stateId', 'text' => "Stav:", 'items' => $states],
            ['type' => 'select', 'name' => 'managerId', 'text' => "Pracovník:", 'items' => $workers],
            ['type' => 'select', 'name' => 'ticketId', 'text' => "Tiket:", 'items' => $tickets],
            ['type' => 'select', 'name' => 'order', 'text' => "Seřadit:", 'items' => $orderby]
        ];

        return $this->searchBoxFormFactory->create(
            $filterArray,
            function ($resultArray) { $this->filters = $resultArray; }
        );
    }
}