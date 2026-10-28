<?php
/** Run against an isolated PrestaShop 8 demo installation, never a production database. */
if (($argv[2] ?? '') !== '--isolated-demo') {
    fwrite(STDERR, "Usage: php prestashop-integration.php /path/to/shop --isolated-demo\n");
    exit(1);
}
require rtrim($argv[1], '/') . '/config/config.inc.php';
use FacetasDrawer\FilterEngine;
use FacetasDrawer\SearchProvider;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
$tests = 0;
function verify($condition, $message) {
    global $tests;
    ++$tests;
    if (!$condition) { throw new RuntimeException($message); }
}
$module = Module::getInstanceByName('facetasdrawer');
verify($module && Module::isEnabled('facetasdrawer'), 'Module must be installed and enabled');
verify(!Module::isEnabled('ps_facetedsearch'), 'Native faceted search must be disabled for independence test');
$context = Context::getContext();
$context->currency = new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT'));
$context->country = new Country((int)Configuration::get('PS_COUNTRY_DEFAULT'));
$context->language = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
$context->customer = new Customer();
$context->cart = new Cart();
$context->controller = new CategoryController();
$searchContext = new ProductSearchContext($context);
$query = (new ProductSearchQuery())->setQueryType('category')->setIdCategory(3)->setPage(1)->setResultsPerPage(100)->setSortOrder(new SortOrder('product','price','asc'));
$provider = new SearchProvider($module);
$result = $provider->runQuery($searchContext,$query);
verify($result->getTotalProductsCount() === 2, 'Default demo clothes fixture contains two products');
verify(count($result->getProducts()) === 2, 'Both demo products returned');
$rows = $result->getProducts();
verify((float)$rows[0]['price'] <= (float)$rows[1]['price'], 'Core-computed price sort');
$price = (float)$rows[0]['price'];
$q = clone $query;
$q->setEncodedFacets(FilterEngine::encode(FilterEngine::normalize(['max'=>round($price,2)])));
$filtered = $provider->runQuery($searchContext,$q);
verify($filtered->getTotalProductsCount() === 1, 'Discounted customer listing price filtering');
verify(FilterEngine::decode($filtered->getEncodedFacets())['max'] === round($price,2), 'Filter URL survives result round trip');
$q = clone $query;
$q->setResultsPerPage(1)->setPage(2);
$page = $provider->runQuery($searchContext,$q);
verify($page->getTotalProductsCount() === 2 && count($page->getProducts()) === 1, 'Pagination keeps full count');
verify($page->getProducts()[0]['id_product'] === $rows[1]['id_product'], 'Second page uses stable sorted order');
$q = clone $query;
$q->setEncodedFacets(FilterEngine::encode(FilterEngine::normalize(['groups'=>['a2'=>['11']]])));
$filtered = $provider->runQuery($searchContext,$q);
verify($filtered->getTotalProductsCount() === 1, 'Black attribute filter');
$html = $provider->renderFacets($searchContext,$filtered);
verify(strpos($html,'data-fd-config=') !== false, 'Smarty renderer emits mount');
preg_match('/data-fd-config="([^"]+)"/', $html, $matches);
$config = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true);
verify($config['state']['groups']['a2'] === ['11'], 'Smarty data preserves attribute IDs');
verify(count($config['groups']) >= 5, 'Brand, features and attributes discovered');
$q = clone $query;
$q->setIdCategory(999999);
verify($provider->runQuery($searchContext,$q)->getTotalProductsCount() === 0, 'Nonexistent categories return no products');
// Transactional fixture mutations verify boundaries, then restore demo data.
$db = Db::getInstance();
$db->execute('START TRANSACTION');
try {
    $db->execute('DELETE FROM `'._DB_PREFIX_.'category_group` WHERE id_category=3');
    Cache::clean('Category::checkAccess_*');
    verify($provider->runQuery($searchContext,$query)->getTotalProductsCount() === 0, 'Restricted category does not leak products or facets');
} finally {
    $db->execute('ROLLBACK');
    Cache::clean('Category::checkAccess_*');
}
$db->execute('START TRANSACTION');
try {
    $db->execute('UPDATE `'._DB_PREFIX_.'product_shop` SET active=0 WHERE id_product='.(int)$rows[0]['id_product'].' AND id_shop='.(int)$context->shop->id);
    verify($provider->runQuery($searchContext,$query)->getTotalProductsCount() === 1, 'Shop inactive products excluded');
} finally {
    $db->execute('ROLLBACK');
}
$catalogMode = Configuration::get('PS_CATALOG_MODE');
$catalogPrices = Configuration::get('PS_CATALOG_MODE_WITH_PRICES');
try {
    Configuration::updateValue('PS_CATALOG_MODE',1);
    Configuration::updateValue('PS_CATALOG_MODE_WITH_PRICES',0);
    $q = clone $query;
    $q->setEncodedFacets(FilterEngine::encode(FilterEngine::normalize(['max'=>0])));
    $result = $provider->runQuery($searchContext,$q);
    verify($result->getTotalProductsCount() === 2, 'Hidden-price catalog ignores price filters');
    preg_match('/data-fd-config="([^"]+)"/', $provider->renderFacets($searchContext,$result), $matches);
    $config = json_decode(html_entity_decode($matches[1],ENT_QUOTES,'UTF-8'),true);
    verify($config['priceVisible'] === false && $config['bounds']['max'] === 0, 'Hidden prices do not leak via slider bounds');
    verify($result->getCurrentSortOrder()->getField() === 'position', 'Hidden-price catalog ignores price sorting');
} finally {
    Configuration::updateValue('PS_CATALOG_MODE',$catalogMode);
    Configuration::updateValue('PS_CATALOG_MODE_WITH_PRICES',$catalogPrices);
}
// Automatic organization reads native metadata and preserves overrides per shop.
$oldSettings = Configuration::get('FD_DRAWER_SETTINGS');
try {
    Configuration::updateValue('FD_DRAWER_SETTINGS', json_encode(['hide_uniform'=>true,'rules'=>[]]));
    $result = $provider->runQuery($searchContext,$query);
    preg_match('/data-fd-config="([^"]+)"/', $provider->renderFacets($searchContext,$result), $matches);
    $config = json_decode(html_entity_decode($matches[1],ENT_QUOTES,'UTF-8'),true);
    $groups = array_column($config['groups'],null,'id');
    verify(!$groups['brand']['displayed'] && !$groups['f1']['displayed'], 'Uniform brand and composition suppressed');
    verify($groups['a1']['displayed'], 'Sizes remain available for valid combination choices');
    verify(array_column($groups['a1']['options'],'label') === ['S','M','L','XL'], 'Real native attribute positions, not alphabetic sizes');
    $feature = Feature::getFeatures($context->language->id);
    verify(isset($groups['f1']['position']) && $groups['f1']['position'] === (int)$feature[0]['position'], 'Native feature position loaded');
    Configuration::updateValue('FD_DRAWER_SETTINGS', json_encode(['rules'=>['f2'=>['mode'=>'show','position'=>0],'a2'=>['mode'=>'hide']]]));
    $result = $provider->runQuery($searchContext,$query);
    preg_match('/data-fd-config="([^"]+)"/', $provider->renderFacets($searchContext,$result), $matches);
    $config = json_decode(html_entity_decode($matches[1],ENT_QUOTES,'UTF-8'),true);
    verify($config['groups'][0]['id']==='f2','Manual priority applied in real provider');
    verify(!array_column($config['groups'],null,'id')['a2']['displayed'],'Manual hide honored');
    $active = clone $query;
    $active->setEncodedFacets(FilterEngine::encode(FilterEngine::normalize(['groups'=>['a2'=>['11']]])));
    $result = $provider->runQuery($searchContext,$active);
    preg_match('/data-fd-config="([^"]+)"/', $provider->renderFacets($searchContext,$result), $matches);
    $config = json_decode(html_entity_decode($matches[1],ENT_QUOTES,'UTF-8'),true);
    verify(array_column($config['groups'],null,'id')['a2']['displayed'],'Active manually hidden group remains removable');
    verify($result->getTotalProductsCount() === 1,'Presentation settings preserve matching');
} finally {
    if ($oldSettings === false) { Configuration::deleteByName('FD_DRAWER_SETTINGS'); }
    else { Configuration::updateValue('FD_DRAWER_SETTINGS',$oldSettings); }
}
echo "$tests PrestaShop integration assertions passed\n";
