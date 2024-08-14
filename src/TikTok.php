<?php

namespace WelfordMedia\CraftTikTok;

use Craft;
use WelfordMedia\CraftTikTok\fields\TikTokCategory;
use WelfordMedia\CraftTikTok\fields\TikTokOrderId;
use WelfordMedia\CraftTikTok\fields\TikTokWarehouse;
use WelfordMedia\CraftTikTok\jobs\DeSyncProduct;
use WelfordMedia\CraftTikTok\jobs\SyncProduct;
use WelfordMedia\CraftTikTok\models\Settings;
use craft\base\Model;
use craft\base\Plugin;
use craft\commerce\elements\Variant;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Fields;
use craft\web\UrlManager;
use craft\web\View;

use yii\base\Event;

/**
 * TikTok for Commerce plugin
 *
 * @method static TikTok getInstance()
 * @method Settings getSettings()
 * @author Welford Media <hello@welfordmedia.co.uk>
 * @copyright Welford Media
 * @license https://craftcms.github.io/license/ Craft License
 */
class TikTok extends Plugin
{
    public string $schemaVersion = "1.0.0";
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            "components" => [
                "tiktok" =>
                    \WelfordMedia\CraftTikTok\services\TikTokService::class,
                "mapping" =>
                    \WelfordMedia\CraftTikTok\services\MappingService::class,
                "commerce" =>
                    \WelfordMedia\CraftTikTok\services\CommerceService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function () {
            // ...
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate(
            "tik-tok-for-commerce/_settings.twig",
            [
                "plugin" => $this,
                "settings" => $this->getSettings(),
            ]
        );
    }

    private function attachEventHandlers(): void
    {
        Event::on(Variant::class, Variant::EVENT_AFTER_SAVE, function (
            ModelEvent $event
        ) {
            $variant = $event->sender;
            $product = $variant->getProduct();
            if (
                (isset($variant->tiktokSync) && $variant->tiktokSync) ||
                (isset($product->tiktokSync) && $product->tiktokSync)
            ) {
                Craft::$app->getQueue()->push(
                    new SyncProduct([
                        "id" => $variant->id,
                    ])
                );
            }
        });

        Event::on(Variant::class, Variant::EVENT_AFTER_RESTORE, function (
            ModelEvent $event
        ) {
            $variant = $event->sender;
            if (isset($variant->tiktokSync) && $variant->tiktokSync) {
                Craft::$app->getQueue()->push(
                    new SyncProduct([
                        "id" => $variant->id,
                    ])
                );
            }
        });

        Event::on(Variant::class, Variant::EVENT_AFTER_DELETE, function (
            ModelEvent $event
        ) {
            $variant = $event->sender;
            if (isset($variant->tiktokSync) && $variant->tiktokSync) {
                Craft::$app->getQueue()->push(
                    new DeSyncProduct([
                        "id" => $variant->id,
                    ])
                );
            }
        });

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules["tiktok-webhook"] =
                    "tik-tok-for-commerce/webhook/process";
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules["tik-tok-for-commerce/auth/start"] =
                    "tik-tok-for-commerce/auth/start";
                $event->rules["tiktok-callback"] =
                    "tik-tok-for-commerce/auth/callback";
            }
        );

        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $e) {
                if (
                    is_dir(
                        $baseDir =
                            $this->getBasePath() .
                            DIRECTORY_SEPARATOR .
                            "templates"
                    )
                ) {
                    $e->roots[$this->id] = $baseDir;
                }
            }
        );
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function (
            RegisterComponentTypesEvent $event
        ) {
            $event->types[] = TikTokWarehouse::class;
            $event->types[] = TikTokCategory::class;
            $event->types[] = TikTokOrderId::class;
        });
    }
}
