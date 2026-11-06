<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\AbandonedCart;

use verbb\base\services\Templates as BaseTemplates;

class Templates extends BaseTemplates
{
    // Properties
    // =========================================================================

    public string $pluginClass = AbandonedCart::class;

    // Subjects are plain text, so Twig in them renders without HTML escaping.
    public string|false|null $sandboxedAutoescape = false;
}
