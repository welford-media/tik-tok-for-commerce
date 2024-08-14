<?php
namespace WelfordMedia\CraftTikTok\services;

use Craft;
use craft\helpers\App;
use yii\base\Component;
use EcomPHP\TiktokShop\Client;
use WelfordMedia\CraftTikTok\TikTok;
use craft\base\Model;
use craft\commerce\elements\Variant;
use EcomPHP\TiktokShop\Webhook;
use craft\elements\Asset;
use WelfordMedia\CraftTikTok\jobs\SyncProduct;

class TikTokService extends Component
{
    private Client $client;
    public Model $settings;

    public function __construct()
    {
        $this->settings = TikTok::getInstance()->getSettings();
        $this->client = new Client(
            App::parseEnv($this->settings->app_key),
            App::parseEnv($this->settings->app_secret)
        );
        if (!empty($this->settings->access_token)) {
            $this->client->setAccessToken($this->settings->access_token);
        }
        if (!empty($this->settings->shop_cipher)) {
            $this->client->setShopCipher($this->settings->shop_cipher);
        }
    }

    public function startAuthRequest(): string|null
    {
        $session = Craft::$app->session;
        $state = bin2hex(random_bytes(40));
        $session->set("tiktok_state", $state);
        $response = $this->client->auth()->createAuthRequest($state);
        return $response;
    }

    public function authRequestCallback(): void
    {
        $request = Craft::$app->request;
        $auth_code = $request->get("code");
        if (empty($auth_code)) {
            throw new \Exception("Auth code was not provided");
        }
        $token = $this->client->auth()->getToken($auth_code);
        if (
            empty($token) ||
            empty($token["access_token"]) ||
            empty($token["refresh_token"])
        ) {
            throw new \Exception("Failed to get access token");
        }
        $this->settings->access_token = $token["access_token"];
        $this->settings->refresh_token = $token["refresh_token"];
        if (!empty($this->settings->access_token)) {
            $this->client->setAccessToken($this->settings->access_token);
        }
        $this->saveSettings();
        $this->getShops();
    }

    public function refreshAccessToken(): void
    {
        $token = $this->client
            ->auth()
            ->refreshNewToken($this->settings->refresh_token);
        if (
            empty($token) ||
            empty($token["access_token"]) ||
            empty($token["refresh_token"])
        ) {
            throw new \Exception("Failed to refresh access token");
        }
        $this->settings->access_token = $token["access_token"];
        $this->settings->refresh_token = $token["refresh_token"];
        if (!empty($this->settings->access_token)) {
            $this->client->setAccessToken($this->settings->access_token);
        }
        $this->saveSettings();
        $this->getShops();
    }

    private function getShops(): void
    {
        $data = $this->client->Authorization->getAuthorizedShop();
        if (isset($data["shops"]) && is_array($data["shops"])) {
            foreach ($data["shops"] as $shop) {
                $this->settings->shops[] = [
                    "label" => $shop["name"],
                    "value" => $shop["cipher"],
                ];
            }
        }
        if (
            is_array($this->settings->shops) &&
            count($this->settings->shops) > 0
        ) {
            $this->settings->shop_cipher = $this->settings->shops[0]["value"];
        }
        $this->saveSettings();
    }

    private function saveSettings(): void
    {
        $plugin_service = Craft::$app->plugins;
        $plugin_service->savePluginSettings(
            TikTok::getInstance(),
            $this->settings->toArray()
        );
    }

    private function checkAuthenticated(): void
    {
        if (
            empty($this->settings->access_token) ||
            empty($this->settings->refresh_token)
        ) {
            throw new \Exception(
                "Please authorize the plugin before making this request"
            );
        }
        $this->refreshAccessToken();
    }

    private function checkShopCipher(): void
    {
        if (empty($this->settings->shop_cipher)) {
            throw new \Exception(
                "Please select a shop using the plugin settings"
            );
        }
    }

    private function checkAssetIsImage(Asset $asset): bool
    {
        $mimeType = $asset->mimeType;
        if (!$mimeType) {
            switch ($asset->extension) {
                case "jpg":
                case "jpeg":
                case "png":
                    $mimeType = "image/" . $asset->extension;
                default:
                    return false;
            }
        }

        return strpos($mimeType, "image") !== false;
    }

    public function verifyWebhook(): array|string
    {
        try {
            $this->checkAuthenticated();
            $this->checkShopCipher();
            $webhook = new Webhook($this->client);
            $request = Craft::$app->request;
            $data = $request->getRawBody();
            $webhook->verify();
            $webhook->capture($data);
            return $webhook->getData();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function getWarehouses(): array
    {
        $this->checkAuthenticated();
        $this->checkShopCipher();

        return $this->client->Logistic->getWarehouseList();
    }

    public function getCategoryList(): array
    {
        $this->checkAuthenticated();
        $this->checkShopCipher();

        return $this->client->Product->getCategories();
    }

    public function syncAllProducts(): bool
    {
        $this->checkAuthenticated();
        $this->checkShopCipher();

        $variants = Variant::find()->tiktokSync(1)->all();

        $results = [];
        foreach ($variants as $variant) {
            Craft::$app->queue->push(new SyncProduct(["id" => $variant->id]));
        }

        return $results;
    }

    public function syncProduct(int $variantId, bool $skipAuth = false): bool
    {
        if (!$skipAuth) {
            $this->checkAuthenticated();
            $this->checkShopCipher();
        }

        $variant = Variant::find()->id($variantId)->one();

        if (!$variant) {
            throw new \Exception("Variant not found");
        }

        $mapping_service = TikTok::getInstance()->mapping;

        $product = $variant->getProduct();
        $product_type_handle = $product->type->handle;

        $tiktokProductId = $mapping_service->getVariantMapping($variant->id);

        $category_mapping =
            $variant->tiktokCategory ?? ($product->tiktokCategory ?? null);

        if (empty($category_mapping)) {
            throw new \Exception(
                "No TikTok category selected for the product type. You must setup a TikTok Category product field with the handle tiktokCategory for the product type."
            );
        }

        $description = [
            $product->tiktokDescription ?? null,
            $variant->tiktokDescription ?? null,
        ];
        $description = array_filter($description);
        if (empty($description)) {
            throw new \Exception(
                "Description is required for the variant or the product. You must setup a single line text field with the handle tiktokDescription for your product and/or variant"
            );
        } else {
            $description = implode("</p><p>", $description);
        }

        if (empty($variant->weight)) {
            throw new \Exception("Weight is required for the variant.");
        }

        if (
            empty($variant->length) ||
            empty($variant->width) ||
            empty($variant->height)
        ) {
            throw new \Exception("Dimensions are required for the variant.");
        }

        $imageAsset = null;
        $images = [
            $variant->tiktokImage ?? null,
            $product->tiktokImage ?? null,
        ];
        Craft::debug("Images: " . print_r($images, true), "tiktok");

        foreach ($images as $image_query) {
            if ($image_query !== null) {
                $image = $image_query->one();
                if (
                    $image instanceof Asset &&
                    $this->checkAssetIsImage($image)
                ) {
                    $imageAsset = $image;
                    break;
                }
            }
        }

        if (!$imageAsset) {
            throw new \Exception(
                "No valid image found for the variant or product. You must setup an asset field with the handle tiktokImage on your product or variant, ensuring that only JPG or PNG is allowed."
            );
        }

        $warehouse =
            $variant->tiktokWarehouse ?? ($product->tiktokWarehouse ?? null);

        if (!$warehouse) {
            throw new \Exception(
                "No warehouse selected for the variant or product. You must setup a TikTok Warehouse field with the handle tiktokWarehouse on your product or variant and ensure that you select the correct warehouse for the product or variant."
            );
        }

        $stream = $imageAsset->getStream();
        $image_response = $this->client->Product->uploadProductImage($stream);
        if (
            empty($image_response) ||
            !is_array($image_response) ||
            empty($image_response["uri"])
        ) {
            throw new \Exception("Failed to upload image");
        }

        $skus = [
            [
                "inventory" => [
                    "warehouse_id" => $warehouse,
                    "quantity" => $variant->stock ?? 999999,
                ],
                "seller_sku" => $variant->sku || $variant->id,
                "price" => $variant->price,
            ],
        ];

        $jsonData = [
            "save_mode" => $this->settings->draft_mode ? "AS_DRAFT" : "LISTING",
            "description" => "<p>" . $description . "</p>",
            "category_id" => (string) $category_mapping,
            "main_images" => [["uri" => $image_response["uri"]]],
            "skus" => $skus,
            "package_dimensions" => [
                "length" => $variant->length,
                "width" => $variant->width,
                "height" => $variant->height,
                "unit" => "CENTIMETER",
            ],
            "package_weight" => [
                "value" => $variant->weight,
                "unit" => "KILOGRAM",
            ],
        ];

        $repsonse = null;
        if (empty($tiktokProductId)) {
            $response = $this->client->Product->createProduct($jsonData);
        } else {
            $response = $this->client->Product->editProduct(
                $tiktokProductId,
                $jsonData
            );
        }

        if (
            isset($response) &&
            !empty($response) &&
            is_array($response) &&
            !empty($response["product_id"])
        ) {
            $mapping_service->saveVariantMapping(
                $variant->id,
                $response["product_id"]
            );
        }
    }

    public function desyncProduct(int $variantId): void
    {
        $this->checkAuthenticated();
        $this->checkShopCipher();

        $variant = Variant::find()->id($variantId)->one();

        if (!$variant) {
            throw new \Exception("Variant not found");
        }

        $mapping_service = TikTok::getInstance()->mapping;
        $tiktokProductId = $mapping_service->getVariantMapping($variant->id);

        if (empty($tiktokProductId)) {
            throw new \Exception("Variant mapping not found");
        }

        $response = $this->client->Product->deleteProducts([$tiktokProductId]);

        if (isset($response)) {
            $mapping_service->deleteVariantMapping($variant->id);
        }
    }

    public function getOrder(int $id): array|null
    {
        $this->checkAuthenticated();
        $this->checkShopCipher();

        $orders = $this->client->Order->getOrderDetail([$id]);

        if (!empty($orders) && is_array($orders)) {
            return $orders[0];
        } else {
            return null;
        }
    }
}
