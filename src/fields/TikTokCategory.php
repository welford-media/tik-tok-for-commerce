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
class TikTokCategory extends Field
{
    public static function displayName(): string
    {
        return Craft::t("tik-tok-for-commerce", "TikTok Category");
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
            $this->getCategories(),
            [
                "class" => "select",
                "id" => $this->handle,
            ]
        );
        $html .= $field;
        $html .= "</div></div>";
        return $html;
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

        // Recursive function to build the full path
        function buildCategoryPath($category, $categoryMap)
        {
            if (
                empty($category["parent_id"]) ||
                !isset($categoryMap[$category["parent_id"]])
            ) {
                return $category["local_name"];
            }

            $parentCategory = $categoryMap[$category["parent_id"]];
            return buildCategoryPath($parentCategory, $categoryMap) .
                "/" .
                $category["local_name"];
        }

        // Create the remapped array
        foreach ($categories as $category) {
            $categoryPath = buildCategoryPath($category, $categoryMap);
            $result[$category["id"]] = $categoryPath;
        }

        asort($result);

        return $result;
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
