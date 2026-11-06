<?php
namespace verbb\abandonedcart\models;

use craft\base\Model;

class RenderedEmail extends Model
{
    // Properties
    // =========================================================================

    public string $subject = '';
    public string $previewText = '';
    public string $html = '';
    public string $text = '';
    public string $recoverUrl = '';
    public string $unsubscribeUrl = '';
}
