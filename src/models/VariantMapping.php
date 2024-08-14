<?php

namespace WelfordMedia\CraftTikTok\models;

class VariantMapping extends \craft\base\Model
{
    public $variantId;
    public $tiktokProductId;

    public function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [["variantId", "tiktokProductId"], "required"];
        $rules[] = [["variantId", "tiktokProductId"], "integer"];
        return $rules;
    }
}
