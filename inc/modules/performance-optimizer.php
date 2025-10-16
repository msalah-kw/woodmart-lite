<?php
/**
 * Front-end performance helpers.
 *
 * @package woodmart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'woodmart_performance_disable_emojis' ) ) {
    /**
     * Remove emoji scripts and styles on the front end.
     *
     * @since 1.0.0
     *
     * @return void
     */
    function woodmart_performance_disable_emojis() {
        if ( ! apply_filters( 'woodmart_disable_emojis', true ) ) {
            return;
        }

        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'embed_head', 'print_emoji_detection_script' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );

        if ( is_admin() ) {
            remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
            remove_action( 'admin_print_styles', 'print_emoji_styles' );
        }
    }

    add_action( 'init', 'woodmart_performance_disable_emojis' );
}

if ( ! function_exists( 'woodmart_performance_filter_tinymce_emojis' ) ) {
    /**
     * Strip the emoji plugin from TinyMCE to avoid loading assets in the editor.
     *
     * @since 1.0.0
     *
     * @param array $plugins TinyMCE plugins.
     *
     * @return array
     */
    function woodmart_performance_filter_tinymce_emojis( $plugins ) {
        if ( ! apply_filters( 'woodmart_disable_emojis', true ) || ! is_array( $plugins ) ) {
            return $plugins;
        }

        return array_diff( $plugins, array( 'wpemoji' ) );
    }

    add_filter( 'tiny_mce_plugins', 'woodmart_performance_filter_tinymce_emojis' );
    add_filter( 'emoji_svg_url', '__return_false' );
}

if ( ! function_exists( 'woodmart_performance_maybe_disable_wp_embed' ) ) {
    /**
     * Drop the legacy wp-embed script for visitors when allowed.
     *
     * @since 1.0.0
     *
     * @return void
     */
    function woodmart_performance_maybe_disable_wp_embed() {
        if ( is_admin() || ! apply_filters( 'woodmart_disable_wp_embed', false ) ) {
            return;
        }

        wp_deregister_script( 'wp-embed' );
        wp_dequeue_script( 'wp-embed' );
    }

    add_action( 'wp_enqueue_scripts', 'woodmart_performance_maybe_disable_wp_embed', 100 );
}

if ( ! function_exists( 'woodmart_performance_maybe_dequeue_dashicons' ) ) {
    /**
     * Remove Dashicons for visitors.
     *
     * @since 1.0.0
     *
     * @return void
     */
    function woodmart_performance_maybe_dequeue_dashicons() {
        if ( is_admin() || is_user_logged_in() || is_customize_preview() ) {
            return;
        }

        if ( apply_filters( 'woodmart_disable_dashicons_for_guests', true ) && wp_style_is( 'dashicons', 'enqueued' ) ) {
            wp_dequeue_style( 'dashicons' );
        }
    }

    add_action( 'wp_enqueue_scripts', 'woodmart_performance_maybe_dequeue_dashicons', 100 );
}

if ( ! function_exists( 'woodmart_performance_defer_scripts' ) ) {
    /**
     * Add a defer attribute to non-critical scripts.
     *
     * @since 1.0.0
     *
     * @param string $tag    Script tag HTML.
     * @param string $handle Script handle.
     * @param string $src    Script source URL.
     *
     * @return string
     */
    function woodmart_performance_defer_scripts( $tag, $handle, $src ) {
        if ( is_admin() || empty( $src ) ) {
            return $tag;
        }

        $allow_list = apply_filters(
            'woodmart_defer_script_handles',
            array(
                'wd-libraries',
                'wd-device-library',
                'wd-justified-library',
                'wd-magnific-library',
                'wd-photoswipe-library',
                'wd-swiper-library',
                'wd-woocommerce-notices',
                'wd-ajax-filters',
                'wd-shop-page-init',
                'wd-back-history',
                'wd-widget-collapse',
            )
        );

        if ( ! in_array( $handle, $allow_list, true ) ) {
            return $tag;
        }

        $wp_scripts = wp_scripts();
        $registered = $wp_scripts->registered[ $handle ] ?? null;

        if ( ! $registered || empty( $registered->extra['group'] ) || (int) $registered->extra['group'] < 1 ) {
            return $tag;
        }

        if ( false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' type="module"' ) ) {
            return $tag;
        }

        return preg_replace( '/<script\b/', '<script defer', $tag, 1 );
    }

    add_filter( 'script_loader_tag', 'woodmart_performance_defer_scripts', 20, 3 );
}

if ( ! function_exists( 'woodmart_performance_optimize_google_fonts_tag' ) ) {
    /**
     * Convert Google font stylesheet to a non-blocking request.
     *
     * @since 1.0.0
     *
     * @param string $html   Link tag HTML.
     * @param string $handle Style handle.
     * @param string $href   Stylesheet URL.
     * @param string $media  Stylesheet media attribute.
     *
     * @return string
     */
    function woodmart_performance_optimize_google_fonts_tag( $html, $handle, $href, $media ) {
        if ( is_admin() || 'xts-google-fonts' !== $handle || empty( $href ) ) {
            return $html;
        }

        $preconnect = '';

        if ( apply_filters( 'woodmart_preconnect_google_fonts', true ) ) {
            $preconnect  = '<link rel="preconnect" href="https://fonts.googleapis.com" />' . "\n";
            $preconnect .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />' . "\n";
        }

        $preload = sprintf(
            '<link rel="preload" href="%1$s" as="style" media="%2$s" onload="this.onload=null;this.rel=\'stylesheet\'">',
            esc_url( $href ),
            esc_attr( $media ? $media : 'all' )
        );

        $fallback = sprintf(
            '<noscript><link rel="stylesheet" href="%s"></noscript>',
            esc_url( $href )
        );

        return $preconnect . $preload . $fallback;
    }

    add_filter( 'style_loader_tag', 'woodmart_performance_optimize_google_fonts_tag', 10, 4 );
}
