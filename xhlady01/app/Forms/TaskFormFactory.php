<?php
/**
 * Created by PhpStorm.
 * User: Dalik
 * Date: 12/1/2019
 * Time: 8:34 PM
 */

namespace App\Forms;


use App\Model\Task;
use App\Model\TaskManager;
use Nette;
use Nette\Application\UI\Form;
use Tracy\Debugger;

class TaskFormFactory
{

    /** @var FormFactory */
    private $factory;

    /** @var Nette\Database\Context */
    private $database;

    /** @var TaskManager */
    private $taskManager;

    /** @var Nette\Security\User */
    private $user;

    public function __construct(FormFactory $factory, Nette\Database\Context $database, TaskManager $taskManager, Nette\Security\User $user)
    {
        $this->factory = $factory;
        $this->database = $database;
        $this->taskManager = $taskManager;
        $this->user = $user;
    }

    public function createEditForm(callable $onSuccess, callable $onError, int $origId = null): Form
    {
        $form = $this->factory->create();

        if ($origId) {
            $task = $this->taskManager->get($origId);
        } else {
            $task = new Task();
        }

        $form->addText('name', "Název úkolu:")
            ->setRequired('Pole s názvem je povinné.');

        $tickets = $this->database->table('Ticket')->fetchPairs('id', 'name');
        $form->addSelect('ticketId', "Tiket:", $tickets)
            ->setPrompt('Zvolte tiket')
            ->setRequired('Pole s tiketem je povinné.');

        $workers = [];
        $result = $this->database->table('User')->where('role', 'worker');
        foreach ($result as $worker) {
            $workers[$worker->id] = $worker->name . ' ' . $worker->surname;
        }
        $form->addSelect('workerId', "Pracovník:", $workers)
            ->setPrompt("Zvolte pracovníka");

        $form->addInteger('expectedTime', "Očekávaný čas")->setRequired("Pole pro očekávaný čas je povinné")->setHtmlAttribute('min', "1");

        $form->addTextArea('description', "Textový popis (markdown):")
            ->setHtmlAttribute('style', "height: 300px")
            ->setRequired('Pole s popisem je povinné.');

        $form->addSubmit('send', "Uložit");

        $form->onSuccess[] = function (Form $form, \stdClass $values) use ($onSuccess, $origId, $onError): void {
            if (!$origId) {
                $task = new Task();

                $task->name = $values->name;
                $task->workerId = $values->workerId;
                $task->description = $values->description;
                $task->expectedTime = $values->expectedTime;
                $task->authorId = $this->user->getId();
                $task->ticketId = $values->ticketId;

                $result = $this->taskManager->add($task);
                if ($result) {
                    $id = $result->id;
                } else {
                    $onError();
                    return;
                }
            } else {
                $task = $this->taskManager->get($origId);

                $task->name = $values->name;
                $task->workerId = $values->workerId;
                $task->description = $values->description;
                $task->expectedTime = $values->expectedTime;
                $task->authorId = $this->user->getId();
                $task->ticketId = $values->ticketId;

                $result = $this->taskManager->update($task);

                $id = $origId;
                if (!$result) {
                    $onError();
                    return;
                }
            }
            $onSuccess($id);
        };

        return $form;
    }

    public function createUpdateProgressForm(callable $onSuccess, callable $onError, int $origId): Form
    {
        $form = $this->factory->create();

        $task = $this->taskManager->get($origId);

        $stateArr = $this->database->table('State')->fetchPairs('id', 'name');

        $form->addSelect('stateId', "Stav:", $stateArr)
            ->setPrompt("Zvolte stav");

        $form->addInteger('spentTime', "Odpracovaný čas")->setRequired("Pole pro odpracovaný čas je povinné")->setHtmlAttribute('min', "0");

        $form->addTextArea('description', "Textový popis (markdown):")
            ->setHtmlAttribute('style', "height: 300px")
            ->setRequired('Pole s popisem je povinné.');

        $form->addSubmit('send', "Uložit");

        $form->onSuccess[] = function (Form $form, \stdClass $values) use ($onSuccess, $origId, $onError): void {

            $task = $this->taskManager->get($origId);

            $task->description = $values->description;
            $state = $form->getHttpData($form::DATA_TEXT, 'stateId');
            if ($state) $task->stateId = $state;
            $spentTime = $form->getHttpData($form::DATA_TEXT, 'spentTime');
            if ($spentTime) $task->spentTime = $spentTime;

            $result = $this->taskManager->update($task);

            $id = $origId;
            if (!$result) {
                $onError();
                return;
            }
            $onSuccess($id);
        };

        return $form;
    }
}