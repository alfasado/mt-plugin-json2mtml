<?php
/*
The MIT License (MIT)

Copyright (c) 2013 Alfasado,Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

class JSON2MTML extends MTPlugin {

    var $registry = array(
        'name' => 'JSON2MTML',
        'id'   => 'JSON2MTML',
        'key'  => 'json2mtml',
        'author_name' => 'Alfasado Inc.',
        'author_link' => 'http://alfasado.net/',
        'version' => '0.1',
        'description' => 'JSON to MTML using Movable Type data api.',
        'config_settings' => array(
            'DataAPIURL' => array( 'default' => '' ),
            'DataAPIVersion'  => array( 'default' => 'v1' ),
        ),
        'tags' => array(
            'block'    => array( 'json2mtml' => '_hdlr_json2mtml',
                                 'varsrecurse' => '_hdlr_vars_recurse', ),
        ),
    );

    function _hdlr_json2mtml ( $args, $content, &$ctx, &$repeat ) {
        $localvars = array( 'json2mtmlitems', 'json2mtmltotalsize',
                            'json2mtmlcounter' );
        $app = $ctx->stash( 'bootstrapper' );
        if (! isset( $content ) ) {
            $ctx->localize( $localvars );
            $api_version = $app->config( 'DataAPIVersion' );
            $instance_url = $args[ 'instance' ];
            $request = $args[ 'request' ];
            if (! $instance_url ) {
                $instance_url = $app->config( 'DataAPIURL' );
            }
            $api = "${instance_url}/${api_version}${request}";
            $curl = curl_init();
            curl_setopt( $curl, CURLOPT_URL, $api );
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
            $buf = curl_exec( $curl );
            if ( curl_errno( $curl ) ) {
                $repeat = FALSE;
                return '';
            }
            curl_close( $curl );
            $json = json_decode( $buf, TRUE );
            if ( $args[ 'debug' ] ) {
                echo '<pre>' . $api . ':';
                var_dump( $json );
                echo '</pre>';
            }
            if ( $error = $json[ 'error' ] ) {
                $ctx->__stash[ 'vars' ][ 'code' ] = $error[ 'code' ];
                $ctx->__stash[ 'vars' ][ 'message' ] = $error[ 'message' ];
            } else {
                $json = $json[ 'items' ];
                $total = count( $json );
                $ctx->stash( 'json2mtmlitems', $json );
                $ctx->stash( 'json2mtmltotalsize', $total );
                $ctx->stash( 'json2mtmlcounter', 0 );
                $counter = 0;
            }
        } else {
            $json = $ctx->stash( 'json2mtmlitems' );
            $counter = $ctx->stash( 'json2mtmlcounter' );
            $total = $ctx->stash( 'json2mtmltotalsize' );
        }
        if ( $json ) {
            if ( $total ) {
                if ( $counter < $total ) {
                    $obj = $json[ $counter ];
                    if (! $counter ) {
                        $ctx->__stash[ 'vars' ][ '__first__' ] = 1;
                    } else {
                        $ctx->__stash[ 'vars' ][ '__first__' ] = 0;
                    }
                    foreach ( $obj as $key => $value ) {
                        $ctx->__stash[ 'vars' ][ $key ] = $value;
                        $ctx->__stash[ 'vars' ][ strtolower( $key ) ] = $value;
                    }
                    $counter++;
                    $ctx->__stash[ 'vars' ][ '__counter__' ] = $counter;
                    $ctx->__stash[ 'vars' ][ '__odd__' ]     = ( $counter % 2 ) == 1;
                    $ctx->__stash[ 'vars' ][ '__even__' ]    = ( $counter % 2 ) == 0;
                    if ( $total == $counter ) {
                        $ctx->__stash[ 'vars' ][ '__last__' ] = 1;
                    }
                    $repeat = TRUE;
                } else {
                    $ctx->restore( $localvars );
                    $repeat = FALSE;
                }
                $ctx->stash( 'json2mtmlcounter', $counter );
            }
        }
        return $content;
    }

    function _hdlr_vars_recurse ( $args, $content, &$ctx, &$repeat ) {
        $key = $args[ 'key' ];
        $vars = $ctx->__stash[ 'vars' ][ $key ];
        if ( (! $vars ) || (! is_array( $vars ) ) ) {
            $repeat = FALSE;
            return '';
        }
        foreach ( $vars as $key => $val ) {
            $ctx->__stash[ 'vars' ][ $key ] = $val;
            $ctx->__stash[ 'vars' ][ strtolower( $key ) ] = $val;
        }
        return $content;
    }
}

?>