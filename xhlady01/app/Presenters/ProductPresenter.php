<?php
/**
 * Created by PhpStorm.
 * User: Dalik
 * Date: 11/30/2019
 * Time: 9:57 PM
 */

namespace App\Presenters;

use App\Forms\CommentFormFactory;
use App\Forms\ProductFormFactory;
use App\Model\TicketManager;
use Nette;
use App\Forms\FormFactory;
use App\Forms\SearchBoxFormFactory;
use App\Model\ProductManager;
use App\Model\Task;
use Nette\Forms\Form;
use Tracy\Debugger;

class ProductPresenter extends BasePresenter
{

    private $database;

    /** @var FormFactory */
    private $factory;

    /** @var Task */
    private $task;

    /** @var ProductManager */
    private $productManager;

    /** @var SearchBoxFormFactory */
    private $searchBoxFormFactory;

    /** @var TicketManager */
    private $ticketManager;

    /** @var ProductFormFactory */
    private $productFormFactory;

    private $filters = [];

    public function __construct(Nette\Database\Context $database, FormFactory $factory, SearchBoxFormFactory $searchBoxFormFactory, ProductManager $productManager, TicketManager $ticketManager, ProductFormFactory $productFormFactory)
    {
        parent::__construct();
        $this->database = $database;
        $this->factory = $factory;
        $this->searchBoxFormFactory = $searchBoxFormFactory;
        $this->productManager = $productManager;
        $this->ticketManager = $ticketManager;
        $this->productFormFactory = $productFormFactory;

        $this->task = new Task();
    }

    public function renderDefault(): void
    {
        $this->permissionRedirect($this->productManager->canShow());

        $this->template->showNew = $this->productManager->canEdit();

        $authors = $this->database->table('User')->fetchPairs('id');
        $parrentProducts = $this->database->table('Product')->fetchPairs('id', 'name');

        $res = $this->productManager->getAll($this->filters);
        $products = [];
        foreach ($res as $key => $value) {
            $products[$key] = $value->toArray();

            $products[$key]['author'] = $authors[$value['managerId']]->name . ' ' . $authors[$value['managerId']]->surname;
            $products[$key]['product'] = ($value['productId'] !== null) ? $parrentProducts[$value['productId']] : "";
        }

        $this['grid']->setData($products);
    }

    public function renderDetail($id): void
    {

        $this->permissionRedirect($this->productManager->canShow());

        $product = $this->productManager->get($id);

        if (!$product) {
            $this->flashMessage("Produkt nenalezen.");
            $this->redirect("Product:default");
        }

        $this->template->product = $product;
        $this->template->manager = $product->dbContext->ref('User', 'managerId');

        $this->template->showCreateTicket = $this->ticketManager->canCreate();

        if($product->productId !== null) {
            $this->template->isSubProduct = true;
            $this->template->parent = $this->productManager->get($product->productId);
        } else {
            $this->template->isSubProduct = false;
        }

        $this->template->showEdit = $this->productManager->canEdit();
    }

    public function actionCreate(): void
    {
        $this->permissionRedirect($this->productManager->canEdit(), 'Product:');
    }

    public function actionEdit($id): void
    {
        $this->permissionRedirect($this->productManager->canEdit(), 'Product:');

        $product = $this->productManager->get($id);
        if (!$product) {
            $this->flashMessage("Produkt nenalezen.");
            $this->redirect("Product:default");
        }

        $this['productForm']->setDefaults($product->toArray());
    }

    protected function createComponentProductForm(): Form
    {
        $origId = $this->getParameter('id');

        return $this->productFormFactory->create(
            function ($id) { $this->redirect('Product:detail', $id); },
            $origId);
    }

    protected function createComponentSearchBox(): Form
    {
        $managers = [];
        $result = $this->database->table('User')->where('role', ["admin", "manager", "executive"]);
        foreach ($result as $manager) {
            $managers[$manager->id] = $manager->name . ' ' . $manager->surname;
        }

        $products = $this->database->table('Product')->fetchPairs('id', 'name');

        $orderby = [
            'name' => "Název",
        ];

        $filterArray = [
            ['type' => 'text' ,'name' => 'name', 'text' => "Vyhledat produkt."],
            ['type' => 'select', 'name' => 'managerId', 'text' => "Odpovědná osoba:", 'items' => $managers],
            ['type' => 'select', 'name' => 'productId', 'text' => "Nadprodukt:", 'items' => $products],
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

        $grid->addHref('Product:detail' ,'id');

        $grid->addColumn('name', "Název", 5);
        $grid->addColumn('author', "Odpovědná osoba", 3);
        $grid->addColumn('product', "Nadprodukt", 4);
        //$grid->addColumn('createDate', "Datum vytvoření", 2, 'datetime');

        $grid->redrawControl();
        return $grid;
    }
}