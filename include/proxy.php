<?php

// Runs without WordPress, so WP_DEBUG_DISPLAY doesn't apply: never print a notice into a tile
ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed

if ( isset( $_SERVER['REQUEST_URI'] ) ) {
	$request_uri     = stripslashes( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
} else {
	http_response_code( 400 );
	exit();
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) ) {
	$script_filename = stripslashes( $_SERVER['SCRIPT_FILENAME'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
} else {
	http_response_code( 400 );
	exit();
}

// match pattern /<provider>/<z>/<x>/<y><r>
if ( ! preg_match( '/\/([a-z0-9\.]+)\/(\d+)\/(\d+)\/(\d+)(@2x)?$/i', $request_uri, $map_matches ) ) {
	http_response_code( 400 );
	exit();
}

$proxy_dir = pathinfo( $script_filename, PATHINFO_DIRNAME );
// request didn't come from proxy dir.
if ( ! preg_match( '/wp-content\/maps$/', $proxy_dir ) ) {
	http_response_code( 400 );
	exit();
}

/**
 *	Read a proxy config: JSON behind a first line that stops PHP when the file is requested over HTTP
 *	(see MapProxy::CONFIG_GUARD). Read as data, never included.
 *
 *	@param string $file
 *	@return array
 */
$read_proxy_config = function( $file ) {
	if ( ! is_file( $file ) ) {
		return [];
	}
	$contents  = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$guard_end = strpos( $contents, '?>' );
	if ( 0 === strpos( $contents, '<?php' ) && false !== $guard_end ) {
		$contents = substr( $contents, $guard_end + 2 );
	}
	$config = json_decode( trim( $contents ), true );
	return is_array( $config ) ? $config : [];
};

// multisite: the main site's config is the base, the site's own config overrides it
$blog_id     = preg_match( '/\/sites\/(\d+)\//i', $request_uri, $matches ) ? (int) $matches[1] : 0; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$content_dir = pathinfo( $proxy_dir, PATHINFO_DIRNAME ); // the configs are in wp-content/, see MapProxy::get_config_file()

$legacy_config = [];
if ( ! defined( 'ACF_OSM_PROXY_INDEX' ) ) {
	// Called by the index.php of an older version: not migrated yet (MapProxy::upgrade() runs on the first
	// admin visit after an update). Use the configs it wrote, which the migration deletes: the network
	// config the old index.php has read into $proxy_config, and the site's config in uploads/.
	$legacy_config = isset( $proxy_config ) && is_array( $proxy_config ) ? $proxy_config : [];
	$legacy_file   = $content_dir . '/uploads' . ( $blog_id ? '/sites/' . $blog_id : '' ) . '/acf-osm-proxy-config.json';
	if ( is_file( $legacy_file ) ) {
		$legacy_site_config = json_decode( file_get_contents( $legacy_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( is_array( $legacy_site_config ) ) {
			$legacy_config = array_replace( $legacy_config, $legacy_site_config );
		}
	}
}

$proxy_config = array_replace(
	$legacy_config,
	$read_proxy_config( $content_dir . '/acf-osm-proxy-config.php' ),
	$blog_id ? $read_proxy_config( $content_dir . '/acf-osm-proxy-config-' . $blog_id . '.php' ) : []
);

@list( $garbage, $provider, $z, $x, $y, $r ) = $map_matches;



if ( ! isset( $proxy_config[$provider] ) ) {
	http_response_code(404);
	exit();
}

// read from config
$base_url   = (string) ( $proxy_config[ $provider ][ 'base_url' ] ?? '' );
$subdomains = $proxy_config[ $provider ][ 'subdomains' ] ?? '';
$subdomains = is_array( $subdomains ) ? array_values( $subdomains ) : str_split( (string) $subdomains ); // 'abc' or [ 'a', 'b', 'c' ], like Leaflet

// tile servers only: no other stream wrapper (file://, …)
if ( ! preg_match( '/^https?:\/\//i', $base_url ) ) {
	http_response_code( 404 );
	exit();
}

$s = $subdomains ? (string) $subdomains[ array_rand( $subdomains ) ] : ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

// fill vars
$url = str_replace(
	[ '{z}', '{x}', '{y}', '{s}', '{r}' ],
	[ $z, $x, $y, $s, $r ],
	$base_url
);

// response headers being forwarded (Content-Type: see below)
$send_response_headers = [
	'Expires',
	'Cache-Control',
	'ETag',
	'Date',
];
$request_headers = [];

// get http headers from user
foreach ( [
	'User-Agent',
	'Accept',
	'Accept-Language',
	// 'Accept-Encoding' is NOT forwarded: the response body is passed on as is, without its Content-Encoding
	// 'Referer' is intentionally NOT forwarded: leaking the visitor's page URL to
	// the upstream tile server would defeat the privacy purpose of this proxy.
	'Sec-GPC',
	'Sec-Fetch-Dest',
	'Sec-Fetch-Mode',
	'Sec-Fetch-Site',
	'Priority',
	'Pragma',
	'Cache-Control',
] as $header ) {
	$hdr = 'HTTP_'.str_replace( '-', '_', strtoupper( $header ) );
	if ( isset( $_SERVER[ $hdr ] ) ) {
		$request_headers[] = "{$header}: ".stripslashes($_SERVER[ $hdr ]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}
}

/** @var int $http_status assume success */
$http_status    = 200;

/** @var int $request_status 1: host resolved, 2: remote connected, 3: got filesize, 4: got mime type */
$request_status = 0;

$ctx = stream_context_create(['http' => [
		'method'        => 'GET',
		'header'        => implode("\r\n", $request_headers ),
		'timeout'       => 10, // don't let a slow tile server hang the request
		'max_redirects' => 3,
	]],
	[
	'notification'  => function( $notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max ) use ( &$http_status, &$request_status ) {
		// debugging
		// $notification_codes = [
		// 	STREAM_NOTIFY_RESOLVE       => 'STREAM_NOTIFY_RESOLVE',
		// 	STREAM_NOTIFY_CONNECT       => 'STREAM_NOTIFY_CONNECT',
		// 	STREAM_NOTIFY_AUTH_REQUIRED => 'STREAM_NOTIFY_AUTH_REQUIRED',
		// 	STREAM_NOTIFY_MIME_TYPE_IS  => 'STREAM_NOTIFY_MIME_TYPE_IS',
		// 	STREAM_NOTIFY_FILE_SIZE_IS  => 'STREAM_NOTIFY_FILE_SIZE_IS',
		// 	STREAM_NOTIFY_REDIRECTED    => 'STREAM_NOTIFY_REDIRECTED',
		// 	STREAM_NOTIFY_PROGRESS      => 'STREAM_NOTIFY_PROGRESS',
		// 	STREAM_NOTIFY_COMPLETED     => 'STREAM_NOTIFY_COMPLETED',
		// 	STREAM_NOTIFY_FAILURE       => 'STREAM_NOTIFY_FAILURE',
		// 	STREAM_NOTIFY_FAILURE       => 'STREAM_NOTIFY_FAILURE',
		// ];

		switch ( $notification_code ) {
			case STREAM_NOTIFY_RESOLVE:
				$request_status = 1;
				break;
			case STREAM_NOTIFY_CONNECT:
				$request_status = 2;
				break;
			case STREAM_NOTIFY_FILE_SIZE_IS:
				$request_status = 3;
				break;
			case STREAM_NOTIFY_MIME_TYPE_IS:
				$request_status = 4;
				break;
			case STREAM_NOTIFY_PROGRESS:
				$request_status = 5;
				break;
			case STREAM_NOTIFY_FAILURE: // 404
				$http_status = $message_code;
				break;
		}
	},
]);

$response_reg = '/^(' . implode( '|', $send_response_headers ) . '):/i';

// @: the warning of a failed request holds the upstream URL, access token included
$contents = @file_get_contents( $url, false, $ctx ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

// $http_response_header is deprecated as of PHP 8.5, http_get_last_response_headers() exists since 8.4
$response_headers = (array) ( function_exists( 'http_get_last_response_headers' )
	? http_get_last_response_headers()
	: ( get_defined_vars()['http_response_header'] ?? [] ) );

// status of the last response (after redirects): the stream notifications miss some, e.g. 403
$content_type = '';
foreach ( $response_headers as $response_header ) {
	if ( preg_match( '/^HTTP\/\S+\s+(\d{3})/', $response_header, $status_match ) ) {
		$http_status  = (int) $status_match[1];
		$content_type = '';
	} elseif ( preg_match( '/^Content-Type:\s*(.+)$/i', $response_header, $type_match ) ) {
		$content_type = trim( $type_match[1] );
	}
}

// no answer, or no body after a redirect loop
if ( ! $request_status
	|| ( false === $contents && 200 === $http_status )
	|| ( ( false === $contents || '' === $contents ) && $http_status >= 300 && $http_status < 400 )
) {
	$http_status = 502;
}

http_response_code( $http_status );

// served from this site's origin: never let a response render as a page
header( 'X-Content-Type-Options: nosniff' );
header( "Content-Security-Policy: default-src 'none'; sandbox" );

foreach ( $response_headers as $response_header ) {
	if ( preg_match( $response_reg, $response_header ) ) {
		header( $response_header );
	}
}

if ( 200 === $http_status ) {
	// one image type, parameters allowed, no second type after a comma
	header( 'Content-Type: ' . ( preg_match( '/^image\/[\w.+-]+\s*(;[^,]*)?$/i', $content_type ) ? $content_type : 'application/octet-stream' ) );
	echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
