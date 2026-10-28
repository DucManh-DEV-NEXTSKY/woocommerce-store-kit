# Third-party notices

Store Kit's own code is GPL-2.0-or-later; see `LICENSE`. Every bundled upstream component retains its copyright, license, source references and original notices. The dependency lock records the actual official download URL, version and SHA256 used for this release; it is not a license key.

| Component | Locked version | License / source |
|---|---|---|
| WordPress | 7.1.3 | GPL-2.0-or-later; `wordpress/license.txt`, https://wordpress.org/about/license/ |
| WooCommerce | 11.2.1 | GPL-3.0-or-later; upstream license files retained inside plugin; https://github.com/woocommerce/woocommerce |
| Blocksy Free | 2.1.60 | GPL-2.0-or-later; upstream theme `style.css` and license files; https://wordpress.org/themes/blocksy/ |
| SQLite Database Integration | 3.1.2 | GPL-2.0-or-later; upstream plugin license files; https://wordpress.org/plugins/sqlite-database-integration/ |
| WP-CLI | 2.12.0 | MIT; `tools/WP-CLI-LICENSE.txt`; https://github.com/wp-cli/wp-cli |
| Bricolage Grotesque | Official source recorded alongside font files | SIL Open Font License 1.1; `components/themes/store-kit-child/assets/fonts/OFL-BricolageGrotesque.txt` and `SOURCES.txt` |
| Be Vietnam Pro | Official source recorded alongside font files | SIL Open Font License 1.1; `components/themes/store-kit-child/assets/fonts/OFL-BeVietnamPro.txt` and `SOURCES.txt` |

SQLite integration is included for isolated local previews. MySQL remains the documented hosted-store database. PHP portable is downloaded separately for Windows previews from the official PHP distribution with checksum verification; the PHP runtime itself is not inside the WordPress release archive.

The package does **not** include Blocksy Pro, DevVN premium plugins, another merchant's license keys, payment/SMTP credentials, private artwork, customer records, orders or business registration documents. Optional CSS compatibility with a third-party plugin does not grant a license to that plugin or enable its features. Install paid extensions separately using a valid entitlement for the destination store.

Upstream packages include their dependencies under the notices shipped by their respective maintainers. Preserve those notices when redistributing. Source for Store Kit and its child theme is provided in the source archive; upstream source locations and exact downloadable packages are recorded in `dependencies.lock.json`.
