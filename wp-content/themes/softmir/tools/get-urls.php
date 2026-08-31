<?php
require_once(dirname(__FILE__) . '/../../../../wp-load.php');
$q = new WP_Query(['post_type'=>'software','posts_per_page'=>3]); 
foreach($q->posts as $p) {
    echo "- " . $p->post_title . "\n  " . get_permalink($p->ID) . "\n";
}
