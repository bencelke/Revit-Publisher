<?php
/**
 * Resolves GSC API client (real or fixture).
 *
 * @package RevIt_Publisher
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RevIt_Publisher_GSC_Client {

	public static function create(): RevIt_Publisher_GSC_Client_Interface {
		$auth = RevIt_Publisher_Services::gsc_auth();
		if ( $auth->is_fixture_mode() || ( defined( 'REVIT_PUBLISHER_GSC_USE_FAKE' ) && REVIT_PUBLISHER_GSC_USE_FAKE ) ) {
			return new RevIt_Publisher_GSC_Fake_Client();
		}
		if ( class_exists( RevIt_Publisher_GSC_Google_Client::class ) ) {
			return new RevIt_Publisher_GSC_Google_Client();
		}
		return new RevIt_Publisher_GSC_Fake_Client();
	}
}
