<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\FormFactory;
use App\Forms\SearchBoxFormFactory;
use App\Model\TicketManager;
use Nette;
use Nette\Application\UI\Form;
use Tracy\Debugger;


final class HomepagePresenter extends BasePresenter
{
    private $database;

    /** @var FormFactory */
    private $factory;

    /** @var SearchBoxFormFactory */
    private $searchBoxFormFactory;

    /** @var TicketManager */
    private $ticketManager;

    private $filters = [];

    public function __construct(Nette\Database\Context $database, FormFactory $factory, SearchBoxFormFactory $searchBoxFormFactory, TicketManager $ticketManager)
    {
        parent::__construct();
        $this->factory = $factory;
        $this->database = $database;
        $this->searchBoxFormFactory = $searchBoxFormFactory;
        $this->ticketManager = $ticketManager;
    }

    protected function createComponentSearchBox(): Form
    {
        $states = $this->database->table('State')->fetchPairs('id', 'name');
        $products = $this->database->table('Product')->fetchPairs('id', 'name');

        $orderby = [
            'createDate' => "Datum vytvoření",
            'name' => "Název",
        ];

        $filterArray = [
            ['type' => 'text' ,'name' => 'name', 'text' => "Vyhledat tiket."],
            ['type' => 'select', 'name' => 'stateId', 'text' => "Stav:", 'items' => $states],
            ['type' => 'select', 'name' => 'productId', 'text' => "Produkt:", 'items' => $products],
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

        $grid->addHref('Ticket:default' ,'id');

        $grid->addColumn('name', "Název", 5);
        $grid->addColumn('author', "Autor", 3);
        $grid->addColumn('state', "Stav", 2, 'state');
        $grid->addColumn('createDate', "Datum vytvoření", 2, 'datetime');

        $grid->redrawControl();
        return $grid;
    }

    public function renderDefault(): void
	{
        $authors = $this->database->table('User')->fetchPairs('id');
        $states = $this->database->table('State')->fetchPairs('id', 'name');

        $res = $this->ticketManager->getAll($this->filters);
        $tickets = [];
        foreach ($res as $key => $value) {
            $tickets[$key] = $value->toArray();

            $tickets[$key]['author'] = $authors[$value['authorId']]->name . ' ' . $authors[$value['authorId']]->surname;
            $tickets[$key]['state'] = $states[$value['stateId']];
        }

        $this['grid']->setData($tickets);
	}
}
