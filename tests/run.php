<?php

require_once __DIR__ . '/bootstrap.php';

$before_classes = get_declared_classes();

foreach ( glob( __DIR__ . '/*Test.php' ) as $test_file ) {
	require_once $test_file;
}

$after_classes = get_declared_classes();
$test_classes  = array_values(
	array_filter(
		array_diff( $after_classes, $before_classes ),
		static function ( $class_name ) {
			return is_subclass_of( $class_name, 'WPBM_TestCase' );
		}
	)
);

$results         = array();
$total_tests     = 0;
$total_assertions = 0;
$failed          = 0;

foreach ( $test_classes as $test_class ) {
	$reflection = new ReflectionClass( $test_class );
	$methods    = array_filter(
		$reflection->getMethods( ReflectionMethod::IS_PUBLIC ),
		static function ( ReflectionMethod $method ) use ( $test_class ) {
			return $method->class === $test_class && 0 === strpos( $method->getName(), 'test' );
		}
	);

	foreach ( $methods as $method ) {
		$instance = new $test_class();
		$name     = $test_class . '::' . $method->getName();
		++$total_tests;

		try {
			$instance->setUp();
			$instance->{$method->getName()}();
			$instance->tearDown();

			$total_assertions += $instance->getAssertionCount();
			$results[] = array(
				'name'   => $name,
				'status' => 'PASS',
			);
		} catch ( Throwable $throwable ) {
			++$failed;

			try {
				$instance->tearDown();
			} catch ( Throwable $ignored ) {
			}

			$results[] = array(
				'name'    => $name,
				'status'  => 'FAIL',
				'message' => $throwable->getMessage(),
			);
		}
	}
}

foreach ( $results as $result ) {
	echo sprintf( "[%s] %s", $result['status'], $result['name'] ) . PHP_EOL;

	if ( ! empty( $result['message'] ) ) {
		echo '  ' . $result['message'] . PHP_EOL;
	}
}

echo PHP_EOL;
echo sprintf(
	'Completed %1$d test(s), %2$d assertion(s), %3$d failure(s).',
	$total_tests,
	$total_assertions,
	$failed
) . PHP_EOL;

exit( $failed > 0 ? 1 : 0 );
