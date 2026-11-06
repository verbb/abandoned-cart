<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\events\EmailTemplateEvent;
use verbb\abandonedcart\models\EmailTemplate;
use verbb\abandonedcart\records\EmailTemplate as EmailTemplateRecord;

use Craft;
use craft\base\Component;
use craft\base\MemoizableArray;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\events\RebuildConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;

use Throwable;

class EmailTemplates extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_EMAIL_TEMPLATE = 'beforeSaveEmailTemplate';
    public const EVENT_AFTER_SAVE_EMAIL_TEMPLATE = 'afterSaveEmailTemplate';
    public const EVENT_BEFORE_DELETE_EMAIL_TEMPLATE = 'beforeDeleteEmailTemplate';
    public const EVENT_AFTER_DELETE_EMAIL_TEMPLATE = 'afterDeleteEmailTemplate';

    public const CONFIG_KEY_ROOT = 'abandoned-cart';
    public const CONFIG_KEY = self::CONFIG_KEY_ROOT . '.emailTemplates';


    // Properties
    // =========================================================================

    private ?MemoizableArray $_templates = null;


    // Public Methods
    // =========================================================================

    public function getAllTemplates(): array
    {
        // Copies, so a caller editing a template can't change what everyone else reads before it's saved.
        return array_map(static fn(EmailTemplate $template): EmailTemplate => clone $template, $this->_templates()->all());
    }

    public function getTemplateById(int $id): ?EmailTemplate
    {
        return $this->_copy($this->_templates()->firstWhere('id', $id));
    }

    public function getTemplateByHandle(string $handle): ?EmailTemplate
    {
        return $this->_copy($this->_templates()->firstWhere('handle', $handle, true));
    }

    public function getTemplateByUid(string $uid): ?EmailTemplate
    {
        return $this->_copy($this->_templates()->firstWhere('uid', $uid, true));
    }

    public function getDefaultTemplate(): ?EmailTemplate
    {
        return $this->_copy($this->_templates()->firstWhere('isDefault', true));
    }

    public function saveTemplate(EmailTemplate $template, bool $runValidation = true): bool
    {
        $isNew = !$template->id;

        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_EMAIL_TEMPLATE)) {
            $event = new EmailTemplateEvent(['template' => $template, 'isNew' => $isNew]);
            $this->trigger(self::EVENT_BEFORE_SAVE_EMAIL_TEMPLATE, $event);

            if (!$event->isValid) {
                return false;
            }
        }

        if ($runValidation && !$template->validate()) {
            return false;
        }

        if ($isNew) {
            $template->uid = StringHelper::UUID();
            $template->sortOrder = (int)(new Query())->from([Table::EMAIL_TEMPLATES])->max('[[sortOrder]]') + 1;
        } elseif (!$template->uid) {
            $template->uid = Db::uidById(Table::EMAIL_TEMPLATES, $template->id);
        }

        $projectConfig = Craft::$app->getProjectConfig();

        // There's always exactly one default, so making this one the default clears the others.
        if ($template->isDefault) {
            foreach ($this->getAllTemplates() as $other) {
                if ($other->uid !== $template->uid && $other->isDefault) {
                    $other->isDefault = false;
                    $projectConfig->set(self::CONFIG_KEY . '.' . $other->uid, $other->getConfig(), "Make “{$template->handle}” the default email template");
                }
            }
        }

        $projectConfig->set(self::CONFIG_KEY . '.' . $template->uid, $template->getConfig(), "Save the “{$template->handle}” email template");

        if ($isNew) {
            $template->id = Db::idByUid(Table::EMAIL_TEMPLATES, $template->uid);
        }

        return true;
    }

    public function handleChangedTemplate(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $record = EmailTemplateRecord::findOne(['uid' => $uid]) ?? new EmailTemplateRecord();
            $isNew = $record->getIsNewRecord();

            $record->name = $data['name'];
            $record->handle = $data['handle'];
            $record->htmlTemplate = $data['htmlTemplate'] ?? null;
            $record->textTemplate = $data['textTemplate'] ?? null;
            $record->isDefault = (bool)($data['isDefault'] ?? false);
            $record->sortOrder = (int)($data['sortOrder'] ?? 0);
            $record->uid = $uid;
            $record->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        $this->_templates = null;

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_EMAIL_TEMPLATE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_EMAIL_TEMPLATE, new EmailTemplateEvent([
                'template' => $this->getTemplateById($record->id),
                'isNew' => $isNew,
            ]));
        }
    }

    public function deleteTemplateById(int $id): bool
    {
        $template = $this->getTemplateById($id);

        return $template && $this->deleteTemplate($template);
    }

    public function deleteTemplate(EmailTemplate $template): bool
    {
        if ($template->isDefault) {
            $template->addError('isDefault', Craft::t('abandoned-cart', 'Make another template the default first.'));

            return false;
        }

        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_EMAIL_TEMPLATE)) {
            $event = new EmailTemplateEvent(['template' => $template]);
            $this->trigger(self::EVENT_BEFORE_DELETE_EMAIL_TEMPLATE, $event);

            if (!$event->isValid) {
                return false;
            }
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_KEY . '.' . $template->uid, "Delete the “{$template->handle}” email template");

        return true;
    }

    public function handleDeletedTemplate(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $template = $this->getTemplateByUid($uid);

        if (!$template) {
            return;
        }

        // Emails that used it lose the link through the foreign key and fall back to the default.
        Db::delete(Table::EMAIL_TEMPLATES, ['id' => $template->id]);

        $this->_templates = null;

        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_EMAIL_TEMPLATE)) {
            $this->trigger(self::EVENT_AFTER_DELETE_EMAIL_TEMPLATE, new EmailTemplateEvent([
                'template' => $template,
            ]));
        }
    }

    public function rebuildProjectConfig(RebuildConfigEvent $event): void
    {
        $config = [];

        foreach ($this->getAllTemplates() as $template) {
            $config[$template->uid] = $template->getConfig();
        }

        $event->config[self::CONFIG_KEY] = $config;
    }


    // Private Methods
    // =========================================================================

    private function _copy(?EmailTemplate $template): ?EmailTemplate
    {
        return $template ? clone $template : null;
    }

    private function _templates(): MemoizableArray
    {
        if ($this->_templates === null) {
            $templates = array_map(static fn(array $row): EmailTemplate => new EmailTemplate($row), $this->_createTemplateQuery()->all());

            $this->_templates = new MemoizableArray($templates);
        }

        return $this->_templates;
    }

    private function _createTemplateQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'htmlTemplate',
                'textTemplate',
                'isDefault',
                'sortOrder',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from([Table::EMAIL_TEMPLATES])
            ->orderBy(['sortOrder' => SORT_ASC, 'id' => SORT_ASC]);
    }
}
