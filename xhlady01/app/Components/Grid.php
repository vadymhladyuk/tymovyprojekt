<?php
/**
 * Created by PhpStorm.
 * User: jan
 * Date: 2019-12-02
 * Time: 18:00
 */

use Nette\Application\UI;

class Grid extends UI\Control
{
    private $columns = [];

    private $data = null;

    private $hrefColumn = null;
    private $hrefAction = null;

    public function addColumn($name, $label, $width, $type = 'text'): void
    {
        $this->columns[$name] = ['name' => $name, 'label' => $label, 'width' => $width, 'type' => $type];
    }

    public function addHref($action, $columnName): void
    {
        $this->hrefAction = $action;
        $this->hrefColumn = $columnName;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function render(): void
    {
        $template = $this->template;

        $template->columns = $this->columns;
        $template->data = $this->data;

        $template->hrefAction = $this->hrefAction;
        $template->hrefColumn = $this->hrefColumn;

        $template->render(__DIR__ . '/Grid.latte');
    }
}