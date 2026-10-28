<?php
require __DIR__ . '/../facetasdrawer/src/FilterEngine.php';
use FacetasDrawer\FilterEngine as F;
$tests = 0;
function check($condition, $message) {
    global $tests;
    ++$tests;
    if (!$condition) { throw new RuntimeException($message); }
}
function ids($products) { return array_column($products, 'id'); }
$products = [
    ['id'=>1,'price'=>20,'quantity'=>4,'groups'=>['brand'=>['1'],'f1'=>['10']],'variants'=>[
        ['attributes'=>['100','200'],'quantity'=>2], ['attributes'=>['101','201'],'quantity'=>2],
    ]],
    ['id'=>2,'price'=>50,'quantity'=>2,'groups'=>['brand'=>['2'],'f1'=>['10','11']],'variants'=>[
        ['attributes'=>['100','201'],'quantity'=>0], ['attributes'=>['101','200'],'quantity'=>2],
    ]],
    ['id'=>3,'price'=>100,'quantity'=>0,'groups'=>['brand'=>['1'],'f1'=>['11']],'variants'=>[]],
];
check(ids(F::filter($products,F::normalize([]))) === [1,2,3], 'No filter');
check(ids(F::filter($products,F::normalize(['groups'=>['brand'=>['1','2']]]))) === [1,2,3], 'OR in a facet');
check(ids(F::filter($products,F::normalize(['groups'=>['brand'=>['1'],'f1'=>['10']]]))) === [1], 'AND across facets');
check(ids(F::filter($products,F::normalize(['groups'=>['a1'=>['100'],'a2'=>['201']]]))) === [2], 'Do not cross match separate combinations');
check(ids(F::filter($products,F::normalize(['groups'=>['a1'=>['100'],'a2'=>['201']],'stock'=>true]))) === [], 'Selected combination must be in stock');
check(ids(F::filter($products,F::normalize(['min'=>20,'max'=>50]))) === [1,2], 'Inclusive prices');
check(ids(F::filter($products,F::normalize(['min'=>50,'max'=>20]))) === [1,2], 'Reversed range normalization');
check(ids(F::filter($products,F::normalize(['stock'=>true]))) === [1,2], 'Stock filter');
check(ids(F::filter($products,F::normalize(['groups'=>['brand'=>['99']]]))) === [], 'Unknown selection must not silently widen results');
$groups = ['brand'=>['id'=>'brand','options'=>[['id'=>'1'],['id'=>'2']]]];
$counts = F::counts($products,F::normalize(['groups'=>['brand'=>['1'],'f1'=>['10']]]),$groups);
check(array_column($counts[0]['options'],'count') === [1,1], 'Counts exclude own facet and honor other facets');
foreach ([null,[],str_repeat('x',9000),'not-a-state','fd1.!!!!','fd1.'.base64_encode('null'),'fd1.'.base64_encode('[]')] as $raw) {
    check(F::decode($raw) === F::normalize([]), 'Malformed request safely ignored');
}
$state = F::normalize(['groups'=>['brand'=>['1','1','2'],'f1'=>['10']], 'min'=>20, 'stock'=>true]);
check(F::decode('fd1.'.rtrim(strtr(base64_encode(json_encode($state)),'+/','-_'),'=')) === $state, 'URL round trip');
check(F::normalize(['groups'=>['invalid'=>['1'],'brand'=>['1 OR 1=1',[],'-1','0']],'min'=>[],'max'=>INF]) === F::normalize([]), 'Hostile input normalized');
check(count(F::normalize(['groups'=>['brand'=>range(1,200)]])['groups']['brand']) === 100, 'Bound request work');
check(F::normalize(['stock'=>'false'])['stock'] === false, 'Strict boolean');
check(F::normalize(['min'=>-1,'max'=>'NaN'])['max'] === null, 'Invalid numeric values ignored');
check(F::encode(F::normalize([])) === '', 'Empty state clears query');
check(F::decode(F::encode($state)) === $state, 'Canonical result URL preserves filters');
echo "$tests filter engine assertions passed\n";
