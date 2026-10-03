<?php
/**
 * Regression coverage for bounding sparse matrix batches by traversed rows.
 *
 * Run with: php tests/matrix-enqueue-budget-regression.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once dirname(__DIR__) . '/includes/class-sfc-matrix.php';

$method = new ReflectionMethod('SFC_Matrix', 'snapshot_batch_end');
$failures = array();

$cases = array(
    array('cursor' => 100, 'total' => 10000, 'limit' => 25, 'expected' => 125),
    array('cursor' => 9990, 'total' => 10000, 'limit' => 25, 'expected' => 10000),
    array('cursor' => 0, 'total' => 0, 'limit' => 250, 'expected' => 0),
    array('cursor' => 0, 'total' => 1, 'limit' => 1, 'expected' => 1),
);

foreach ($cases as $case) {
    $end = $method->invoke(null, $case['cursor'], $case['total'], $case['limit']);
    if ($end !== $case['expected']) {
        $failures[] = sprintf('Unexpected batch end %d; expected %d.', $end, $case['expected']);
    }
    if ($end - $case['cursor'] > $case['limit']) {
        $failures[] = 'A batch can traverse more snapshot rows than its limit.';
    }
}

// A sparse batch containing only MERGE/SKIP decisions must still stop at the row budget.
$decisions = array_fill(0, 10000, 'SKIP');
$cursor = 100;
$limit = 25;
$end = $method->invoke(null, $cursor, count($decisions), $limit);
$traversed = 0;
for ($index = $cursor; $index < $end; $index++) {
    $traversed++;
}
if ($traversed !== $limit || $end !== 125) {
    $failures[] = 'Sparse MERGE/SKIP traversal was not capped by the snapshot-row budget.';
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Matrix enqueue row-budget regression checks passed.\n";
