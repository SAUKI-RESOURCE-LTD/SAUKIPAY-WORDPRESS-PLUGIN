<?php
/**
 * Create sanitized documentation screenshots.
 *
 * This script crops WordPress admin chrome and masks keys/URLs from source
 * screenshots before they are committed to docs/assets.
 */

$root = dirname( __DIR__ );
$out  = $root . '/docs/assets';

if ( ! is_dir( $out ) ) {
	mkdir( $out, 0775, true );
}

$screenshots = array(
	array(
		'source' => '/Users/ayomikunoloyede/Downloads/WhatsApp Image 2026-07-11 at 04.03.46 (1).jpeg',
		'target' => $out . '/saukipay-settings-sanitized.jpg',
		'crop'   => array( 315, 65, 1280, 1780 ),
		'masks'  => array(
			array( 480, 270, 720, 90 ),
			array( 480, 1235, 750, 66 ),
			array( 480, 1350, 750, 66 ),
			array( 480, 1470, 750, 66 ),
		),
	),
	array(
		'source' => '/Users/ayomikunoloyede/Downloads/WhatsApp Image 2026-07-11 at 04.03.46.jpeg',
		'target' => $out . '/saukipay-form-builder-sanitized.jpg',
		'crop'   => array( 315, 170, 2600, 1650 ),
		'masks'  => array(),
	),
	array(
		'source' => '/Users/ayomikunoloyede/Downloads/WhatsApp Image 2026-07-11 at 04.07.00.jpeg',
		'target' => $out . '/saukipay-payment-form-sanitized.jpg',
		'crop'   => array( 110, 35, 1330, 1530 ),
		'masks'  => array(),
	),
	array(
		'source' => '/Users/ayomikunoloyede/Downloads/WhatsApp Image 2026-07-11 at 04.07.00 (1).jpeg',
		'target' => $out . '/saukipay-givewp-checkout-sanitized.jpg',
		'crop'   => array( 210, 0, 1220, 884 ),
		'masks'  => array(),
	),
);

foreach ( $screenshots as $screenshot ) {
	if ( ! file_exists( $screenshot['source'] ) ) {
		fwrite( STDERR, 'Missing source: ' . $screenshot['source'] . PHP_EOL );
		exit( 1 );
	}

	$image = imagecreatefromjpeg( $screenshot['source'] );

	if ( ! $image ) {
		fwrite( STDERR, 'Unable to open source: ' . $screenshot['source'] . PHP_EOL );
		exit( 1 );
	}

	list( $x, $y, $width, $height ) = $screenshot['crop'];
	$cropped = imagecrop(
		$image,
		array(
			'x'      => $x,
			'y'      => $y,
			'width'  => $width,
			'height' => $height,
		)
	);

	imagedestroy( $image );

	if ( ! $cropped ) {
		fwrite( STDERR, 'Unable to crop source: ' . $screenshot['source'] . PHP_EOL );
		exit( 1 );
	}

	$mask = imagecolorallocate( $cropped, 235, 241, 243 );
	$line = imagecolorallocate( $cropped, 194, 209, 215 );
	$text = imagecolorallocate( $cropped, 16, 47, 58 );

	foreach ( $screenshot['masks'] as $rect ) {
		list( $mx, $my, $mw, $mh ) = $rect;
		imagefilledrectangle( $cropped, $mx, $my, $mx + $mw, $my + $mh, $mask );
		imagerectangle( $cropped, $mx, $my, $mx + $mw, $my + $mh, $line );
		imagestring( $cropped, 5, $mx + 18, $my + (int) ( $mh / 2 ) - 8, 'Hidden for security', $text );
	}

	imagejpeg( $cropped, $screenshot['target'], 88 );
	imagedestroy( $cropped );

	echo $screenshot['target'] . PHP_EOL;
}
