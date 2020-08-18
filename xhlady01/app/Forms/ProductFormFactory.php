<?php
/**
 * Created by PhpStorm.
 * User: jan
 * Date: 2019-12-01
 * Time: 15:33
 */

namespace App\Forms;

use App\Model\Product;
use App\Model\ProductManager;
use Nette\Application\UI\Form;
use Nette;

class ProductFormFactory
{
    /** @var FormFactory */
    private $factory;

    /** @var ProductManager */
    private $productManager;

    /** @var Nette\Database\Context */
    private $database;

    public function __construct(FormFactory $factory, ProductManager $productManager, Nette\Database\Context $database)
    {
        $this->factory = $factory;
        $this->productManager = $productManager;
        $this->database = $database;
    }

    public function create(callable $onSuccess, int $origId = null): Form
    {
        $form = $this->factory->create();

        $form->addText('name', "Název produktu:")
            ->setRequired("Pole s názvem je povinné.");

        $managers = $this->database->table('User')
            ->where('role', 'manager')
            ->fetchPairs('id', 'login');
        $form->addSelect('managerId', "Manažer:", $managers)
            ->setPrompt("Vyberte login manažera")
            ->setRequired("Vyberte manažera.");

        $products = $this->database->table('Product')
            ->fetchPairs('id', 'name');
        $form->addSelect('productId', "Nadprodukt:", $products)
            ->setPrompt("Žádný");

        $form->addTextArea('description', "Popis produktu (markdown):")
            ->setHtmlAttribute('style', "height: 180px")
            ->setRequired("Pole s popisem je povinné.");

        $form->addSubmit('send', "Uložit");

        $form->onSuccess[] = function (Form $form, \stdClass $values) use ($onSuccess, $origId): void {
            if ($origId) {
                $product = $this->productManager->get($origId);

                $product->name = $values->name;
                $product->managerId = $values->managerId;
                $product->productId = $values->productId;
                $product->description = $values->description;

                $this->productManager->update($product);

                $id = $origId;
            } else {
                $product = new Product();

                $product->name = $values->name;
                $product->managerId = $values->managerId;
                $product->productId = $values->productId;
                $product->description = $values->description;

                $res = $this->productManager->add($product);

                $id = $res->id;
            }

            $onSuccess($id);
        };

        return $form;
    }

}