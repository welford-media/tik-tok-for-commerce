<?php

namespace WelfordMedia\CraftTikTok\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use yii\db\Schema;

/**
 * TikTok Warehouse field type
 */
class TikTokOrderId extends Field
{
    public static function displayName(): string
    {
        return Craft::t("tik-tok-for-commerce", "TikTok Order ID");
    }

    public static function valueType(): string
    {
        return "string";
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            // ...
        ]);
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return null;
    }

    public function getContentColumnType(): array|string
    {
        return Schema::TYPE_STRING;
    }

    public function normalizeValue(
        mixed $value,
        ?ElementInterface $element = null
    ): mixed {
        return $value;
    }

    protected function inputHtml(
        mixed $value,
        ?ElementInterface $element = null
    ): string {
        $html = "<div class=\"field\"><div class=\"\">";
        $field = Html::textInput($this->handle, $value, [
            "class" => "text fullwidth",
            "id" => $this->handle,
            "readonly" => true,
        ]);
        $html .= $field;
        $html .= "</div></div>";
        return $html;
    }

    public function getElementValidationRules(): array
    {
        return [];
    }

    protected function searchKeywords(
        mixed $value,
        ElementInterface $element
    ): string {
        return StringHelper::toString($value, " ");
    }

    public function getElementConditionRuleType(): array|string|null
    {
        return null;
    }

    public function modifyElementsQuery(
        ElementQueryInterface $query,
        mixed $value
    ): void {
        parent::modifyElementsQuery($query, $value);
    }
}
