# TikTok for Commerce

Provides product and order syncronisation for TikTok shops with Craft Commerce.
---
## Requirements

This plugin requires Craft CMS 4.11.0 or later, Craft Commerce and PHP 8.0.2 or later.

This plugin also requires specific configuration options within Craft Commerce:

- Weight Unit - Must be set to Kilograms.
- Dimension Units - Must be set to Centimeters.
- Product Types - Must be set to display and use Dimension and Weight fields.
- Products with Multiple Variants - Must be set to display and use Dimension and Weight fields.
- Products cannot be set to be unlimited stock.

---
## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “TikTok for Commerce”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require welford-media/craft-tik-tok-for-commerce

# tell Craft to install the plugin
./craft plugin/install tik-tok-for-commerce
```
---
## Usage

### Product Sync

First, you will need to setup a TikTok Partner account and a private developer app. For more information on how to do this, please refer to the [TikTok documentation](https://partner.tiktokshop.com/docv2/page/64f1994264ed2e0295f3d631).

When creating your TikTok app you must configure it using the following options:
- **App Name**: This can be anything you like.
- **Redirect URL**: This should be the URL of your Craft CMS site followed by `/admin/tiktok-callback`. For example, `https://example.com/admin/tiktok-callback`. If your control panel trigger is different, you should adjust this accordingly.
- **Webhook URL**: This should be the URL of your Craft CMS site followed by `/tiktok-webhook`. For example, `https://example.com/tiktok-webhook`.

After creation, you will be able to copy your App key and App secret. Copy these values as you will need them to configure the plugin.

Some additional setup is required so that the plugin can access your shop information. Click on the "Manage API" button next to Basic info and enable the following packages:

- Global Shop Information
- Logistics Basic
- Order Information
- Product Basic
- Product Delete & Recover
- Product Modify
- Shop Authorized Information

Without these packages enabled you will experience errors and the plugin will not function correctly. Please make sure they are setup correctly.

You can now configure the plugin by going to the plugin settings page and entering the App key and App secret you received when creating your TikTok app. Once you've saved the settings, click on "Connect TikTok" and follow the off site instructions to authorise the plugin to connect to your TikTok shops.

If all went well, you will receive a success message and the plugin will be authorised to connect to your TikTok shops.

Now select the shop you wish to connect to from the plugin settings page and press save.

Once you have completed the above steps, you can now configure your products to sync with your TikTok shop.

To enable a product or specific variant to sync with TikTok you must:
 - Create a new asset field with the handle `tiktokImage`. The field show only allow one image to be selected, and ensure the type is set to Image only. **TikTok only supports JPG and PNG images at this time.** Apply this field to your product or variant fields.
 - Create a new field using the custom field type `TikTok Fields`. You may name this field however you wish. This field provides the functionality required for the plugin to sync your product with TikTok. Apply this field to your product or variant fields.

 Now that your fields are complete, work through your products and variants and ensure that the TikTok Fields options are populated with the correct information. Upon saving each product, it will be automatically synced to your TikTok shop.

### Order Sync

Orders made on TikTok can be automatically sent to Craft Commerce via a webhook. To enable this feature you will need to:
- Have created your TikTok app and have provided your webhook URL as described above.
- Create a new field using the custom field type `TikTok Order ID` that is provided by the plugin. This field allows the plugin to link your Craft Commerce Order to the correct TikTok order. Apply this field to your order fields in the Commerce Order Fields settings.

TikTok provides for a number of order statuses. These will be handled within Craft Commerce as follows:
- UNPAID - These orders are synced to Craft Commerce and open orders, as though a user were browsing the site and added items to their cart but have not yet checked out.
- AWAITING_SHIPMENT, AWAITING_COLLECTION, IN_TRANSIT, DELIVERED, COMPLETED - These orders are synced to Craft Commerce and will have the isPaid and isCompleted flags set to true.

**The plugin will only store orders where the products have been synced from Craft Commerce. If an order is received for a product that has not been synced from Craft Commerce, the order will be ignored.**
