<?php
/** Review archived source facts for the six already-published domestic candidates. */
use ParameterCommerce\Repository;
use ParameterCommerce\Metrics;

if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' || !current_user_can('manage_options') ||
    !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true)) {
    throw new RuntimeException('Domestic administrator CLI required.');
}
$mode = $args[0] ?? 'verify';
if (!in_array($mode, ['apply','verify'], true)) { throw new RuntimeException('Use apply or verify.'); }
function sscn_specs_require($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function sscn_specs_preserved() {
    $data=[];
    foreach (wc_get_products(['limit'=>-1,'type'=>['variable','variation','simple'],'status'=>['publish','draft']]) as $p) {
        $data[$p->get_id()]=[$p->get_status(),$p->get_price(),$p->get_regular_price(),$p->get_sale_price(),$p->get_manage_stock(),$p->get_stock_quantity(),$p->get_stock_status(),$p->get_attributes()];
    }
    return hash('sha256', serialize([$data,get_option('woocommerce_coming_soon'),get_option('woocommerce_currency')]));
}
$file=__DIR__.'/sample-drafts-20261019/products.json';
$hash=hash_file('sha256',$file);
$manifest=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
sscn_specs_require($manifest['site']==='domestic' && count($manifest['products'])===6,'Wrong source manifest.');
$before=sscn_specs_preserved();
$plan=[];
// The pasted 323 parameter table supports neither mesh panels nor terry location.
// Keep those two assertions unpublished until their source has been confirmed.
foreach ($manifest['products'] as $source) {
    $id=wc_get_product_id_by_sku($source['sku']); $p=wc_get_product($id);
    sscn_specs_require($p && $p->get_status()==='publish' && $p->get_meta('_pc_sample_drafts_operation')===$manifest['operation'],'Unexpected product: '.$source['sku']);
    $claims=[];
    for($page=1;;++$page){$rows=Repository::listing($id,$page);foreach(array_slice($rows,0,20) as $r){$claims[]=Repository::find((int)$r['id']);}if(count($rows)<=20)break;}
    foreach ($source['claims'] as $expected) {
        if ($source['model']==='323' && in_array($expected['metric_key'],['mesh_panels','cushion_location'],true)) { continue; }
        $matches=array_values(array_filter($claims,fn($c)=>$c['metric_key']===$expected['metric_key'] && !(int)$c['wc_variation_id']));
        sscn_specs_require(count($matches)===1,'Ambiguous source claim: '.$source['sku'].' '.$expected['metric_key']);
        $c=$matches[0]; $value=$c['value_num'] ?? $c['value_enum'];
        sscn_specs_require((is_numeric($expected['value']) ? (float)$value===(float)$expected['value'] : $value===$expected['value']) && $c['unit']===$expected['unit'] && $c['source_type']==='supplier_declared','Claim changed since source import.');
        sscn_specs_require(in_array($c['status'],['draft','reviewed','published'],true),'Unexpected claim lifecycle.');
        $plan[]=[$source,$c,$expected];
    }
}
if($mode==='apply' && !get_option('sscn_sample_specs_before_20261020')) {
    add_option('sscn_sample_specs_before_20261020',['source_sha256'=>$hash,'claims'=>array_column($plan,1)],'',false);
}
$count=0;
foreach($plan as [$source,$c,$expected]) {
    if($mode==='apply' && $c['status']==='draft') {
        $definition=Metrics::defaults()[$c['metric_key']];
        $measurement=$definition['category']==='measurement';
        $scope=$measurement ? '产品手册参考值；具体鞋码范围未注明，测量位置待确认' : '该型号的供应商声明；非独立检测结果';
        if($source['model']==='J02-J03') { $scope='J02／J03商品组参考；未分别标明型号与鞋码'; }
        $conditions=$measurement ? ['basis'=>'supplier_catalog','scope'=>'catalog reference; size coverage unconfirmed'] : ['applicability'=>'all_variations'];
        if($c['metric_key']==='pair_weight_g') {
            $conditions+=['quantity'=>'one pair (operator confirmed)','packaging'=>'excluded'];
            $scope.='；一双净重，不含包装';
        }
        if(!empty($definition['material_scope'])) {
            $conditions+=['area'=>'whole','material_component'=>$c['metric_key']==='wool_type'?'羊毛组分':'棉组分'];
        }
        $note='供应商资料声明，未进行独立检测。'.($measurement?'目录测量位置及具体尺码范围尚未确认，不用于推断鞋码或脚跟上方袜筒长度。':'');
        if($expected['note']) { $note.=$expected['note']; }
        if($source['model']==='J02-J03') { $note.='商品组参考数据，不作为每个型号、颜色或尺码的实测值。'; }
        $input=$c;
        $input['value']=$c['value_num'] ?? $c['value_enum'];
        $input['size_scope']=$scope; $input['conditions_json']=wp_json_encode((object)$conditions,JSON_UNESCAPED_UNICODE);
        $input['measured_at']=$source['source']['operator_context_date']; $input['note']=$note;
        $evidence=$c['evidence'];
        $evidence['public_title']=$source['model']==='323'?'商品详情参数资料':'产品手册参数资料';
        $evidence['note']='Stored source SHA-256: '.$hash.'. Archived source: sample-drafts-20261019/products.json; source receipt date, not a test date. '.wp_json_encode($source['source'],JSON_UNESCAPED_UNICODE);
        Repository::save($input,$evidence,(int)$c['id'],(int)$c['revision']);
        $c=Repository::find((int)$c['id']);
        Repository::transition((int)$c['id'],(int)$c['revision'],'reviewed');
        $c=Repository::find((int)$c['id']);
    }
    if($mode==='apply' && $c['status']==='reviewed') {
        Repository::transition((int)$c['id'],(int)$c['revision'],'published');
        $c=Repository::find((int)$c['id']);
    }
    sscn_specs_require($c['status']==='published','Parameter is not published: '.$source['sku'].' '.$c['metric_key']);
    $projection=Repository::public_specifications((int)$c['wc_product_id']);
    $metric=$projection['groups'][Metrics::defaults()[$c['metric_key']]['category']]['metrics'][$c['metric_key']];
    sscn_specs_require($metric['value']!==null,'Missing public parameter.');
    ++$count;
}
sscn_specs_require(sscn_specs_preserved()===$before,'Commerce values or visibility changed.');
WP_CLI::success('Verified '.$count.' published source-backed parameters; prices, stock and store visibility preserved.');
