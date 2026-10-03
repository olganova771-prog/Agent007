<?php
/**
 * Regression coverage for WordPress post-meta slashing semantics.
 *
 * Run with: php tests/post-meta-slashing-regression.php
 */

function wp_slash($value) {
    return is_array($value) ? array_map('wp_slash', $value) : addslashes($value);
}
function wp_unslash($value) {
    return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value);
}

$failures = array();
$values = array(
    'ordinary metadata value',
    'ACME \\ Pro',
    'C:\\Program Files\\Site Factory\\data.json',
);

foreach ($values as $value) {
    // update_post_meta() unslashes incoming data before persistence.
    $stored = wp_unslash(wp_slash($value));
    if ($stored !== $value) {
        $failures[] = 'Slashing round-trip changed metadata value: ' . $value;
    }
    if ($stored != $value) {
        $failures[] = 'Strict read-back verification would fail: ' . $value;
    }
}

$generator = file_get_contents(dirname(__DIR__) . '/includes/class-sfc-generator.php');
if (strpos($generator, 'update_post_meta($post_id,$key,wp_slash($value));') === false) {
    $failures[] = 'SFC_Generator::set_meta() does not slash metadata before update_post_meta().';
}

$qa = file_get_contents(dirname(__DIR__) . '/includes/class-sfc-qa.php');
if (strpos($qa, "update_post_meta(\$post_id,'_sfc_qa',wp_slash(\$result));") === false) {
    $failures[] = 'SFC_QA::run_for_page() does not slash the QA result before update_post_meta().';
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Post meta slashing regression checks passed.\n";
