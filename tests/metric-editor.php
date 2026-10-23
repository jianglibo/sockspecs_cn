<?php
/** Read-only domestic local form/validator regression. */
if(!defined('WP_CLI')||!WP_CLI||DB_NAME!=='sockspecs_cn'||wp_get_environment_type()!=='local')throw new RuntimeException('Local domestic only');
$staff=get_users(['role'=>'administrator','number'=>1])[0];wp_set_current_user($staff->ID);
$input=['wc_product_id'=>wc_get_product_id_by_sku('SS-001-X59'),'wc_variation_id'=>0,'metric_key'=>'cushion_location','value'=>'partial-terry','unit'=>'','source_type'=>'supplier_declared','conditions_json'=>'{}'];
$n=ParameterCommerce\Validator::claim($input);if($n['value_enum']!=='partial-terry'||$n['unit']!=='')throw new RuntimeException('Unitless rejected');
$failed=false;try{ParameterCommerce\Validator::claim(array_replace($input,['unit'=>'%']));}catch(ParameterCommerce\Failure $e){$failed=isset($e->fields['unit']);}if(!$failed)throw new RuntimeException('Invalid unit accepted');
ob_start();ParameterCommerce\Admin::page(['unit'=>'DEMO error'],array_replace($input,['unit'=>'%','claim_id'=>0,'revision'=>0]));$html=ob_get_clean();
if(!preg_match('/<option data-unit="&quot;&quot;" value="cushion_location"[^>]*selected/', $html))throw new RuntimeException('Missing unitless dictionary binding');
if(!str_contains($html,'data-unit="null" value="yarn_count"'))throw new RuntimeException('Multi-unit system guessed');
if(!str_contains($html,'name="unit" value="%"'))throw new RuntimeException('Server rewrote submitted unit');
ParameterCommerce\Product_Picker::assets();if(!wp_script_is('pc-metric-editor','enqueued'))throw new RuntimeException('Missing error-page asset');
WP_CLI::success('6 read-only metric editor checks passed.');
