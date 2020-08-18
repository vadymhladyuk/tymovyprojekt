<?php
/**
 * Created by PhpStorm.
 * User: jan
 * Date: 2019-12-01
 * Time: 16:40
 */

namespace App\Forms;

use App\Model\Ticket;
use App\Model\TicketManager;
use Nette;
use Nette\Application\UI\Form;


class TicketFormFactory
{
    /** @var FormFactory */
    private $factory;

    /** @var Nette\Database\Context */
    private $database;

    /** @var TicketManager */
    private $ticketManager;

    public function __construct(FormFactory $factory, Nette\Database\Context $database, TicketManager $ticketManager)
    {
        $this->factory = $factory;
        $this->database = $database;
        $this->ticketManager = $ticketManager;
    }

    public function create(callable $onSuccess, callable $onError, int $origId = null): Form
    {
        // načtení výběru z produktů
        $products = $this->database->table('Product')->fetchPairs('id', 'name');

        $form = $this->factory->create();

        $form->addText('name', "Název ticketu:")
            //->setDefaultValue($this->ticket->name)
            ->setRequired('Pole s názvem je povinné.');

        if ($origId) {
            $ticket = $this->ticketManager->get($origId);
        } else {
            $ticket = new Ticket();
        }
        if ($this->ticketManager->canEditState($ticket)) {
            $stateArr = $this->database->table('State')->fetchPairs('id', 'name');

            $form->addSelect('stateId', "Stav:", $stateArr)
                ->setPrompt("Zvolte stav");
                //->setDefaultValue($this->ticket->stateId === 0 ? null : $this->ticket->stateId);
        }

        $form->addSelect('productId', "Produkt:", $products)
            ->setPrompt('Zvolte produkt')
            //->setDefaultValue($this->ticket->productId === 0 ? null : $this->ticket->productId)
            ->setRequired('Pole s produktem je povinné.');

        $form->addTextArea('description', "Textový popis (markdown):")
            //->setDefaultValue($this->ticket->description)
            ->setHtmlAttribute('style', "height: 300px")
            ->setRequired('Pole s popisem je povinné.');

        $form->addSubmit('send', "Uložit");

        $form->onSuccess[] = function (Form $form, \stdClass $values) use ($onSuccess, $origId, $onError): void
        {
            if (!$origId) {
                $ticket = new Ticket();

                $ticket->name = $values->name;
                $ticket->productId = $values->productId;
                $ticket->description = $values->description;

                $state = $form->getHttpData($form::DATA_TEXT, 'stateId');
                if ($state) $ticket->stateId = $state;

                $result = $this->ticketManager->add($ticket);
                if ($result) {
                    $id = $result->id;
                } else {
                    $onError();
                    return;
                }
            } else {
                $ticket = $this->ticketManager->get($origId);

                $ticket->name = $values->name;
                $ticket->productId = $values->productId;
                $ticket->description = $values->description;

                $state = $form->getHttpData($form::DATA_TEXT, 'stateId');
                if ($state) $ticket->stateId = $state;

                $result = $this->ticketManager->update($ticket);

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
}