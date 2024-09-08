# Pulse - Bitcoin Lightning Network Affiliate Plugin for WooCommerce

Pulse is a WordPress plugin that enables automated affiliate payouts for WooCommerce using the Bitcoin Lightning Network and BTCPayServer. It allows store owners to easily manage affiliate programs with secure, instant, and low-cost payments.

## Features

- Affiliate signup system with encrypted Lightning addresses
- Custom affiliate link creation
- Automated affiliate payouts via BTCPayServer
- Support for both encrypted and unencrypted affiliate links
- Custom affiliate mappings for personalized affiliate codes
- Integration with WooCommerce for order tracking and commission calculation

## Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- BTCPayServer instance
- PHP 7.2+

## Installation

1. Download the Pulse plugin ZIP file.
2. Log in to your WordPress admin panel and navigate to Plugins > Add New.
3. Click "Upload Plugin" and choose the downloaded ZIP file.
4. Click "Install Now" and then "Activate Plugin".

## Configuration

1. Go to the Pulse settings page in your WordPress admin panel (Settings > Pulse).
2. Enter your BTCPayServer URL, API Key, and Store ID.
3. Set your desired commission rate.
4. Generate or enter your encryption keys for secure affiliate links.
5. Configure additional settings such as allowing unencrypted addresses and custom affiliate mappings.

### Generating BTCPayServer API Key

To generate the appropriate API key for Pulse in BTCPayServer:

1. Log in to your BTCPayServer instance.
2. Navigate to the "Settings" section.
3. Click on "Access Tokens" and then the link under "Greenfield API Keys"
4. Click on "Generate Key".
5. Set a label for your key (e.g., "Pulse Affiliate Plugin").
6. Under "Permissions", select the following:
   - `Manage your pull payments btcpay.store.canmanagepullpayments`
   - `Archive your pull payments btcpay.store.canarchivepullpayments`
   - `Create pull payments btcpay.store.cancreatepullpayments`
   - `View your pull payments btcpay.store.canviewpullpayments`
   - `Create non-approved pull payments btcpay.store.cancreatenonapprovedpullpayments`
   - `Manage payouts btcpay.store.canmanagepayouts`
   - `View payouts btcpay.store.canviewpayouts`
7. Click "Generate" to create the API key.
8. Copy the generated API key immediately, as it won't be shown again.
9. Paste this API key into the Pulse plugin settings in your WordPress admin panel.

### Finding Your BTCPayServer Store ID

To find your BTCPayServer Store ID:

1. In BTCPayServer, go to "Stores" and select the store you want to use with Pulse.
2. The Store ID is typically visible in the URL of your store's management page.
3. It can also be found at Settings > General > Store ID
4. Copy this ID and paste it into the Pulse plugin settings.

### Auto Approve Claims

Check this box if you want any affiliate payments to be marked pre-approved upon creation by the plugin and paid by BTCPayServer automatically.

If you leave this unchecked, you will need to manually approve payouts periodically. Recommending using this setting as this is beta software and could have bugs.

### Enabling Your BTCPayServer Payout Processor

To enable Lightning (off-chain) payouts:

1. In BTCPayServer, go to "Settings" then "Payout Processors".
2. Under "Automated Lightning Sender", click "Configure (Or "Modify" if already enabled).
3. Set your desired settings.
4. Save

## Usage

### For Store Owners

1. Set up your BTCPayServer instance and ensure it's properly configured.
2. Generate the API key in BTCPayServer as described above.
3. Configure the Pulse plugin settings, including the BTCPayServer URL, API Key, and Store ID.
4. Create an affiliate signup page using the `[pulse_affiliate_signup]` shortcode.
5. Monitor affiliate signups and payouts through the WooCommerce order interface.

### For Affiliates

1. Sign up using the affiliate signup form on the store's website.
2. Receive your unique affiliate link (encrypted, unencrypted, or custom, depending on store settings).
3. Share your affiliate link to promote the store's products.
4. Earn commissions on qualifying sales, paid out automatically via the Lightning Network.

## Security

Pulse uses RSA encryption to secure affiliate Lightning addresses. Store owners should ensure that their private key is kept secure and not shared publicly.

## Troubleshooting

- Ensure your BTCPayServer instance is properly configured and accessible.
- Verify that the API key has the correct permissions as listed above.
- Check that the Store ID is correct and matches the store you intend to use.
- Check the WordPress error logs for any issues related to affiliate signups or payouts.
- Verify that your WooCommerce settings are correct and that orders are being processed properly.

## Contributing

Contributions to Pulse are welcome! Please submit pull requests to the GitHub repository.

## License

Pulse is released under the Unlicense. See the LICENSE file for more details.