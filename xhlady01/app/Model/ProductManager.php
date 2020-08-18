<?php
/**
 * Created by PhpStorm.
 * User: Dalik
 * Date: 11/30/2019
 * Time: 9:00 PM
 */

namespace App\Model;

use Nette;

class Product
{
    public $id = 0;
    public $productId = 0;
    public $managerId = 0;
    public $name = "";
    public $description = "";

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
        $this->productId = $activeRow['productId'];
        $this->managerId = $activeRow['managerId'];
        $this->name = $activeRow['name'];
        $this->description = $activeRow['description'];

        $this->dbContext = $activeRow;
    }

    public function toArray()
    {
        return [
            'name' => $this->name,
            'productId' => $this->productId,
            'managerId' => $this->managerId,
            'description' => $this->description
        ];
    }
}

class ProductManager
{
    private $database;

    /** @var Nette\Security\User */
    private $user;

    private const
        TABLE_NAME = 'Product';

    public function __construct(Nette\Database\Context $database, Nette\Security\User $user)
    {
        $this->database = $database;
        $this->user = $user;
    }

    public function add(Product $product): ? Nette\Database\Table\ActiveRow
    {
        if (!$this->canEdit()) return null;

        return $this->database->table(self::TABLE_NAME)->insert($product->toArray());
    }

    public function update(Product $product)
    {
        if (!$this->canEdit()) return null;

        $this->database->table(self::TABLE_NAME)
            ->where('id', $product->id)
            ->update($product->toArray());
    }

    public function get($id): ? Product
    {
        if (!$this->canShow()) return null;

        return new Product($this->database->table(self::TABLE_NAME)->get($id));
    }

    public function canShow(): bool
    {
        return ($this->user->isLoggedIn() and $this->user->isAllowed('show_products'));
    }

    public function canEdit(): bool
    {
        return ($this->user->isLoggedIn() and $this->user->isAllowed('edit_product'));
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
                case 'managerId':
                    $result->where('managerId', $value);
                    break;
                case 'productId':
                    $result->where('productId', $value);
                    break;

                case 'order':
                    $result->order($value);
                    break;
            }
        }
        return $result;
    }
}