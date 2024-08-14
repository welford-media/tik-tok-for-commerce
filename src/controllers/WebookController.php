<?php

namespace WelfordMedia\CraftTikTok\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\web\Controller;
use WelfordMedia\CraftTikTok\TikTok;
use WelfordMedia\CraftTikTok\jobs\ProcessOrder;
use WelfordMedia\CraftTikTok\fields\TikTokOrderId;

class WebhookController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function actionProcess(): \yii\web\Response
    {
        $tiktok = TikTok::getInstance()->tiktok;
        $result = $tiktok->verifyWebhook();
        if (
            is_array($result) &&
            isset($result["order_id"]) &&
            !empty($result["order_id"])
        ) {
            if ($this->canProcessWebhooks()) {
                Craft::$app->queue->push(
                    new ProcessOrder(["id" => $result["order_id"]])
                );
            }
        }
        return $this->asJson(["success" => true]);
    }

    private function canProcessWebhooks(): bool
    {
        $test = new \craft\commerce\elements\Order();
        foreach ($test->fieldLayout->tabs as $tab) {
            foreach ($tab->elements as $element) {
                if ($element->field instanceof TikTokOrderId) {
                    return true;
                }
            }
        }
        return false;
    }
}
