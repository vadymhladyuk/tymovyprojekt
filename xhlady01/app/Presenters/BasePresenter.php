<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    protected function checkPermissions(string $resource)
    {
        if (!$this->getUser()->isLoggedIn() or !$this->getUser()->isAllowed($resource)) {
            $this->flashMessage("Nemáte dostatečná oprávnění.", 'danger');
            $this->redirect('Homepage:');
        }
    }

    protected function permissionRedirect(bool $allowed, string $destination = 'Homepage:'): void
    {
        if (!$allowed) {
            $this->flashMessage("Nemáte dostatečná oprávnění.", 'danger');
            $this->redirect($destination);
        }
    }
}
