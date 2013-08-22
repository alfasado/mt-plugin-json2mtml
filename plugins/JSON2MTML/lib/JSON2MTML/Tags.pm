package JSON2MTML::Tags;
use strict;
use HTTP::Request::Common;
use LWP::UserAgent;
use JSON qw/decode_json/;
no warnings 'redefine';
use Data::Dumper;
{
    package Data::Dumper;
    sub qquote { return shift; }
}
$Data::Dumper::Useperl = 1;

sub _hdlr_json2mtml {
    my ( $ctx, $args, $cond ) = @_;
    my $app = MT->instance;
    my $api_version = $args->{ version };
    if (! $api_version ) {
        $api_version = $app->config( 'DataAPIVersion' );
    }
    my $instance_url = $args->{ instance };
    my $request = $args->{ request };
    if (! $instance_url ) {
        $instance_url = $app->config( 'DataAPIURL' );
    }
    my $api = "${instance_url}/${api_version}${request}";
    my $json;
    my $cache_file;
    my $fmgr = MT::FileMgr->new( 'Local' )
                or die MT::FileMgr->errstr;
    if ( my $cache_ttl = $args->{ cache_ttl } ) {
        if ( $cache_ttl eq 'auto' ) {
            $cache_ttl = $app->config( 'DataAPICacheTtl' );
        }
        my $cache_dir = $app->config( 'DataAPICacheDir' );
        require Digest::MD5;
        $cache_file = File::Spec->catfile( $cache_dir, Digest::MD5::md5_hex( $api ) );
        if ( $fmgr->exists( $cache_file ) ) {
            my $mtime = $fmgr->file_mod_time( $cache_file );
            my $time = time();
            if ( ( $time - $cache_ttl ) < $mtime ) {
                $json = $fmgr->get_data( $cache_file );
            }
        }
    }
    if (! $json ) {
        my $req = HTTP::Request->new( 'GET', $api );
        my $ua = LWP::UserAgent->new;
        my $res = $ua->request( $req );
        if ( $res->is_error ) {
            return '';
        }
        $json = $res->{ _content };
        if ( $cache_file ) {
            $fmgr->put_data( $json, $cache_file );
        }
    }
    if ( $args->{ raw_data } ) {
        return $json;
    }
    $json = decode_json( $json );
    if ( $args->{ debug } ) {
        my $res = '<pre>' . $api . ':';
        $res .= Dumper( $json );
        $res .= '</pre>';
        return $res;
    }
    my ( $code, $message );
    if ( my $error = $json->{ error } ) {
        $code = $error->{ code };
        $message = $error->{ message };
    } else {
        $json = $json->{ items };
    }
    my $res = '';
    my $vars = $ctx->{ __stash }{ vars } ||= +{};
    my $tokens = $ctx->stash( 'tokens' );
    my $builder = $ctx->stash( 'builder' );
    if (! $code && $json ) {
        $ctx->stash( 'json2mtmlitems', $json );
        my $i = 0;
        for my $record ( @$json ) {
            my $last = !defined @$json[ $i + 1 ];
            local $vars->{ __first__ }   = !$i;
            local $vars->{ __counter__ } = $i + 1;
            local $vars->{ __odd__ }     = $i % 2 ? 0 : 1;
            local $vars->{ __even__ }    = $i % 2;
            local $vars->{ __last__ }    = $last;
            for my $key ( keys ( %$record ) ) {
                my $value = $record->{ $key };
                $vars->{ lc( $key ) } = $value;
            }
            my $out = $builder->build( $ctx, $tokens, $cond );
            if ( !defined( $out ) ) { return $ctx->error( $builder->errstr ) };
            $res .= $out;
            $i++;
        }
        return $res;
    } else {
        $vars->{ code } = $code;
        $vars->{ message } = $message;
        my $out = $builder->build( $ctx, $tokens, $cond );
        if ( !defined( $out ) ) { return $ctx->error( $builder->errstr ) };
        return $out;
    }
}

sub _hdlr_vars_recurse {
    my ( $ctx, $args, $cond ) = @_;
    my $key = $args->{ key };
    my $record = $ctx->{ __stash }{ vars }->{ $key };
    my $tokens = $ctx->stash( 'tokens' );
    my $builder = $ctx->stash( 'builder' );
    for my $key ( keys ( %$record ) ) {
        my $value = $record->{ $key };
        $ctx->{ __stash }{ vars }->{ lc( $key ) } = $value;
    }
    my $out = $builder->build( $ctx, $tokens, $cond );
    if ( !defined( $out ) ) { return $ctx->error( $builder->errstr ) };
    return $out;
}

1;