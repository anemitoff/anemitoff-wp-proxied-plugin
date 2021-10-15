<?php
/*
 * Plugin name: Anemitoff WP Proxied
 * Description: When blog is accessed by proxy, links are rewritten to appear as though they belog to proxy origin
 * Version: 1.1.9
 * Author: Adam Nemitoff
 * Author URI: https://teamnemitoff.com
 * License: GPL v3
 */


if (!class_exists(AnemitoffWpProxiedPlugin::class)) {
    class AnemitoffWpProxiedPlugin {
        const USE_CANONICAL_HOST = true;
        const CANONICAL_HOST = 'www.nassaucandy.com';
        //const CANONICAL_HOST = 'ncweb241.teamnemitoff.com'; // this must reverse proxy to actual blog host
        const USE_TEMPORARY_REDIRECT_FOR_CANONICAL_HOST = true;

        function __construct() {
            add_filter('post_link', [$this, 'filterPostLink']);
            add_filter('wp_nav_menu_objects', [$this, 'filterNavMenuObjects']);
            add_filter('wp_page_menu_args', [$this,  'filterPageMenuArgs']); // NOOP
            add_filter('home_url', [$this, 'filterHomeUrl']);
            add_filter('the_content', [$this, 'filterTheContent']);
            add_filter('get_custom_logo', [$this, 'getCustomLogo']);

            add_action('init', [$this, 'useCanonicalHostIfForwarded']);
        }

        function getCustomLogo($html) {
            $forwardingHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? null; 
            if (!$forwardingHost) return $html;
            $html = preg_replace('/\/blog/','',$html, 1);
            return $html;
        }

        function useCanonicalHostIfForwarded() {
            if (!SELF::USE_CANONICAL_HOST) return;

            $forwardingHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''; 
            if (SELF::CANONICAL_HOST == $forwardingHost) {
                return;
            }

            $originalUrl = SELF::original_url();
            $nonRedirectable = ['wp-admin','jetpack','wp-json','preview=true','customize'];
            foreach($nonRedirectable as $x) {
                if (strpos($originalUrl, $x) !== false) {
                    return;
                }    
            }

			$userAgent = $_SERVER['HTTP_USER_AGENT'];
			$nonRedirectableUserAgents=['Facebook','linked'];
            foreach($nonRedirectableUserAgents as $x) {
                if (strpos($userAgent, $x) !== false) {
                    return;
                }    
            }
			
			
            $_SERVER['HTTP_X_FORWARDED_HOST'] = SELF::CANONICAL_HOST;
            $canonicalUrl = SELF::rewriteUrlForForwarding($originalUrl);
			
            wp_redirect($canonicalUrl, SELF::USE_TEMPORARY_REDIRECT_FOR_CANONICAL_HOST ? 302 : 301);
            die();
        }


        private static function original_url() {
			$requestScheme = $_SERVER['REQUEST_SCHEME'] ?? 'https';
            return "$requestScheme://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]$_SERVER[QUERY_STRING]";
        }

        private static function build_url(array $elements) {
            $e = $elements;
            return
                (isset($e['host']) ? (
                    (isset($e['scheme']) ? "$e[scheme]://" : '//') .
                    (isset($e['user']) ? $e['user'] . (isset($e['pass']) ? ":$e[pass]" : '') . '@' : '') .
                    $e['host'] .
                    (isset($e['port']) ? ":$e[port]" : '')
                ) : '') .
                (isset($e['path']) ? $e['path'] : '/') .
                (isset($e['query']) ? '?' . (is_array($e['query']) ? http_build_query($e['query'], '', '&') : $e['query']) : '') .
                (isset($e['fragment']) ? "#$e[fragment]" : '')
            ;
        }

        private static function rewriteUrlForForwarding($url) {
            $forwardingHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? "";
            if ($forwardingHost == "") return $url;

            $linkComponents = parse_url($url);
            $linkHost = $linkComponents['host'] ?? "";
            if ($forwardingHost === $linkHost) return $url;
            if ($linkHost != $_SERVER["HTTP_HOST"]) return $url;

            $linkComponents['scheme'] = "https";
            $linkComponents['host'] = $forwardingHost;
            $linkComponents['path'] = '/blog' . ($linkComponents['path'] ?? '');
            $rewrittenUrl = SELF::build_url($linkComponents);
            return $rewrittenUrl;
        }

        /**
         * @param WP_Post[] $args
         */
        function filterNavMenuObjects($args) {
            foreach($args as $arg) {
                $arg->url = SELF::rewriteUrlForForwarding($arg->url);
            }
            return $args;
        }

        function filterPostLink($link) {
            return SELF::rewriteUrlForForwarding($link);
        }

        function filterPageMenuArgs($args) {
            return  $args;
        }

        function filterHomeUrl($url) {
            return SELF::rewriteUrlForForwarding($url);
        }

        function filterTheContent($subject) {
            $forwardingHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? "";
            if ($forwardingHost == "") return $subject;

            $replace = $forwardingHost . '/blog';
            $search =  $_SERVER["HTTP_HOST"];

            $modifiedContent = str_replace($search, $replace, $subject);
            return $modifiedContent;
        }
    }

    $_ = new AnemitoffWpProxiedPlugin();
}