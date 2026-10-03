<?php
/**
 * Static regression check for MariaDB/MySQL-safe matrix run cursor SQL.
 *
 * Run with: php tests/schema-sql-regression.php
 */

$root = dirname(__DIR__);
$db = file_get_contents($root . '/includes/class-sfc-db.php');
$matrix = file_get_contents($root . '/includes/class-sfc-matrix.php');

$failures = array();
if (strpos($db, 'run_cursor bigint(20) unsigned NOT NULL DEFAULT 0') === false) {
    $failures[] = 'The matrix-runs schema does not define run_cursor.';
}
if (preg_match('/^\s*cursor\s+bigint/im', $db)) {
    $failures[] = 'The matrix-runs schema still defines the reserved cursor identifier.';
}
if (strpos($db, 'in_array(\'run_cursor\'') === false) {
    $failures[] = 'schema_ready() does not require run_cursor.';
}
foreach (array("'run_cursor'=>0", "['run_cursor']", 'SET run_cursor=%d', 'AND run_cursor=%d') as $needle) {
    if (strpos($matrix, $needle) === false) {
        $failures[] = 'Matrix run SQL is missing expected fragment: ' . $needle;
    }
}
if (preg_match('/(?:SET|AND)\s+cursor\s*=/i', $matrix)) {
    $failures[] = 'Matrix runtime SQL still accesses the legacy cursor column.';
}
if (strpos($db, 'SET `run_cursor`=`cursor`') === false) {
    $failures[] = 'The idempotent legacy cursor data migration is missing.';
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Schema cursor regression checks passed.\n";
