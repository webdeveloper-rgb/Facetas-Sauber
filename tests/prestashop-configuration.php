<?php
/** Configuration checks for the isolated demo shop, with state restored afterward. */
if (($argv[2] ?? '') !== '--isolated-demo') { exit(1); }
require rtrim($argv[1], '/') . '/config/config.inc.php';
$context = Context::getContext();
$context->employee = new Employee(1);
$context->controller = new AdminModulesController();
$module = Module::getInstanceByName('facetasdrawer');
$old = Configuration::get('FD_DRAWER_SETTINGS');
$tests = 0;
function assertConfig($condition, $message) { global $tests; ++$tests; if (!$condition) { throw new RuntimeException($message); } }
$originalPost = $_POST;
$originalGet = $_GET;
$method = $_SERVER['REQUEST_METHOD'] ?? '';
try {
    Configuration::updateValue('FD_DRAWER_SETTINGS', json_encode(['hide_uniform'=>true,'rules'=>[]]));
    $_GET = [];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $html = $module->getContent();
    assertConfig(strpos($html, 'fd_rules[f1][mode]') !== false, 'Feature settings discovered');
    assertConfig(strpos($html, 'fd_rules[a1][mode]') !== false, 'Attribute settings discovered');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['submitFacetasSettings'=>'1', 'token'=>'invalid','fd_hide_uniform'=>'0','fd_rules'=>['f1'=>['mode'=>'hide','position'=>'0']]];
    $module->getContent();
    assertConfig($module->getFilterSettings()['hide_uniform'] === true, 'Bad CSRF token cannot change configuration');
    $_POST['token'] = Tools::getAdminTokenLite('AdminModules');
    $module->getContent();
    $settings = $module->getFilterSettings();
    assertConfig(!$settings['hide_uniform'] && $settings['rules']['f1']===['mode'=>'hide','position'=>0], 'Valid configuration persists');
    $before = Configuration::get('FD_DRAWER_SETTINGS');
    $_POST['fd_rules']['f1']['position'] = '-1';
    $module->getContent();
    assertConfig(Configuration::get('FD_DRAWER_SETTINGS') === $before, 'Invalid priority rejects the whole update');
    $_POST['fd_rules']['f1'] = ['mode'=>'auto','position'=>''];
    $module->getContent();
    assertConfig(!isset($module->getFilterSettings()['rules']['f1']), 'Reset to automatic removes old override');
} finally {
    $_POST = $originalPost;
    $_GET = $originalGet;
    $_SERVER['REQUEST_METHOD'] = $method;
    if ($old === false) { Configuration::deleteByName('FD_DRAWER_SETTINGS'); }
    else { Configuration::updateValue('FD_DRAWER_SETTINGS',$old); }
}
echo "$tests configuration assertions passed\n";
