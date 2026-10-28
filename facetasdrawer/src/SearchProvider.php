<?php
namespace FacetasDrawer;

use PrestaShop\PrestaShop\Core\Product\Search\FacetsRendererInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrdersCollection;

final class SearchProvider implements ProductSearchProviderInterface, FacetsRendererInterface
{
    private $module;
    private $config = [];

    public function __construct(\FacetasDrawer $module)
    {
        $this->module = $module;
    }

    public function runQuery(ProductSearchContext $searchContext, ProductSearchQuery $query)
    {
        $context = \Context::getContext();
        $category = new \Category($query->getIdCategory(), $searchContext->getIdLang(), $context->shop->id);
        $result = new ProductSearchResult();
        $state = FilterEngine::decode($query->getEncodedFacets());
        $priceVisible = !(\Configuration::get('PS_CATALOG_MODE') && !\Configuration::get('PS_CATALOG_MODE_WITH_PRICES'));
        if (!$priceVisible) {
            $state['min'] = $state['max'] = null;
        }
        $rows = [];
        // Core supplies access checks, shop scope, visibility, tax and customer prices.
        if (\Validate::isLoadedObject($category) && $category->active && $category->checkAccess($context->customer->id)) {
            $total = (int) $category->getProducts($searchContext->getIdLang(), 1, 1, 'position', 'ASC', true);
            if ($total) {
                $rows = $category->getProducts($searchContext->getIdLang(), 1, $total, 'position', 'ASC') ?: [];
            }
        }
        $groups = [];
        $products = [];
        foreach ($rows as $position => $row) {
            $id = (int) $row['id_product'];
            $products[$id] = [
                'id' => $id, 'price' => round((float) (\Product::getTaxCalculationMethod() === PS_TAX_EXC ? $row['price_tax_exc'] : $row['price']), (int) $context->currency->precision),
                'quantity' => (int) \StockAvailable::getQuantityAvailableByProduct($id, 0, $context->shop->id),
                'groups' => [], 'variants' => [], 'row' => $row, 'position' => $position,
            ];
            if (!empty($row['id_manufacturer'])) {
                $this->addOption($groups, 'brand', $this->module->l('Brand'), $row['id_manufacturer'], $row['manufacturer_name']);
                $products[$id]['groups']['brand'] = [(string) $row['id_manufacturer']];
            }
        }
        $this->loadMetadata($products, $groups, (int) $searchContext->getIdLang(), (int) $context->shop->id);
        $groups = FacetOrganizer::organize($products, $groups, $state, $this->module->getFilterSettings(), $context->language->locale);
        // Hidden groups keep their metadata for existing filter chips and URLs.
        $groups = array_column($groups, null, 'id');
        $filtered = FilterEngine::filter($products, $state);
        $sort = $query->getSortOrder();
        if (!$priceVisible && $sort->getField() === 'price') {
            $sort = new \PrestaShop\PrestaShop\Core\Product\Search\SortOrder('product', 'position', 'asc');
        }
        $field = $sort->getField();
        $direction = strtolower($sort->getDirection()) === 'desc' ? -1 : 1;
        usort($filtered, function ($a, $b) use ($field, $direction) {
            if ($field === 'price') {
                $compare = $a['price'] <=> $b['price'];
            } elseif ($field === 'name') {
                $compare = strnatcasecmp($a['row']['name'], $b['row']['name']);
            } elseif ($field === 'date_add' || $field === 'date_upd') {
                $compare = strcmp($a['row'][$field], $b['row'][$field]);
            } elseif ($field === 'id_product') {
                $compare = $a['id'] <=> $b['id'];
            } else {
                $compare = $a['position'] <=> $b['position'];
            }
            return ($compare ?: ($a['id'] <=> $b['id'])) * $direction;
        });
        $perPage = max(1, (int) $query->getResultsPerPage());
        $page = max(1, (int) $query->getPage());
        $result->setProducts(array_column(array_slice($filtered, ($page - 1) * $perPage, $perPage), 'row'));
        $result->setTotalProductsCount(count($filtered));
        $result->setAvailableSortOrders((new SortOrdersCollection($context->getTranslator()))->getDefaults());
        $result->setCurrentSortOrder($sort);
        $result->setEncodedFacets(FilterEngine::encode($state));
        $this->config = [
            'state' => $state, 'groups' => FilterEngine::counts($products, $state, $groups),
            'total' => count($filtered), 'priceVisible' => $priceVisible,
            'currency' => $context->currency->iso_code, 'locale' => $context->language->locale,
            'bounds' => ['min' => 0, 'max' => $products && $priceVisible ? ceil(max(array_column($products, 'price'))) : 0],
        ];
        return $result;
    }

    private function addOption(array &$groups, $key, $label, $id, $name, $color = '', $position = 0, $optionPosition = 0)
    {
        if (!isset($groups[$key])) {
            $groups[$key] = ['id' => $key, 'label' => $label, 'position' => (int) $position, 'options' => []];
        }
        $groups[$key]['options'][(string) $id] = ['id' => (string) $id, 'label' => $name, 'position' => (int) $optionPosition, 'color' => preg_match('/^#[a-fA-F0-9]{6}$/D', $color) ? $color : ''];
    }

    private function loadMetadata(array &$products, array &$groups, $lang, $shop)
    {
        foreach (array_chunk(array_keys($products), 400) as $ids) {
            $in = implode(',', array_map('intval', $ids));
            $prefix = _DB_PREFIX_;
            $features = \Db::getInstance()->executeS('SELECT fp.id_product, fp.id_feature, fp.id_feature_value, fl.name, fvl.value, f.position
                FROM `' . $prefix . 'feature_product` fp
                INNER JOIN `' . $prefix . 'feature` f ON f.id_feature=fp.id_feature
                INNER JOIN `' . $prefix . 'feature_shop` fs ON fs.id_feature=f.id_feature AND fs.id_shop=' . $shop . '
                INNER JOIN `' . $prefix . 'feature_lang` fl ON fl.id_feature=fp.id_feature AND fl.id_lang=' . $lang . '
                INNER JOIN `' . $prefix . 'feature_value_lang` fvl ON fvl.id_feature_value=fp.id_feature_value AND fvl.id_lang=' . $lang . '
                WHERE fp.id_product IN (' . $in . ') ORDER BY fp.id_feature, fvl.value') ?: [];
            foreach ($features as $row) {
                $key = 'f' . $row['id_feature'];
                $this->addOption($groups, $key, $row['name'], $row['id_feature_value'], $row['value'], '', $row['position']);
                $products[$row['id_product']]['groups'][$key][] = (string) $row['id_feature_value'];
            }
            $attributes = \Db::getInstance()->executeS('SELECT pa.id_product, pa.id_product_attribute, a.id_attribute, a.id_attribute_group, a.color, al.name, agl.public_name, ag.position AS group_position, a.position AS option_position
                FROM `' . $prefix . 'product_attribute` pa
                INNER JOIN `' . $prefix . 'product_attribute_shop` pas ON pas.id_product_attribute=pa.id_product_attribute AND pas.id_shop=' . $shop . '
                INNER JOIN `' . $prefix . 'product_attribute_combination` pac ON pac.id_product_attribute=pa.id_product_attribute
                INNER JOIN `' . $prefix . 'attribute` a ON a.id_attribute=pac.id_attribute
                INNER JOIN `' . $prefix . 'attribute_group` ag ON ag.id_attribute_group=a.id_attribute_group
                INNER JOIN `' . $prefix . 'attribute_group_shop` ags ON ags.id_attribute_group=ag.id_attribute_group AND ags.id_shop=' . $shop . '
                INNER JOIN `' . $prefix . 'attribute_lang` al ON al.id_attribute=a.id_attribute AND al.id_lang=' . $lang . '
                INNER JOIN `' . $prefix . 'attribute_group_lang` agl ON agl.id_attribute_group=a.id_attribute_group AND agl.id_lang=' . $lang . '
                WHERE pa.id_product IN (' . $in . ') ORDER BY a.id_attribute_group, al.name') ?: [];
            foreach ($attributes as $row) {
                $key = 'a' . $row['id_attribute_group'];
                $this->addOption($groups, $key, $row['public_name'], $row['id_attribute'], $row['name'], $row['color'], $row['group_position'], $row['option_position']);
                $product = &$products[$row['id_product']];
                $variantId = (int) $row['id_product_attribute'];
                if (!isset($product['variants'][$variantId])) {
                    $product['variants'][$variantId] = ['attributes' => [], 'quantity' => (int) \StockAvailable::getQuantityAvailableByProduct($row['id_product'], $variantId, $shop)];
                }
                $product['variants'][$variantId]['attributes'][] = (string) $row['id_attribute'];
                unset($product);
            }
        }
        foreach ($groups as &$group) {
            $group['options'] = array_values($group['options']);
        }
        unset($group);
    }

    public function renderFacets(ProductSearchContext $context, ProductSearchResult $result)
    {
        return $this->module->renderDrawer($this->config);
    }

    public function renderActiveFilters(ProductSearchContext $context, ProductSearchResult $result)
    {
        return '';
    }
}
