<?php
namespace verbb\abandonedcart\controllers;

use craft\web\Controller;

abstract class BaseCpController extends Controller
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();
        $this->requirePermission('accessPlugin-abandoned-cart');

        return true;
    }
}
