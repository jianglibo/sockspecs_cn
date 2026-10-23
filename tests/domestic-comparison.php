<?php
/** Read-only integration regression on the real domestic catalog. Run with local WP CLI. */
use ParameterCommerce\Comparison;
use ParameterCommerce\Repository;
if (!defined('WP_CLI') || !WP_CLI || DB_NAME!=='sockspecs_cn' || wp_get_environment_type()!=='local') { throw new RuntimeException('Local domestic only.'); }
$checks=0;
$compare_check=function($ok,$message)use(&$checks){if(!$ok)throw new RuntimeException($message);++$checks;};
wp_set_current_user(0);
$x59=wc_get_product_id_by_sku('SS-001-X59'); $cotton=wc_get_product_id_by_sku('SS-001-323');
$d=Comparison::data("$x59,$cotton");
$compare_check($d['items'][0]['specifications']['groups']['measurement']['metrics']['pair_weight_g']['value']==28,'X59 net pair weight');
$compare_check($d['items'][0]['specifications']['groups']['composition']['metrics']['cotton_pct']['value']===null,'Do not invent X59 composition');
$compare_check($d['items'][1]['specifications']['groups']['composition']['metrics']['cotton_pct']['value']==80,'323 composition visible to guests');
$compare_check($d['items'][1]['specifications']['groups']['construction']['metrics']['mesh_panels']['value']===null,'Unsubstantiated 323 claim remains private');
$compare_check(str_contains($d['items'][1]['options']['size'],'39'),'Native EU size visible');
$compare_check($d['items'][0]['options']['size']==='','No invented X59 shoe size');
$_GET['pc_compare']="$x59,$cotton"; $html=Comparison::render();
$compare_check(!str_contains(strip_tags($html),'WooCommerce'),'No platform branding in customer copy');
foreach(['可选尺码','EU 39','80 %','200 针','28 g','170 mm','85 mm'] as $text)$compare_check(str_contains($html,$text),'Missing customer detail: '.$text);
$compare_check(!str_contains($html,'Stored source SHA-256') && !str_contains($html,'source_org'),'No private source metadata');
$x70=wc_get_product_id_by_sku('SS-001-X70'); $wool=wc_get_product_id_by_sku('SS-001-365');
$d=Comparison::data("$x70,$wool");
$m=$d['items'][0]['specifications']['groups']['measurement']['metrics']['pair_weight_g'];
$compare_check($m['value']===null && count($m['variants'])===4,'Parent comparison retains four exact color measurements');
foreach($m['variants'] as $entry)$compare_check($entry['value']==37 && (int)$entry['record']['wc_variation_id']>0,'Net weight comes from exact published child');
$compare_check(Repository::public_specifications($x70)['groups']['measurement']['metrics']['pair_weight_g']['value']===null,'Comparison must not mutate or invent parent measurement');
$child=wc_get_product($x70)->get_children()[0];
$d=Comparison::data("$child,$cotton");
$compare_check($d['items'][0]['specifications']['groups']['measurement']['metrics']['pair_weight_g']['value']==37,'Selected variation still uses its own measurement');
$compare_check(empty($d['items'][0]['specifications']['groups']['measurement']['metrics']['pair_weight_g']['variants']),'Selected variation must not aggregate siblings');
$compare_check($d['items'][0]['specifications']['groups']['composition']['metrics']['cotton_pct']['value']==70,'Explicit model-wide composition still inherits');
foreach(wc_get_products(['limit'=>-1,'status'=>'publish','type'=>'variable']) as $p){
 $spec=Repository::public_specifications($p->get_id());$known=0;
 foreach($spec['groups'] as $g)foreach($g['metrics'] as $metric)if($metric['value']!==null)++$known;
 $compare_check($known>0,'Published product has no public facts: '.$p->get_sku());
}
WP_CLI::success("$checks comparison integration checks passed.");
