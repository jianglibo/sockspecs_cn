<?php
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' || home_url() !== 'http://127.0.0.1:8089' || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local domestic test only'); }
[$id,$credit,$referrer,$folder,$worker]=$args;
file_put_contents($folder.'/ready-'.$worker,'ready');$end=microtime(true)+30;
while(!file_exists($folder.'/go')){if(microtime(true)>$end)throw new RuntimeException('Barrier timed out');usleep(20000);}
try{ParameterCommerce\Referral_Store::reserve(wc_get_order((int)$id),(int)$credit,(int)$referrer);echo 'RESERVED';}catch(RuntimeException $e){echo 'REJECTED';}
