<?php
/** Reviewed taxonomy for the six sample drafts. No publication changes. */
function pc_sample_category_slugs($model) {
    $map = [
        '323'=>['everyday-socks','running-socks','cushioned-socks'],
        'J02-J03'=>['running-socks'],
        'X31'=>['everyday-socks','cushioned-socks'],
        'X65'=>['everyday-socks','merino-wool-socks'],
        'X72'=>['everyday-socks','running-socks'],
        'X59'=>['running-socks'],
    ];
    if (!isset($map[$model])) { throw new RuntimeException('Unexpected sample model.'); }
    return $map[$model];
}
function pc_sample_category_ids($model, $cn, $create=false) {
    $labels=['everyday-socks'=>['Everyday Socks','日常袜'],'running-socks'=>['Running Socks','跑步袜'],'cushioned-socks'=>['Cushioned Socks','缓冲袜'],'merino-wool-socks'=>['Merino Wool Socks','美利奴羊毛袜']];
    $parent=$cn ? get_term_by('slug','socks','product_cat') : false;
    if ($cn && !$parent) { throw new RuntimeException('Domestic Socks category missing.'); }
    $ids=$cn ? [(int)$parent->term_id] : [];
    foreach(pc_sample_category_slugs($model) as $slug) {
        $term=get_term_by('slug',$slug,'product_cat');
        if (!$term && $create) {
            $result=wp_insert_term($labels[$slug][$cn?1:0],'product_cat',['slug'=>$slug,'parent'=>$parent?(int)$parent->term_id:0]);
            if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
            $term=get_term($result['term_id'],'product_cat');
        }
        if (!$term || is_wp_error($term)) { throw new RuntimeException('Missing category '.$slug); }
        $ids[]=(int)$term->term_id;
    }
    sort($ids); return $ids;
}
