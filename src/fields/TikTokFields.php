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
class TikTokFields extends Field
{
    public static function displayName(): string
    {
        return Craft::t("tik-tok-for-commerce", "TikTok Fields");
    }

    public static function valueType(): string
    {
        return "array";
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
        return Schema::TYPE_JSON;
    }

    public function normalizeValue(
        mixed $value,
        ?ElementInterface $element = null
    ): mixed {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        return $value;
    }

    protected function inputHtml(
        mixed $value,
        ?ElementInterface $element = null
    ): string {
        $html = "";

        // Enabled
        $html .=
            "<div class=\"field\"><div class=\"heading\"><label>Sync Status</label></div><div class=\"select\">";
        $field = Html::dropDownList(
            $this->handle . "[enabled]",
            $value["enabled"] ?? 1,
            [
                "1" => "Enabled",
                "0" => "Disabled",
            ],
            [
                "class" => "select",
                "id" => $this->handle . "-categoryId",
            ]
        );
        $html .= $field;
        $html .= "</div></div>";

        // Category ID Link
        $html .=
            "<div class=\"field\"><div class=\"heading\"><label>Category</label></div><div class=\"select\">";
        $field = Html::dropDownList(
            $this->handle . "[categoryId]",
            $value["categoryId"] ?? "",
            $this->getCategories(),
            [
                "class" => "select",
                "id" => $this->handle . "-categoryId",
            ]
        );
        $html .= $field;
        $html .= "</div></div>";

        // Warehouse ID Link
        $html .=
            "<div class=\"field\"><div class=\"heading\"><label>Warehouse</label></div><div class=\"select\">";
        $field = Html::dropDownList(
            $this->handle . "[warehouseId]",
            $value["warehouseId"] ?? "",
            $this->getWarehouses(),
            [
                "class" => "select",
                "id" => $this->handle . "-warehouseId",
            ]
        );
        $html .= $field;
        $html .= "</div></div>";

        $html .= "<div class=\"field\">";
        $html .= "<div class=\"heading\"><label>Description</label></div>";
        $html .=
            "<div class=\"instructions\"><p>Provide a HTML product description for TikTok. You can use only &lt;p&gt; &lt;img&gt; &lt;ul&gt; &lt;ol&gt; &lt;li&gt; &lt;br&gt; &lt;strong&gt; &lt;b&gt; &lt;i&gt; &lt;em&gt; &lt;u&gt; HTML tags, all other tags will be filtered out.</p></div>";
        $field = Html::textarea(
            $this->handle . "[description]",
            $value["description"] ?? "",
            [
                "id" => $this->handle . "-description",
                "class" => "text fullwidth",
                "rows" => "5",
            ]
        );
        $html .= $field;
        $html .= "</div>";

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

    private function getCategories(): array
    {
        $tiktok = \WelfordMedia\CraftTikTok\TikTok::getInstance()->tiktok;
        $categories = $tiktok->getCategoryList();
        $options = [];
        $options[""] = "Select a category";
        if (
            !isset($categories["categories"]) ||
            empty($categories["categories"]) ||
            !is_array($categories["categories"])
        ) {
            return ["" => "No categories found"];
        }
        $categories = $this->remapCategoryArray($categories["categories"]);
        foreach ($categories as $key => $category) {
            $options[$key] = $category;
        }
        return $options;
    }

    private function remapCategoryArray($categories): array
    {
        $result = [];
        $categoryMap = [];

        // Build a map for quick access to category by id
        foreach ($categories as $category) {
            $categoryMap[$category["id"]] = $category;
        }

        // Create the remapped array
        foreach ($categories as $category) {
            if (
                in_array(
                    "INVITE_ONLY",
                    $category["permission_statuses"] ?? []
                ) ||
                in_array(
                    "NON_MAIN_CATEGORY",
                    $category["permission_statuses"] ?? []
                ) ||
                $category["is_leaf"] == false
            ) {
                continue;
            }

            $categoryPath = $this->buildCategoryPath($category, $categoryMap);
            $result[$category["id"]] = $categoryPath;
        }

        asort($result);

        return $result;
    }

    private function buildCategoryPath($category, $categoryMap): string
    {
        if (
            empty($category["parent_id"]) ||
            !isset($categoryMap[$category["parent_id"]])
        ) {
            return $category["local_name"];
        }

        $parentCategory = $categoryMap[$category["parent_id"]];
        return $this->buildCategoryPath($parentCategory, $categoryMap) .
            "/" .
            $category["local_name"];
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
