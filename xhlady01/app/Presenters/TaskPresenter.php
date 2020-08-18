<?php
/**
 * Created by PhpStorm.
 * User: Dalik
 * Date: 11/28/2019
 * Time: 7:06 PM
 */

namespace App\Presenters;

use App\Forms\SearchBoxFormFactory;
use App\Forms\TaskFormFactory;
use App\Model\ProductManager;
use App\Model\TaskManager;
use App\Model\Ticket;
use Nette;
use App\Forms\FormFactory;
use App\Model\TicketManager;
use App\Model\Task;
use Nette\Forms\Form;
use Tracy\Debugger;

class TaskPresenter extends BasePresenter
{
    private $database;

    /** @var FormFactory */
    private $factory;

    /** @var Task */
    private $task;

    /** @var Ticket */
    private $ticket;

    /** @var TaskManager */
    private $taskManager;

    /** @var TicketManager */
    private $ticketManager;

    /** @var ProductManager */
    private $productManager;

    /** @var SearchBoxFormFactory */
    private $searchBoxFormFactory;

    /** @var TaskFormFactory  */
    private $taskFormFactory;

    private $filters = [];

    public function __construct(Nette\Database\Context $database, FormFactory $factory, TaskManager $taskManager,
                                SearchBoxFormFactory $searchBoxFormFactory, TicketManager $ticketManager,
                                ProductManager $productManager, TaskFormFactory $taskFormFactory)
    {
        parent::__construct();
        $this->database = $database;
        $this->factory = $factory;
        $this->taskManager = $taskManager;
        $this->searchBoxFormFactory = $searchBoxFormFactory;
        $this->ticketManager = $ticketManager;
        $this->productManager = $productManager;
        $this->taskFormFactory = $taskFormFactory;

        $this->task = new Task();
    }

    public function renderDefault(): void
    {
        $this->permissionRedirect($this->taskManager->canShow());

        $authors = $this->database->table('User')->fetchPairs('id');
        $states = $this->database->table('State')->fetchPairs('id', 'name');

        $res = $this->taskManager->getAll($this->filters);
        $tasks = [];
        foreach ($res as $key => $value) {
            $tasks[$key] = $value->toArray();

            $tasks[$key]['author'] = $authors[$value['authorId']]->name . ' ' . $authors[$value['authorId']]->surname;
            $tasks[$key]['worker'] = $authors[$value['workerId']]->name . ' ' . $authors[$value['workerId']]->surname;
            $tasks[$key]['state'] = $states[$value['stateId']];
        }

        $this['grid']->setData($tasks);
    }

    public function actionCreate($ticketId): void
    {
        $this->setView("edit");

        $ticket = $this->ticketManager->get($ticketId);
        $this->permissionRedirect($this->taskManager->canCreate($ticket));

        $this['editForm']->setDefaults(['ticketId' => $ticketId]);
    }

    public function actionEdit($id): void
    {
        $task = $this->taskManager->get($id);
        if (!$task) {
            $this->flashMessage("Úkol nenalezen.");
            $this->redirect("Homepage:");
        }

        $this->permissionRedirect($this->taskManager->canEdit($task));

        $this['editForm']->setDefaults($task->toArray());
    }

    public function actionUpdateProgress($id): void
    {
        $task = $this->taskManager->get($id);
        if (!$task) {
            $this->flashMessage("Úkol nenalezen.");
            $this->redirect("Homepage:");
        }

        $this->permissionRedirect($this->taskManager->canUpdateProgress($task));

        $this['updateProgressForm']->setDefaults($task->toArray());
    }

    public function renderDetail($id): void
    {

        $this->permissionRedirect($this->taskManager->canShow());

        $task = $this->taskManager->get($id);

        if (!$task) {
            $this->flashMessage("Task nenalezen.");
            $this->redirect("Homepage:default");
        }

        $this->template->task = $task;
        $this->template->ticket = $task->dbContext->ref('Ticket', 'ticketId');
        $this->template->worker = $task->dbContext->ref('User', 'workerId');
        $this->template->author = $task->dbContext->ref('User', 'authorId');
        $this->template->state = $task->dbContext->ref('State', 'stateId');

        $this->template->showEdit = $this->taskManager->canEdit($task);
        $this->template->showUpdateProgress = $this->taskManager->canUpdateProgress($task);
    }

    protected function createComponentEditForm(): Form
    {
        $origId = $this->getParameter('id');

        return $this->taskFormFactory->createEditForm(
            function ($id) {
                $this->redirect("Task:detail", $id); },
            function () {
                $this->flashMessage("Editace úkolu se nezdařila.", 'danger');
                $this->redirect('Homepage:default');
            },
            $origId);
    }

    protected function createComponentUpdateProgressForm(): Form
    {
        $origId = $this->getParameter('id');

        return $this->taskFormFactory->createUpdateProgressForm(
            function ($id) {
                $this->redirect("Task:detail", $id); },
            function () {
                $this->flashMessage("Editace úkolu se nezdařila.", 'danger');
                $this->redirect('Homepage:default');
            },
            $origId);
    }

    public function actionDelete(int $id): void
    {
        $task = $this->taskManager->get($id);
        if (!$task) {
            $this->flashMessage("Úkol nenalezen.");
            $this->redirect("Homepage:default");
        }

        if (!$this->taskManager->delete($task)) {
            $this->flashMessage("Nepodařilo se smazat úkol.", 'danger');
        } else {
            $this->flashMessage("Úkol byl smazán.");
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
            ['type' => 'select', 'name' => 'workerId', 'text' => "Pracovník:", 'items' => $workers],
            ['type' => 'select', 'name' => 'ticketId', 'text' => "Tiket:", 'items' => $tickets],
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

        $grid->addHref('Task:detail' ,'id');

        $grid->addColumn('name', "Název", 5);
        $grid->addColumn('author', "Autor", 2);
        $grid->addColumn('worker', "Worker", 2);
        $grid->addColumn('state', "Stav", 1, 'state');
        $grid->addColumn('createDate', "Datum vytvoření", 2, 'datetime');

        $grid->redrawControl();
        return $grid;
    }
}