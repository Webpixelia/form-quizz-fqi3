<?php
namespace Form_Quizz_FQI3;

/**
 * Ed25519 signature check for update packages served by LUS.
 *
 * LUS signs `lus-artifact-v1|<product>|<version>|<channel>|<sha256>` with a private key kept in the release CI.
 * The plugin embeds the matching PUBLIC key(s) and refuses any package whose signature does not verify:
 * a leaked deploy token or a compromised storage bucket cannot push code to the sites.
 *
 * The message format is a contract with LUS (ArtifactSignature::message) and with the CI (sign-artifact.php).
 *
 * @package Form_Quizz_FQI3
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Package_Signature {

	const MESSAGE_PREFIX = 'lus-artifact-v1';

	/**
	 * Public keys trusted by default (key id => base64 public key), as printed by `php artisan lus:signing:keygen`.
	 * During a key rotation list the old and the new key. Never put a private key here.
	 *
	 * @var array<string, string>
	 */
	const PUBLIC_KEYS = array(
		'k1' => 'OZN8D9s8U5OtxwKKc8t+eV5MVQ4K1n7wDNLg7zlh0bw=',
	);

	/**
	 * Message that is signed (same format as LUS ArtifactSignature::message()).
	 */
	public static function message( string $slug, string $version, string $channel, string $sha256 ): string {
		return implode( '|', array( self::MESSAGE_PREFIX, $slug, $version, $channel, strtolower( $sha256 ) ) );
	}

	/**
	 * Trusted public keys: embedded keys, plus (for tests or a rotation) FQI3_LUS_PUBLIC_KEYS (array or JSON object
	 * defined in wp-config.php) and the `fqi3_lus_public_keys` filter. An embedded key id can never be overridden.
	 *
	 * @return array<string, string>
	 */
	public static function trusted_keys(): array {
		$extra = array();

		if ( defined( 'FQI3_LUS_PUBLIC_KEYS' ) ) {
			$value = FQI3_LUS_PUBLIC_KEYS;
			if ( is_string( $value ) ) {
				$value = json_decode( $value, true );
			}
			if ( is_array( $value ) ) {
				$extra = $value;
			}
		}

		$extra = apply_filters( 'fqi3_lus_public_keys', $extra );
		$keys  = self::PUBLIC_KEYS;

		if ( is_array( $extra ) ) {
			foreach ( $extra as $kid => $key ) {
				if ( is_string( $kid ) && is_string( $key ) && ! isset( $keys[ $kid ] ) ) {
					$keys[ $kid ] = $key;
				}
			}
		}

		return $keys;
	}

	/**
	 * Unsigned packages are accepted ONLY on local/development sites AND when FQI3_LUS_ALLOW_UNSIGNED is true
	 * (local LUS started with LUS_SIGNING_REQUIRED=false). Never honored in staging or production.
	 */
	public static function allow_unsigned(): bool {
		return defined( 'FQI3_LUS_ALLOW_UNSIGNED' ) && FQI3_LUS_ALLOW_UNSIGNED
			&& in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
	}

	/**
	 * Verify a package signature.
	 *
	 * @param array{slug?: string, version?: string, channel?: string, signature?: ?string, kid?: ?string} $signed Data given by LUS for this package.
	 * @param string                                                                                       $actual_sha256 Hash of the downloaded file, computed locally (never the server-provided one).
	 * @return true|\WP_Error
	 */
	public static function verify( array $signed, string $actual_sha256 ) {
		$signature = (string) ( $signed['signature'] ?? '' );

		if ( '' === $signature ) {
			if ( self::allow_unsigned() ) {
				return true;
			}
			return new \WP_Error( 'fqi3_signature_missing', __( 'The update was refused: the package is not signed.', 'form-quizz-fqi3' ) );
		}

		$slug    = (string) ( $signed['slug'] ?? '' );
		$version = (string) ( $signed['version'] ?? '' );
		$channel = (string) ( $signed['channel'] ?? '' );
		$kid     = (string) ( $signed['kid'] ?? '' );

		// The separator is "|": a field containing it (or an empty field) could not be signed unambiguously.
		foreach ( array( $slug, $version, $channel ) as $field ) {
			if ( '' === $field || false !== strpos( $field, '|' ) ) {
				return self::invalid();
			}
		}

		$keys = self::trusted_keys();
		if ( ! isset( $keys[ $kid ] ) ) {
			return new \WP_Error( 'fqi3_signature_unknown_key', __( 'The update was refused: the package is signed with an unknown key.', 'form-quizz-fqi3' ) );
		}

		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return new \WP_Error( 'fqi3_signature_unavailable', __( 'The update was refused: signatures cannot be verified on this server (libsodium is missing).', 'form-quizz-fqi3' ) );
		}

		try {
			$public_key = sodium_base642bin( $keys[ $kid ], SODIUM_BASE64_VARIANT_ORIGINAL );
			$raw        = sodium_base642bin( $signature, SODIUM_BASE64_VARIANT_ORIGINAL );

			if ( SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $public_key ) || SODIUM_CRYPTO_SIGN_BYTES !== strlen( $raw ) ) {
				return self::invalid();
			}

			// The message carries the hash of the file we actually received: the signature binds the bytes, not a claim.
			$valid = sodium_crypto_sign_verify_detached( $raw, self::message( $slug, $version, $channel, $actual_sha256 ), $public_key );
		} catch ( \SodiumException $e ) {
			return self::invalid();
		}

		return $valid ? true : self::invalid();
	}

	private static function invalid(): \WP_Error {
		return new \WP_Error( 'fqi3_signature_invalid', __( 'The update package signature is invalid. It was not installed.', 'form-quizz-fqi3' ) );
	}
}
