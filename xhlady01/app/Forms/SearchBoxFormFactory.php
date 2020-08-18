<?php
/**
 * Created by PhpStorm.
 * User: Dalik
 * Date: 11/30/2019
 * Time: 1:09 PM
 */

namespace App\Forms;


use Nette\Application\UI\Form;

class SearchBoxFormFactory
{
    /** @var FormFactory */
    private $factory;

    public function __construct(FormFactory $factory)
    {
        $this->factory = $factory;
    }

    public function create($filterArray, $handle): Form
    {
        $form = $this->factory->create();
        $form->setMethod('get');

        foreach($filterArray as $filter) {
            switch ($filter['type'])
            {
                case 'text':
                    $form->addText($filter['name'])
                        ->setHtmlAttribute('placeholder', $filter['text']);
                        //->setHtmlAttribute('style', "min-width: 200px;");
                    break;
                case 'select':
                    $form->addSelect($filter['name'], $filter['text'], $filter['items'])
                        ->setPrompt('Nezáleží');
                    break;
            }

        }

        /*$form->addText('searchPhrase')
            ->setHtmlAttribute('placeholder', 'Vyhledat task');*/

        $form->addSubmit('send', 'Hledat');

        $form->onSuccess[] = function (Form $form, \stdClass $values) use ($handle, $filterArray): void {
            /*$resultArray = [];

            foreach($filterArray as $filter) {
                $resultArray[$filter['name']] = $values[$filter['name']];
            }*/

            $handle($values);
        };

        return $form;
    }

}