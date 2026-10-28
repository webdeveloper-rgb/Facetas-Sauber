# Facetas filter drawer

For PrestaShop 8 category pages. Upload the ZIP through Module Manager, install Facetas, disable the native `ps_facetedsearch` module, and clear the shop cache. Facetas works with that module uninstalled too; there are no shared indexes, imports, or runtime dependencies.

The module discovers brands, attributes and features from visible category products. Classic is supported on desktop and mobile. Other themes must expose `displayLeftColumn` or `listing.rendered_facets`; themes without Classic's `#products` container may need a placement adapter.

Filters apply by page navigation. OR applies within groups and AND between groups. Selected attribute groups must exist on the same combination. Price filtering uses the current customer's default-combination listing price, not a selected variant's price. Stock means positive physical stock; backordering does not count.

Version 1 covers category pages only. Search, brand, supplier and promotional listing pages retain the core behavior. The styled sample storefront is a separate preview, not an included theme.

This provider reads the complete category into request memory to retain PrestaShop's customer-specific price calculations. Profile large catalogs before deployment; an indexed architecture is needed for very large categories. It creates no database tables and has no scheduled jobs.

Tested on PrestaShop 8.2.1, PHP 8.1.34, Classic, with the native module disabled. Other 8.x versions and third-party themes require staging validation.


## Version 1.1 — automatic organization

Brand, attributes, then features appear in a stable order; native attribute/feature positions are respected. Attribute values follow configured positions, while feature values sort naturally and numerically within identical units. Filters that cannot narrow the category are suppressed, while sparse features, multiple combination choices and active filters stay accessible.

Configure the module in Module Manager to override each group's visibility and optional priority (0–9999). Leave rules on Automatic for newly discovered catalog data. Settings follow the current PrestaShop shop context. There is no external API connection or key to configure.

Long option lists show seven options plus selected values, with accent-insensitive search and Show all/Show fewer controls. Existing filter URLs and product matching continue to work. Clear PrestaShop caches after replacing files during an update. No reinstall is needed.
