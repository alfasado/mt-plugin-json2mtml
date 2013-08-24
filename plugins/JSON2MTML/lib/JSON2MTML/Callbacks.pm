package JSON2MTML::Callbacks;
use strict;
use File::Find;

sub _cb_post_change {
    my ( $cb, $params ) = @_;
    my $types = MT->config( 'DataAPICachedObjectTypes' );
    if (! $types ) {
        return;
    }
    if ( my $cols = $params->column_values ) {
        if ( my $object_type = $cols->{ object_type } ) {
            my @check = split( /,/, $types );
            if (! grep( /^$object_type$/, @check ) ) {
                return;
            }
            my $cache_dir = MT->config( 'DataAPICacheDir' );
            require MT::FileMgr;
            my $fmgr = MT::FileMgr->new( 'Local' ) or die MT::FileMgr->errstr;
            my @wantedFiles;
            File::Find::find( sub{ my $file = $_;
                                   my $cache = $File::Find::name;
                                   push ( @wantedFiles, $cache )
                                          if $file =~ m/^${object_type}\./; },
                              $cache_dir );
            for my $cache ( @wantedFiles ) {
                if ( $fmgr->exists( $cache ) ) {
                    $fmgr->delete( $cache );
                }
            }
        }
    }
}

1;