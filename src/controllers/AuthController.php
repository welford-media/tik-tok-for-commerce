<?php

namespace WelfordMedia\CraftTikTok\controllers;

use Craft;
use craft\web\Controller;
use WelfordMedia\CraftTikTok\TikTok;

class AuthController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function actionStart(): \yii\web\Response
    {
        $tiktok = TikTok::getInstance()->tiktok;
        try {
            $url = $tiktok->startAuthRequest();
        } catch (\Exception $e) {
            Craft::$app->getSession()->setError($e->getMessage());
            return $this->redirect("settings/plugins/tik-tok-for-commerce");
        }
        Craft::$app->getResponse()->redirect($url);
    }

    public function actionCallback(): \yii\web\Response
    {
        $tiktok = TikTok::getInstance()->tiktok;
        try {
            $tiktok->authRequestCallback();
        } catch (\Exception $e) {
            return $this->asJson([
                "success" => false,
                "error" => $e->getMessage(),
            ]);
        }
        Craft::$app
            ->getSession()
            ->setNotice(
                "TikTok for Commerce has been successfully authenticated."
            );
        return $this->redirect("settings/plugins/tik-tok-for-commerce");
    }
}
