<?php

namespace WelfordMedia\CraftTikTok\helpers;

use craft\commerce\elements\Variant;
use craft\commerce\elements\Product;
use WelfordMedia\CraftTikTok\fields\TikTokFields;

class CommerceHelpers
{
    public static function getElementHasTikTokField(
        Variant|Product $element
    ): bool {
        return self::getElementsTikTokFieldValues($element) !== false;
    }

    public static function getElementsTikTokFieldValues(
        Variant|Product $element
    ): mixed {
        if (isset($element->fieldLayout->tabs)) {
            if (is_array($element->fieldLayout->tabs)) {
                foreach ($element->fieldLayout->tabs as $tab) {
                    if (is_array($tab->getElements())) {
                        foreach ($tab->getElements() as $tab_element) {
                            if (method_exists($tab_element, "getField")) {
                                $field = $tab_element->getField();
                                if (
                                    get_class($tab_element->getField()) ===
                                    TikTokFields::class
                                ) {
                                    return $element->getFieldValue(
                                        $tab_element->getField()->handle
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    public static function getElementsTikTokFieldHandle(
        Variant|Product $element
    ): mixed {
        if (isset($element->fieldLayout->tabs)) {
            if (is_array($element->fieldLayout->tabs)) {
                foreach ($element->fieldLayout->tabs as $tab) {
                    if (is_array($tab->getElements())) {
                        foreach ($tab->getElements() as $tab_element) {
                            if (method_exists($tab_element, "getField")) {
                                $field = $tab_element->getField();
                                if (
                                    get_class($tab_element->getField()) ===
                                    TikTokFields::class
                                ) {
                                    return $tab_element->getField()->handle;
                                }
                            }
                        }
                    }
                }
            }
        }
        return false;
    }
}
