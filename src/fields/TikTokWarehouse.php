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
class TikTokWarehouse extends Field
{
    public static function displayName(): string
    {
        return Craft::t("tik-tok-for-commerce", "TikTok Warehouse");
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
        $html = "<div class=\"field\"><div class=\"select\">";
        $field = Html::dropDownList(
            $this->handle,
            $value,
            $this->getWarehouses(),
            [
                "class" => "select",
                "id" => $this->handle,
            ]
        );
        $html .= $field;
        $html .= "</div></div>";
        return $html;
    }

    private function getWarehouses(): array
    {
        $tiktok = \WelfordMedia\CraftTikTok\TikTok::getInstance()->tiktok;
        $warehouses = $tiktok->getWarehouses();
        $options = [];
        $options[""] = "Select a warehouse";
        if (
            !isset($warehouses["warehouses"]) ||
            empty($warehouses["warehouses"]) ||
            !is_array($warehouses["warehouses"])
        ) {
            return ["" => "No warehouses found"];
        }
        foreach ($warehouses["warehouses"] as $warehouse) {
            $options[$warehouse["id"]] = $warehouse["name"];
        }
        return $options;
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
