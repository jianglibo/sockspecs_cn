<?php
/** Read-only local checks of combined filtering and navigation. */
use ParameterCommerce\Repository as R;
if(!defined('WP_CLI')||!WP_CLI||DB_NAME!=='sockspecs_cn'||wp_get_environment_type()!=='local')throw new RuntimeException('Local domestic only');
$staff=get_users(['role'=>'administrator','number'=>1])[0];wp_set_current_user($staff->ID);
$n=0;$check=function($ok,$why)use(&$n){if(!$ok)throw new RuntimeException($why);++$n;};
$id=wc_get_product_id_by_sku('SS-001-X109');$check($id>0,'Known local product');
$rows=R::listing($id,1,'cushion_location');$check(count($rows)>0,'Combined result exists');foreach($rows as $r)$check((int)$r['wc_product_id']===$id&&$r['metric_key']==='cushion_location','Combined filters exact');
$all=R::listing(0,1,'cushion_location');$check(count($all)>count($rows),'Metric-only spans products');foreach($all as $r)$check($r['metric_key']==='cushion_location','Metric only exact');
$none=R::listing(2147483647,1,'cushion_location');$check(!$none,'No matches is empty, no fallback');
$check(count(R::listing($id,1))>count($rows),'Blank metric preserves product list');
foreach(['unregistered',"' OR 1=1 --",['cushion_location']] as $bad){$failed=false;try{R::listing(0,1,$bad);}catch(ParameterCommerce\Failure $e){$failed=isset($e->fields['metric_filter']);}$check($failed,'Reject invalid filter');}
$_GET=['product_id'=>(string)$id,'metric_filter'=>'cushion_location','paged'=>'2'];ob_start();ParameterCommerce\Admin::page();$html=ob_get_clean();$_GET=[];
$check(str_contains($html,'指标名称')&&str_contains($html,'全部指标'),'Localized selector');
$check(preg_match('/<select id="pc-metric-filter".*?<option value="cushion_location"[^>]*selected/s',$html)===1,'Selection preserved');
$doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);$xp=new DOMXPath($doc);$prev=false;foreach($xp->query('//a[@href]') as $link){parse_str(parse_url(trim(html_entity_decode($link->getAttribute('href'))),PHP_URL_QUERY)??'', $q);if(($q['paged']??'')==='1'&&($q['metric_filter']??'')==='cushion_location'&&(int)($q['product_id']??0)===$id)$prev=true;}$check($prev,'Previous page keeps both filters');
$_GET=['product_id'=>(string)$id,'metric_filter'=>'cushion_location'];ob_start();ParameterCommerce\Admin::page();$html=ob_get_clean();$_GET=[];
$doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);$xp=new DOMXPath($doc);$record=false;foreach($xp->query('//a[@href]') as $link){parse_str(parse_url(trim(html_entity_decode($link->getAttribute('href'))),PHP_URL_QUERY)??'', $q);if(isset($q['claim_id'])&&($q['metric_filter']??'')==='cushion_location'&&(int)($q['product_id']??0)===$id)$record=true;}$check($record,'Opening a record keeps filters');
wp_set_current_user(0);$failed=false;try{R::listing(0,1,'cushion_location');}catch(ParameterCommerce\Failure $e){$failed=$e->status===403;}$check($failed,'Staff-only filter');
WP_CLI::success($n.' admin metric filter checks passed.');
