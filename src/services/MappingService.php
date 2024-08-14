<?php
namespace WelfordMedia\CraftTikTok\services;

use yii\base\Component;
use WelfordMedia\CraftTikTok\records\VariantMapping;

class MappingService extends Component
{
    public function getVariantMapping(int $variant_id): int|null
    {
        $record = VariantMapping::find()
            ->where(["variantId" => $variant_id])
            ->one();

        if ($record) {
            return $record->tiktokProductId;
        }

        return null;
    }

    public function getTikTokProductMapping(int $id): int|null
    {
        $record = VariantMapping::find()
            ->where(["tiktokProductId" => $id])
            ->one();

        if ($record) {
            return $record->variantId;
        }

        return null;
    }

    public function saveVariantMapping(
        int $variant_id,
        int $tiktok_product_id
    ): void {
        $record = VariantMapping::find()
            ->where(["variantId" => $variant_id])
            ->one();

        if (!$record) {
            $record = new VariantMapping();
            $record->variantId = $variant_id;
        }

        $record->tiktokProductId = $tiktok_product_id;
        $record->save();
    }

    public function deleteVariantMapping(int $id): void
    {
        $record = VariantMapping::find()
            ->where(["variantId" => $id])
            ->one();

        if ($record) {
            $record->delete();
        }
    }
}
