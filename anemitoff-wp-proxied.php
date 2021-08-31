<?php
/*
 * Plugin name: Anemitoff WP Proxied
 * Description: When blog is accessed by proxy, links are rewritten to appear as though they belog to proxy origin
 * Version: 1.0
 * Author: Adam Nemitoff
 * Author URI: https://teamnemitoff.com
 * License: GPL v3
 */


if (!class_exists(AnemitoffWpProxiedPlugin::class)) {
    class AnemitoffWpProxiedPlugin {
        function __construct() {
            add_filter('post_link', [$this, 'updateLink']);
            add_filter('wp_nav_menu_objects', [$this, 'updateNavMenuLinks']);
        }

        static function build_url(array $elements) {
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

        /**
         * @param WP_Post[] $args
         */
        function updateNavMenuLinks($args) {
            if (!isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
                return $args;
            }
            $forwardingHost = $_SERVER['HTTP_X_FORWARDED_HOST'];
            foreach($args as $arg) {
                $url = $arg->url;
                $linkComponents = parse_url($url);

                $linkComponents['scheme'] = "https";
                $linkComponents['host'] = $forwardingHost;
                $linkComponents['path'] = '/blog' . $linkComponents['path'];
                $modifiedUrl = $this->build_url($linkComponents);
                $arg->url = $modifiedUrl;
            }
            return $args;
        }

        function updateLink($link) {
            $linkComponents = parse_url($link);
            $linkHost = $linkComponents['host'];
            $forwardingHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $linkHost;
            if ($forwardingHost == $linkHost) return $link;

            $linkComponents['host'] = $forwardingHost;
            $linkComponents['path'] = '/blog' . $linkComponents['path'];
            $modifiedLink = SELF::build_url($linkComponents);
            return $modifiedLink;
        }
    }

    $_ = new AnemitoffWpProxiedPlugin();
}