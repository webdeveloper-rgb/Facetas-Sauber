<?php
require __DIR__ . '/../facetasdrawer/src/FacetOrganizer.php';
require __DIR__ . '/../facetasdrawer/src/FilterEngine.php';
use FacetasDrawer\FacetOrganizer as O;
use FacetasDrawer\FilterEngine as F;
$n = 0;
function expect($condition, $label) { global $n; ++$n; if (!$condition) { throw new RuntimeException($label); } }
function option($id, $label, $position = 0) { return ['id'=>(string)$id,'label'=>$label,'position'=>$position]; }
function byId($groups) { return array_column($groups, null, 'id'); }
$products = [
 ['id'=>1,'price'=>10,'quantity'=>1,'groups'=>['brand'=>['1'],'f1'=>['1'],'f2'=>['2'],'f3'=>['3']],'variants'=>[['attributes'=>['10','11'],'quantity'=>1]]],
 ['id'=>2,'price'=>20,'quantity'=>0,'groups'=>['brand'=>['1'],'f1'=>['1'],'f2'=>['2']],'variants'=>[['attributes'=>['11'],'quantity'=>0]]],
];
$groups = [
 'f1'=>['id'=>'f1','label'=>'Universal','position'=>9,'options'=>[option(1,'Cotton')]],
 'f2'=>['id'=>'f2','label'=>'Weight','position'=>1,'options'=>[option(20,'10 kg'),option(2,'2 kg'),option(21,'2,5 kg')]],
 'brand'=>['id'=>'brand','label'=>'Brand','options'=>[option(1,'A')]],
 'a1'=>['id'=>'a1','label'=>'Size','position'=>5,'options'=>[option(11,'XL',2),option(10,'S',0)]],
 'f3'=>['id'=>'f3','label'=>'Sparse','position'=>0,'options'=>[option(3,'Yes')]],
 'a2'=>['id'=>'a2','label'=>'Fit','position'=>0,'options'=>[option(12,'Regular',0)]],
];
$empty = F::normalize([]);
$out = O::organize($products,$groups,$empty,[]);
expect(array_column($out,'id') === ['brand','a2','a1','f3','f2','f1'],'Group order follows type and native positions');
$map=byId($out);
expect(!$map['brand']['displayed'] && !$map['f1']['displayed'],'Uniform brand and feature hidden');
expect($map['f3']['displayed'],'Sparse single value narrows category and stays visible');
expect(!$map['a2']['displayed'],'Groups without usable products hidden');
expect(array_column($map['a1']['options'],'label') === ['S','XL'],'Attribute order follows configured positions');
expect(array_column($map['f2']['options'],'label') === ['2 kg','2,5 kg','10 kg'],'Decimal comma and numeric unit values sort numerically');
$active=F::normalize(['groups'=>['brand'=>['1']]]);
expect(byId(O::organize($products,$groups,$active,[]))['brand']['displayed'],'Active uniform filter remains removable');
$settings=['rules'=>['brand'=>['mode'=>'hide'],'f1'=>['mode'=>'show'],'f2'=>['position'=>'0']]];
$out=O::organize($products,$groups,$active,$settings);
expect($out[0]['id']==='f2','Manual priority before automatic groups');
expect(byId($out)['brand']['displayed'],'Active explicitly hidden filter still visible');
expect(byId($out)['f1']['displayed'],'Always-show overrides automatic suppression');
expect(!byId(O::organize($products,$groups,$empty,$settings))['brand']['displayed'],'Admin hide works when unselected');
expect(byId(O::organize($products,$groups,$empty,['hide_uniform'=>false]))['f1']['displayed'],'Automatic suppression can be disabled');
expect(O::settings(['rules'=>['f1'=>['position'=>[]],'x'=>['mode'=>'hide']]])['rules']===[],'Reject malformed rules');
expect(O::settings(['rules'=>['f1'=>['position'=>'10000','mode'=>[]]]])['rules']===[],'Reject out of range priority and invalid modes');
expect(!O::settings(['hide_uniform'=>'0'])['hide_uniform'],'Form boolean normalized');
$groups['f2']['options']=[option(3,'10'),option(1,'2'),option(2,'1.5')];
expect(array_column(byId(O::organize($products,$groups,$empty,[]))['f2']['options'],'label')===['1.5','2','10'],'Bare numeric values sorted');
$groups['f2']['options']=[option(4,'Same'),option(3,'Same')];
expect(array_column(byId(O::organize($products,$groups,$empty,[]))['f2']['options'],'id')===['3','4'],'Equal labels preserve separate IDs with stable ordering');
// Presentation changes cannot change product matching, including persisted URLs.
$state=F::normalize(['groups'=>['f3'=>['3']]]);
$before=F::filter($products,$state);
O::organize($products,$groups,$state,['rules'=>['f3'=>['mode'=>'hide']]]);
expect(F::filter($products,$state)===$before && count($before)===1,'Organization preserves original product semantics');
$allSizes = $products;
$allSizes[1]['variants'] = [['attributes'=>['10','11'],'quantity'=>1]];
expect(byId(O::organize($allSizes,$groups,$empty,[]))['a1']['displayed'],'Keep multiple attribute values even with universal product coverage');
echo "$n organizer assertions passed\n";
