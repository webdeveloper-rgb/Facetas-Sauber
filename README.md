# Facetas — independent filter drawer for PrestaShop 8

An installable PrestaShop module and an interactive storefront preview. The module provides **category-page** faceted search using PrestaShop core APIs. It does not load `ps_facetedsearch`, use its indexes, or require it to be installed.

## Install

1. Build the ZIP with `python3 scripts/package.py` (Python 3, no packages required).
2. In PrestaShop 8, open **Modules → Module Manager → Upload a module**, then upload `facetasdrawer.zip`.
3. Disable the native **Faceted search** (`ps_facetedsearch`) module. PrestaShop uses the first matching search provider; running two providers can select the wrong one. Facetas does not silently disable another module.
4. Clear the PrestaShop cache under **Advanced Parameters → Performance**.
5. Open a category with products. The module mounts through `displayLeftColumn`; on Classic, JavaScript places the trigger above the product listing so it is accessible on desktop, mobile and empty results.

Alternatively copy `facetasdrawer/` into your shop's `modules/` directory and install it in Module Manager. No database migrations, external services, credentials, npm packages, or index jobs are required.

## Automatic organization (1.1)

Facetas reads the shop's own catalog. The webservice resources `product_features`, `product_feature_values` and `manufacturers` describe the same metadata; the installed module also reads product relationships directly, so no webservice key or external network connection is needed.

- Automatic group order: brand, attribute groups, features. Attribute groups and features follow their native PrestaShop positions, independently of database IDs and query chunks.
- Attribute options follow their configured positions (for example S, M, L, XL). Feature values use locale-aware natural order when PHP Intl is available, with natural alphabetical fallback. Decimal numbers with identical unit suffixes are sorted numerically (`2 kg`, `2.5 kg`, `10 kg`). Decimal commas are accepted; units are not converted and values are not merged.
- Uniform feature/brand groups that cannot narrow the complete category are hidden. A single value assigned to only some products remains useful and visible. Multiple attribute options stay available because they can narrow combinations or stock even when all products offer each option.
- Selected groups always stay visible and removable, even if an administrator later hides them. The product-matching rules and existing `fd1` URLs are preserved.
- Long lists initially show seven options, plus any selected options. Search ignores letter accents; Show all/Show fewer controls the rest. Active groups open automatically.
- Assets carry the module version to refresh older browser caches after upgrades.

### Settings

Open **Module Manager → Facetas → Configure**. The page automatically lists brands, attributes and features, including newly created ones. Choose Automatic, Always show or Hide for each group. An optional priority from 0–9999 places that group before automatic groups; smaller numbers come first. Blank priority returns to native order. A global setting can show all discovered filters. Settings use PrestaShop's current shop configuration context, with no custom tables. Form submission validates the admin token and module configure permission.

Copying the updated module files over an existing installation is sufficient for these new defaults; no reinstall or data migration is needed. Clear PrestaShop caches after updating, especially if theme asset combination is enabled. Custom themes and every PrestaShop 8 minor release still need staging validation.

## Included

- Responsive left-side drawer with native modal semantics, focus containment, Escape/backdrop dismissal, focus restoration, and reduced-motion support.
- Brands, product features, and attributes discovered from the current category's visible products. Hex-color attributes have swatches; texture attributes retain their text labels.
- OR within a group and AND between groups. Attribute groups must match the same real combination.
- Price range in the customer's currency and tax display mode, based on the product listing/default combination price. Selecting a variant does not change which price is filtered.
- In-stock filtering; when attributes are selected, the matching combination must have positive stock. This means physical stock, not permission to backorder.
- Option counts honor other active groups and exclude the current group. Counts are from the last applied request and are hidden while editing a draft.
- Draft/apply/cancel, clear-all, removable chips, searchable long option lists, and shareable `q=fd1.…` URLs.
- Native category sorting and pagination. Applying filters resets the page. No legacy module URL compatibility is claimed.
- Shop, language, category access, visibility, prices and stock come from PrestaShop core. There is no cross-customer cache.

## Scope and limits

- Version 1 handles **category listings**. Search results, manufacturer listings, supplier listings and promotional pages keep their core providers and do not gain this drawer. Category membership follows the shop's existing category assignments; it does not recursively include children.
- The storefront in `preview/` demonstrates the same drawer JS/CSS with synthetic products. It is **not a complete PrestaShop theme**. Installing the module preserves your existing storefront design.
- Classic is the integration target. Custom themes must render the `displayLeftColumn` hook or `listing.rendered_facets`. The Classic adapter moves the trigger into `#products`; themes with different markup may need an adapter. The module does not modify theme layouts or remove an otherwise empty sidebar.
- Filtering applies with a regular category-page navigation, so it works independently of the native faceted-search JavaScript. Classic sort/pagination updates are supported by re-mounting the drawer after replacement of the facets container.
- This first implementation evaluates the category's catalog in request memory using core-computed customer prices. It favors correct pricing without maintaining an index. Very large categories require profiling and an indexed provider before production deployment. No large-catalog performance guarantee is made.
- Facets need JavaScript. Without it the normal product listing remains available.
- Catalog mode with hidden prices suppresses price controls and ignores incoming price filters.
- The linked Dribbble page was blocked in the development environment. This is an original visual direction based on the requested drawer concept; an exact reference match needs a supplied screenshot.

## Preview

```sh
python3 -m http.server 8080 --bind 0.0.0.0
```

Open `http://localhost:8080/preview/`. The preview includes working filters, category tabs, sorting, search, product details and saved pieces (local browser storage). It uses local sample photos from Unsplash and fonts from Google Fonts with system fallbacks. See `preview/assets/SOURCES.md` for image sources.

## Verify

```sh
php tests/filter-engine.php
php tests/facet-organizer.php
find facetasdrawer -name '*.php' -print0 | xargs -0 -n1 php -l
node --check facetasdrawer/views/js/drawer.js
node --check preview/catalog.js
node --check preview/preview.js
python3 scripts/package.py
```

Use PHP supported by your PrestaShop 8 release for installation. The standalone engine tests have no PrestaShop dependencies. They cover malformed input, selection semantics, combination consistency, stock, price ranges and facet counts.

## Structure

- `facetasdrawer/facetasdrawer.php`: module lifecycle, hooks, assets and localized drawer configuration.
- `facetasdrawer/src/SearchProvider.php`: PrestaShop category integration and metadata.
- `facetasdrawer/src/FacetOrganizer.php`: native ordering, useful group detection and settings normalization.
- `facetasdrawer/src/FilterConfiguration.php`: module configuration form and validation.
- `facetasdrawer/src/FilterEngine.php`: independent request parser, matcher and facet counts.
- `facetasdrawer/views/`: shared responsive UI assets and Smarty mount template.
- `preview/`: storefront demonstration; excluded from the installable ZIP.
- `tests/`: focused behavior checks.

Integration contracts were checked against the official [PrestaShop 8.2 category provider](https://github.com/PrestaShop/PrestaShop/blob/8.2.x/src/Adapter/Category/CategoryProductSearchProvider.php), [listing controller](https://github.com/PrestaShop/PrestaShop/blob/8.2.x/classes/controller/ProductListingFrontController.php), and [facets renderer interface](https://github.com/PrestaShop/PrestaShop/blob/8.2.x/src/Core/Product/Search/FacetsRendererInterface.php).

### Real PrestaShop integration check

Validated on PrestaShop **8.2.1 / PHP 8.1.34 / Classic**, with `ps_facetedsearch` disabled. The suite checks installation status, core pricing, filtering, pagination, facet rendering, category group access, inactive shop products and hidden-price catalog mode. Browser checks additionally cover Classic AJAX sorting, mobile empty-result recovery and keyboard focus.

For a **fresh isolated demo shop** containing the default Clothes category and sample products:

```sh
php tests/prestashop-integration.php /absolute/path/to/prestashop --isolated-demo
php tests/prestashop-configuration.php /absolute/path/to/prestashop --isolated-demo
```

The test temporarily changes demo access and configuration, restoring them in `finally` blocks. Never run it against a production shop. Full compatibility across every PrestaShop 8 minor release and custom theme has not been verified.
