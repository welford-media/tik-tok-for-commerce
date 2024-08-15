<?php
namespace WelfordMedia\CraftTikTok\services;

use Craft;
use craft\helpers\App;
use yii\base\Component;
use EcomPHP\TiktokShop\Client;
use WelfordMedia\CraftTikTok\TikTok;
use craft\base\Model;
use craft\commerce\elements\Variant;
use craft\commerce\elements\Product;
use EcomPHP\TiktokShop\Webhook;
use craft\elements\Asset;
use WelfordMedia\CraftTikTok\jobs\SyncProduct;
use craft\commerce\Plugin as Commerce;

class TikTokService extends Component
{
    private Client $client;
    private Model $settings;
    private string $currency;

    public function __construct()
    {
        $paymentCurrenciesService = Commerce::getInstance()->paymentCurrencies;
        $this->currency = $paymentCurrenciesService->primaryPaymentCurrencyIso;
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

    // Authentication Methods

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

        $this->updateAccessToken(
            $token["access_token"],
            $token["refresh_token"]
        );
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

        $this->updateAccessToken(
            $token["access_token"],
            $token["refresh_token"]
        );
        $this->getShops();
    }

    private function updateAccessToken(
        string $accessToken,
        string $refreshToken
    ): void {
        $this->settings->access_token = $accessToken;
        $this->settings->refresh_token = $refreshToken;
        if (!empty($this->settings->access_token)) {
            $this->client->setAccessToken($this->settings->access_token);
        }
        $this->saveSettings();
    }

    // Product Methods

    public function syncAllProducts(): bool
    {
        $this->checkAuthenticated();
        $this->checkShopCipher();

        $variants = Variant::find()->tiktokSync(1)->all();
        foreach ($variants as $variant) {
            Craft::$app->queue->push(new SyncProduct(["id" => $variant->id]));
        }

        return true;
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

        $tiktokProductId = $mapping_service->getVariantMapping($variant->id);
        $category_mapping =
            $variant->tiktokCategory ?? ($product->tiktokCategory ?? null);

        $this->validateSyncProduct($variant, $product, $category_mapping);

        $description = $this->getDescription($product, $variant);
        $imageAsset = $this->getImageAsset($variant, $product);
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
                    [
                        "warehouse_id" => $warehouse,
                        "quantity" => $variant->stock,
                    ],
                ],
                "seller_sku" => (string) $variant->sku,
                "price" => [
                    "amount" => (string) $variant->price,
                    "currency" => $this->currency,
                ],
            ],
        ];

        $jsonData = [
            "title" => $variant->title ?? $product->title,
            "save_mode" => $this->settings->draft_mode ? "AS_DRAFT" : "LISTING",
            "description" => "<p>" . $description . "</p>",
            "category_id" => (string) $category_mapping,
            "main_images" => [["uri" => $image_response["uri"]]],
            "skus" => $skus,
            "package_dimensions" => [
                "length" => (string) $variant->length,
                "width" => (string) $variant->width,
                "height" => (string) $variant->height,
                "unit" => "CENTIMETER",
            ],
            "package_weight" => [
                "value" => (string) $variant->weight,
                "unit" => "KILOGRAM",
            ],
        ];

        $response = empty($tiktokProductId)
            ? $this->client->Product->createProduct($jsonData)
            : $this->client->Product->editProduct($tiktokProductId, $jsonData);

        if (isset($response) && !empty($response["product_id"])) {
            $mapping_service->saveVariantMapping(
                $variant->id,
                $response["product_id"]
            );
        }

        return true;
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

    // Order Methods

    public function getOrder(int $id): array|null
    {
        $this->checkAuthenticated();
        $this->checkShopCipher();

        $orders = $this->client->Order->getOrderDetail([$id]);
        return !empty($orders) && is_array($orders) ? $orders[0] : null;
    }

    // Auxiliary Methods

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

        Craft::debug(print_r($this->settings->shops, true), __METHOD__);

        if (
            is_array($this->settings->shops) &&
            count($this->settings->shops) > 0
        ) {
            $this->settings->shop_cipher = $this->settings->shops[0]["value"];
        }

        $this->saveSettings();
    }

    // Helper Methods

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

    private function validateSyncProduct(
        Variant $variant,
        Product $product,
        string|int $category_mapping
    ): void {
        if (empty($category_mapping)) {
            throw new \Exception(
                "No TikTok category selected for the product type. You must setup a TikTok Category product field with the handle tiktokCategory for the product type."
            );
        }

        if ($this->isDescriptionEmpty($variant, $product)) {
            throw new \Exception(
                "Description is required for the variant or the product. You must setup a single line text field with the handle tiktokDescription for your product and/or variant"
            );
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
    }

    private function isDescriptionEmpty(
        Variant $variant,
        Product $product
    ): bool {
        return empty($product->tiktokDescription) &&
            empty($variant->tiktokDescription);
    }

    private function getDescription(Product $product, Variant $variant): string
    {
        $description = array_filter([
            $product->tiktokDescription ?? null,
            $variant->tiktokDescription ?? null,
        ]);

        return !empty($description) ? implode("</p><p>", $description) : "";
    }

    private function getImageAsset(Variant $variant, Product $product): ?Asset
    {
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
                    return $image;
                }
            }
        }

        throw new \Exception(
            "No valid image found for the variant or product. You must setup an asset field with the handle tiktokImage on your product or variant, ensuring that only JPG or PNG is allowed."
        );
    }
}
