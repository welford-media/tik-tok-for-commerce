<?php
namespace WelfordMedia\CraftTikTok\services;

use Craft;
use WelfordMedia\CraftTikTok\helpers\CommerceHelpers;
use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use yii\base\Component;
use WelfordMedia\CraftTikTok\TikTok;
use craft\commerce\elements\Order;

class CommerceService extends Component
{
    public function processOrder(int $id): void
    {
        $tiktok = TikTok::getInstance()->tiktok;
        $mapping = TikTok::getInstance()->mapping;

        $order_detail = $tiktok->getOrder($id);
        if (!isset($order_detail) || !is_array($order_detail)) {
            throw new \Exception(
                "Unable to retrieve order details for TikTok order ID: $id"
            );
        }

        $handle = CommerceHelpers::getElementsTikTokOrderIdHandle(new Order());
        $commerceOrder = Order::find()
            ->where([$handle => $order_detail["order_id"]])
            ->one();

        if (!$commerceOrder) {
            $commerceOrder = new Order();
            $commerceOrder->setFieldValue($handle, $order_detail["order_id"]);
            Craft::$app->getElements()->saveElement($commerceOrder);
            foreach ($order_detail["line_items"] as $line) {
                $variant_id = $mapping->getTikTokProductMapping(
                    $line["product_id"]
                );
                if (!$variant_id) {
                    return;
                }
                $variant = Variant::find()->id($variant_id)->one();
                if (!$variant) {
                    return;
                }
                $lineItem = new LineItem();
                $lineItem->purchasable = $variant;
                $lineItem->qty = 1;
                $lineItem->price = $line["sale_price"];
                $lineItem->salePrice = $line["sale_price"];
                $lineItem->saleAmount = $line["sale_price"];
                $lineItem->subtotal = $line["sale_price"];
                $commerceOrder->addLineItem($lineItem);
            }
            Craft::$app->getElements()->saveElement($commerceOrder);
        }

        switch ($order_detail["status"]) {
            case "UNPAID":
            case "ON_HOLD":
                break;
            default:
                $address = $this->createAddressFromOrderDetail($order_detail);
                Craft::$app->getElements()->saveElement($address);
                $commerceOrder->setBillingAddress($address);
                $commerceOrder->setShippingAddress($address);
                if ($commerceOrder->datePaid === null) {
                    $commerceOrder->datePaid = new \DateTime();
                }
                Craft::$app->getElements()->saveElement($commerceOrder, false);
                $commerceOrder->markAsComplete();
                break;
        }
    }

    /**
     * @param array $order_detail
     * @return \craft\elements\Address
     */
    private function createAddressFromOrderDetail(
        array $order_detail
    ): \craft\elements\Address {
        $address = new \craft\elements\Address();
        $address->addressLine1 =
            $order_detail["recipient_address"]["address_line1"];
        $address->addressLine2 =
            $order_detail["recipient_address"]["address_line2"];

        if (
            isset($order_detail["recipient_address"]["district_info"]) &&
            is_array($order_detail["recipient_address"]["district_info"])
        ) {
            foreach (
                $order_detail["recipient_address"]["district_info"]
                as $info_level
            ) {
                if (
                    isset($info_level["address_level_name"]) &&
                    !empty($info_level["address_level_name"])
                ) {
                    switch ($info_level["address_level_name"]) {
                        case "city":
                        case "district":
                        case "county":
                        case "state":
                            $address->administrativeArea .=
                                $info_level["address_name"] . ", ";
                            break;
                    }
                }
            }
        }

        $address->administrativeArea = rtrim(
            $address->administrativeArea,
            ", "
        );
        $address->postalCode = $order_detail["postal_code"];
        $address->countryCode = $order_detail["region_code"];

        return $address;
    }
}
