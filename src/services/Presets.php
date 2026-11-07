<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\events\RegisterPresetsEvent;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\Preset;
use verbb\abandonedcart\models\Sequence;

use Craft;
use craft\base\Component;
use craft\events\ConfigEvent;
use craft\events\RebuildConfigEvent;
use craft\helpers\StringHelper;

use Throwable;

class Presets extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_REGISTER_PRESETS = 'registerPresets';

    public const CONFIG_KEY = EmailTemplates::CONFIG_KEY_ROOT . '.presets';

    public const PRESET_THREE_REMINDERS = 'threeReminders';
    public const PRESET_REMINDERS_THEN_DISCOUNT = 'remindersThenDiscount';
    public const PRESET_HIGH_VALUE_CARTS = 'highValueCarts';
    public const PRESET_SINGLE_REMINDER = 'singleReminder';


    // Properties
    // =========================================================================

    private ?array $_presets = null;


    // Public Methods
    // =========================================================================

    /**
     * Every preset New Sequence offers: those kept under Settings, in order, after plugins have had their say.
     *
     * @return Preset[]
     */
    public function getAllPresets(): array
    {
        if ($this->_presets === null) {
            $event = new RegisterPresetsEvent(['presets' => $this->getSettingsPresets()]);
            $this->trigger(self::EVENT_REGISTER_PRESETS, $event);

            $presets = [];

            foreach ($event->presets as $preset) {
                $preset = $preset instanceof Preset ? $preset : new Preset($preset);

                if ($preset->validate()) {
                    $presets[$preset->handle] = $preset;
                } else {
                    AbandonedCart::error('Ignored an invalid sequence preset: ' . implode(' ', $preset->getFirstErrors()));
                }
            }

            $this->_presets = array_values($presets);
        }

        return $this->_presets;
    }

    public function getPresetByHandle(string $handle): ?Preset
    {
        foreach ($this->getAllPresets() as $preset) {
            if ($preset->handle === $handle) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * The presets kept in project config and managed under Settings, in their order, before any plugin changes.
     *
     * @return Preset[]
     */
    public function getSettingsPresets(): array
    {
        $presets = [];

        foreach (Craft::$app->getProjectConfig()->get(self::CONFIG_KEY) ?? [] as $uid => $config) {
            $presets[] = new Preset(array_merge($config, [
                'uid' => $uid,
                'description' => $config['description'] ?? null,
                'audienceSettings' => $config['audienceSettings'] ?? [],
                'emails' => array_values($config['emails'] ?? []),
                'overrides' => $config['overrides'] ?? [],
                'sortOrder' => (int)($config['sortOrder'] ?? 0),
                'isEditable' => true,
            ]));
        }

        usort($presets, static fn(Preset $a, Preset $b): int => $a->sortOrder <=> $b->sortOrder);

        return $presets;
    }

    public function getSettingsPresetByUid(string $uid): ?Preset
    {
        foreach ($this->getSettingsPresets() as $preset) {
            if ($preset->uid === $uid) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Presets that plugins add with `EVENT_REGISTER_PRESETS`, which Settings lists but can't change.
     *
     * @return Preset[]
     */
    public function getPluginPresets(): array
    {
        return array_values(array_filter($this->getAllPresets(), static fn(Preset $preset): bool => !$preset->isEditable));
    }

    public function savePreset(Preset $preset, bool $runValidation = true): bool
    {
        $preset->setScenario(Preset::SCENARIO_SETTINGS);

        if ($runValidation && !$preset->validate()) {
            return false;
        }

        foreach ($this->getSettingsPresets() as $other) {
            if ($other->handle === $preset->handle && $other->uid !== $preset->uid) {
                $preset->addError('handle', Craft::t('abandoned-cart', 'Another preset already uses this handle.'));

                return false;
            }
        }

        if (!$preset->uid) {
            $preset->uid = StringHelper::UUID();
            $preset->sortOrder = max(array_map(static fn(Preset $other): int => $other->sortOrder, $this->getSettingsPresets()) ?: [0]) + 1;
        }

        $preset->isEditable = true;
        Craft::$app->getProjectConfig()->set(self::CONFIG_KEY . '.' . $preset->uid, $preset->getConfig(), "Save the “{$preset->handle}” sequence preset");
        $this->_presets = null;

        return true;
    }

    public function deletePreset(Preset $preset): bool
    {
        if (!$preset->uid || !$preset->isEditable) {
            return false;
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_KEY . '.' . $preset->uid, "Delete the “{$preset->handle}” sequence preset");
        $this->_presets = null;

        return true;
    }

    /**
     * @param string[] $uids Settings presets, in their new order.
     */
    public function reorderPresets(array $uids): void
    {
        $projectConfig = Craft::$app->getProjectConfig();

        foreach (array_values($uids) as $index => $uid) {
            if ($projectConfig->get(self::CONFIG_KEY . '.' . $uid) !== null) {
                $projectConfig->set(self::CONFIG_KEY . '.' . $uid . '.sortOrder', $index + 1, 'Reorder sequence presets');
            }
        }

        $this->_presets = null;
    }

    /**
     * Puts the built-in presets in project config, so a new install has them under Settings. Does nothing when there
     * already are presets, or when project config is being applied from another environment, which brings its own.
     */
    public function installBuiltInPresets(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();

        if ($projectConfig->getIsApplyingExternalChanges() || $projectConfig->get(self::CONFIG_KEY)) {
            return;
        }

        foreach ($this->getBuiltInPresets() as $index => $preset) {
            $preset->sortOrder = $index + 1;
            $preset->uid = StringHelper::UUID();
            $projectConfig->set(self::CONFIG_KEY . '.' . $preset->uid, $preset->getConfig(), 'Add the built-in sequence presets');
        }

        $this->_presets = null;
    }

    /**
     * Presets live only in project config, so a change from anywhere just clears what's cached.
     */
    public function handleChangedPreset(ConfigEvent $event): void
    {
        $this->_presets = null;
    }

    public function rebuildProjectConfig(RebuildConfigEvent $event): void
    {
        $event->config[self::CONFIG_KEY] = Craft::$app->getProjectConfig()->get(self::CONFIG_KEY) ?? [];
    }

    /**
     * The built-in preset with this handle, even when a plugin has removed it from the list. The default sequence is built
     * from Three Reminders, so it must always be available.
     */
    public function getBuiltInPresetByHandle(string $handle): ?Preset
    {
        foreach ($this->getBuiltInPresets() as $preset) {
            if ($preset->handle === $handle) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Creates a new sequence, switched off, as a one-time copy of a preset. `$notices` collects anything the preset
     * asked for that this site doesn't have, such as an email template handle, so the editor can say so. Check the
     * returned sequence for errors: on failure nothing is saved.
     */
    public function createSequenceFromPreset(Preset $preset, array $config = [], array &$notices = []): Sequence
    {
        $sequence = new Sequence(array_merge([
            'name' => $preset->name,
            'enabled' => false,
            'audienceType' => $preset->audienceType,
            'audienceSettings' => $preset->audienceSettings,
        ], $preset->overrides, $config));

        $templates = AbandonedCart::$plugin->getEmailTemplates();
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            if (!AbandonedCart::$plugin->getSequences()->saveSequence($sequence)) {
                $transaction->rollBack();

                return $sequence;
            }

            foreach (array_slice($preset->emails, 0, Email::MAX_PER_SEQUENCE) as $index => $attributes) {
                $templateHandle = $attributes['emailTemplate'] ?? null;
                unset($attributes['emailTemplate'], $attributes['offersDiscount']);

                $email = new Email(array_merge($attributes, [
                    'sequenceId' => $sequence->id,
                    'position' => $index + 1,
                ]));

                if ($templateHandle) {
                    $template = $templates->getTemplateByHandle($templateHandle);
                    $email->emailTemplateId = $template?->id;

                    if (!$template) {
                        $notices[] = Craft::t('abandoned-cart', 'Email {number} uses the default template, because there’s no “{handle}” template.', ['number' => $index + 1, 'handle' => $templateHandle]);
                    }
                }

                if (!AbandonedCart::$plugin->getEmails()->saveEmail($email)) {
                    $sequence->addError('emails', implode(' ', $email->getFirstErrors()));
                    $sequence->id = null;
                    $transaction->rollBack();

                    return $sequence;
                }
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        return $sequence;
    }


    /**
     * The presets Abandoned Cart ships with, in the installer's language. They're copied into project config on
     * install; after that, Settings manages them.
     *
     * @return Preset[]
     */
    public function getBuiltInPresets(): array
    {
        $reminder = [
            'subject' => Craft::t('abandoned-cart', 'You left something behind'),
            'previewText' => Craft::t('abandoned-cart', 'Your cart is saved for you.'),
            'heading' => Craft::t('abandoned-cart', 'You left something behind'),
            'message' => Craft::t('abandoned-cart', 'Your cart is still waiting. Come back whenever you’re ready to finish your order.'),
            'buttonLabel' => Craft::t('abandoned-cart', 'Return to your cart'),
        ];
        $nudge = [
            'subject' => Craft::t('abandoned-cart', 'Still thinking it over?'),
            'previewText' => Craft::t('abandoned-cart', 'Your items are still waiting.'),
            'heading' => Craft::t('abandoned-cart', 'Still thinking it over?'),
            'message' => Craft::t('abandoned-cart', 'A quick reminder that the items in your cart are still available.'),
            'buttonLabel' => Craft::t('abandoned-cart', 'View your cart'),
        ];
        $lastReminder = [
            'subject' => Craft::t('abandoned-cart', 'A final reminder about your cart'),
            'previewText' => Craft::t('abandoned-cart', 'Your cart is saved, for now.'),
            'heading' => Craft::t('abandoned-cart', 'A final reminder'),
            'message' => Craft::t('abandoned-cart', 'This is our last reminder. Your cart is saved for now, so you can pick up where you left off.'),
            'buttonLabel' => Craft::t('abandoned-cart', 'Complete your order'),
        ];
        $lastChance = [
            'subject' => Craft::t('abandoned-cart', 'Last chance: a discount on your cart'),
            'previewText' => Craft::t('abandoned-cart', 'Use your code at checkout.'),
            'heading' => Craft::t('abandoned-cart', 'A little something to help you decide'),
            'message' => Craft::t('abandoned-cart', 'This is our last reminder. Use the code below at checkout to save on your order.'),
            'buttonLabel' => Craft::t('abandoned-cart', 'Finish your order'),
            'offersDiscount' => true,
        ];

        return [
            new Preset([
                'handle' => self::PRESET_THREE_REMINDERS,
                'name' => Craft::t('abandoned-cart', 'Three Reminders'),
                'description' => Craft::t('abandoned-cart', 'The common pattern: a quick nudge, a follow-up, and a last reminder.'),
                'emails' => [
                    ['delayMinutes' => 0] + $reminder,
                    ['delayMinutes' => 1440] + $nudge,
                    ['delayMinutes' => 2880] + $lastReminder,
                ],
            ]),
            new Preset([
                'handle' => self::PRESET_REMINDERS_THEN_DISCOUNT,
                'name' => Craft::t('abandoned-cart', 'Reminders, Then a Discount'),
                'description' => Craft::t('abandoned-cart', 'Two reminders, with a discount code saved for the last email.'),
                'emails' => [
                    ['delayMinutes' => 0] + $reminder,
                    ['delayMinutes' => 1440] + $nudge,
                    ['delayMinutes' => 2880] + $lastChance,
                ],
            ]),
            new Preset([
                'handle' => self::PRESET_HIGH_VALUE_CARTS,
                'name' => Craft::t('abandoned-cart', 'High-Value Carts'),
                'description' => Craft::t('abandoned-cart', 'For carts over an amount you choose, with a more personal tone.'),
                'audienceType' => Sequence::AUDIENCE_CART_VALUE,
                'audienceSettings' => ['operator' => 'atLeast', 'amount' => null],
                'emails' => [
                    ['delayMinutes' => 0] + $reminder,
                    ['delayMinutes' => 1440] + $nudge,
                ],
            ]),
            new Preset([
                'handle' => self::PRESET_SINGLE_REMINDER,
                'name' => Craft::t('abandoned-cart', 'Single Reminder'),
                'description' => Craft::t('abandoned-cart', 'One friendly email. Good for stores that email customers rarely.'),
                'emails' => [
                    ['delayMinutes' => 180] + $reminder,
                ],
            ]),
        ];
    }
}
