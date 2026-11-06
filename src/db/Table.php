<?php
namespace verbb\abandonedcart\db;

abstract class Table
{
    // Constants
    // =========================================================================

    public const ACTIVITIES = '{{%abandonedcart_activities}}';
    public const ATTEMPTS = '{{%abandonedcart_attempts}}';
    public const CARTS = '{{%abandonedcart_carts}}';
    public const CUSTOMERS = '{{%abandonedcart_customers}}';
    public const EMAILS = '{{%abandonedcart_emails}}';
    public const EMAIL_TEMPLATES = '{{%abandonedcart_emailtemplates}}';
    public const RECOVERIES = '{{%abandonedcart_recoveries}}';
    public const SEQUENCES = '{{%abandonedcart_sequences}}';
    public const SEQUENCE_VERSIONS = '{{%abandonedcart_sequenceversions}}';
    public const SKIPPED_CARTS = '{{%abandonedcart_skippedcarts}}';
    public const STATE = '{{%abandonedcart_state}}';
    public const VISITS = '{{%abandonedcart_visits}}';
}
