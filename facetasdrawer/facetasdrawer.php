<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once __DIR__ . '/src/FilterEngine.php';
require_once __DIR__ . '/src/FacetOrganizer.php';
require_once __DIR__ . '/src/FilterConfiguration.php';
require_once __DIR__ . '/src/SearchProvider.php';

class FacetasDrawer extends Module
{
    public function __construct()
    {
        $this->name = 'facetasdrawer';
        $this->tab = 'search_filter';
        $this->version = '1.1.0';
        $this->author = 'Facetas';
        $this->bootstrap = true;
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '8.99.99'];
        parent::__construct();
        $this->displayName = $this->l('Facetas — filter drawer');
        $this->description = $this->l('Independent category filtering with an accessible, responsive drawer.');
    }

    public function install()
    {
        return parent::install() && $this->registerHook('productSearchProvider') && $this->registerHook('displayHeader') && $this->registerHook('displayLeftColumn');
    }

    public function hookProductSearchProvider($params)
    {
        if (isset($params['query']) && $params['query']->getQueryType() === 'category') {
            return new \FacetasDrawer\SearchProvider($this);
        }
        return null;
    }

    public function hookDisplayHeader()
    {
        if ($this->context->controller->php_self !== 'category') {
            return;
        }
        $this->context->controller->registerStylesheet('facetas-drawer', 'modules/' . $this->name . '/views/css/drawer.css', ['media' => 'all', 'priority' => 150, 'version' => $this->version]);
        $this->context->controller->registerJavascript('facetas-drawer', 'modules/' . $this->name . '/views/js/drawer.js', ['position' => 'bottom', 'priority' => 150, 'version' => $this->version]);
    }

    public function hookDisplayLeftColumn()
    {
        $listing = $this->context->smarty->getTemplateVars('listing');
        return $this->context->controller->php_self === 'category' && is_array($listing)
            ? (isset($listing['rendered_facets']) ? $listing['rendered_facets'] : '') : '';
    }

    public function getFilterSettings()
    {
        $raw = Configuration::get('FD_DRAWER_SETTINGS');
        return \FacetasDrawer\FacetOrganizer::settings(is_string($raw) ? json_decode($raw, true) : []);
    }

    public function getContent()
    {
        return (new \FacetasDrawer\FilterConfiguration($this))->render();
    }

    public function uninstall()
    {
        return parent::uninstall() && Configuration::deleteByName('FD_DRAWER_SETTINGS');
    }

    public function renderDrawer(array $config)
    {
        $labels = [
            'filters' => $this->l('Filters'),
            'refine' => $this->l('Make it yours'),
            'intro' => $this->l('A few thoughtful filters. The perfect find.'),
            'close' => $this->l('Close filters'),
            'clear' => $this->l('Clear all'),
            'apply' => $this->l('Show results'),
            'price' => $this->l('Price range'),
            'min' => $this->l('Minimum price'),
            'max' => $this->l('Maximum price'),
            'stock' => $this->l('In stock only'),
            'stockHint' => $this->l('Ready for a new home'),
            'search' => $this->l('Search options'),
            'more' => $this->l('Show all options'),
            'less' => $this->l('Show fewer options'),
            'remove' => $this->l('Remove filter'),
            'selected' => $this->l('selected'),
            'empty' => $this->l('No matching options'),
            'invalid' => $this->l('Enter a valid price range.'),
        ];
        $config['labels'] = $labels;
        $config['state']['groups'] = (object) $config['state']['groups'];
        $this->context->smarty->assign('fd_config', json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE));
        return $this->display(__FILE__, 'views/templates/hook/drawer.tpl');
    }
}
