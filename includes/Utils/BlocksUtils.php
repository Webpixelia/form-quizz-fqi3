<?php
namespace Form_Quizz_FQI3\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlocksUtils {
	/**
	 * Extract Gutenberg styles and return them in a common format
	 *
	 * @return array An array containing 'classes' and 'styles' keys
	*/
	public static function extractGutenbergStyles(): array {
		$wrapper_attrs = get_block_wrapper_attributes();
		preg_match( '/class="([^"]*)"/', $wrapper_attrs, $class_matches );
		preg_match( '/style="([^"]*)"/', $wrapper_attrs, $style_matches );

		return array(
			'classes' => $class_matches[1] ?? '',
			'styles'  => $style_matches[1] ?? '',
		);
	}

	/**
	 * Clean text color classes - method common
	 *
	 * @param string $classes CSS classes
	 * @return string Cleaned classes
	 */
	public static function cleanTextColorClasses( string $classes ): string {
		$classes = preg_replace( '/\bhas-[a-z0-9\-]+-color\b(?!-background)/', '', $classes );
		$classes = str_replace( 'has-text-color', '', $classes );
		return trim( preg_replace( '/\s+/', ' ', $classes ) );
	}

	/**
	 * Extract color from styles - method common
	 *
	 * @param string $styles CSS styles
	 * @return string Extracted color
	 */
	public static function extractColorFromStyles( string $styles ): string {
		if ( empty( $styles ) ) {
			return '';
		}

		if ( preg_match( '/color:\s*([^;]+);?/', $styles, $color_match ) ) {
			return trim( $color_match[1] );
		}

		return '';
	}
}
