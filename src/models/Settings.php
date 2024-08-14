<?php

namespace WelfordMedia\CraftTikTok\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * TikTok for Commerce settings
 */
class Settings extends Model
{
    public $app_key;
    public $app_secret;
    public $shops = [];
    public $shop_cipher;
    public $access_token;
    public $refresh_token;
    public $draft_mode;

    public function rules(): array
    {
        return [
            [["app_key", "app_secret"], "string"],
            [["app_key", "app_secret"], "required"],
            [["draft_mode"], "boolean"],
        ];
    }

    public function getAppKey(): string
    {
        return App::parseEnv($this->app_key);
    }

    public function getAppSecret(): string
    {
        return App::parseEnv($this->app_secret);
    }
}
