<?php
namespace verbb\abandonedcart\controllers;

abstract class BaseAdminController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Settings and email templates live in project config, so they can be viewed but not changed when admin changes are off.
        $this->requireAdmin(false);

        return true;
    }
}
