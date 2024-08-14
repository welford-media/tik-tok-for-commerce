<?php

namespace WelfordMedia\CraftTikTok\records;

class VariantMapping extends \craft\db\ActiveRecord
{
    public static function primaryKey()
    {
        return ["variantId"];
    }

    public static function tableName(): string
    {
        return "{{%tiktok_variant_mapping}}";
    }

    public function rules(): array
    {
        return [
            [["variantId", "tiktokProductId"], "required"],
            [["variantId", "tiktokProductId"], "integer"],
        ];
    }
}
