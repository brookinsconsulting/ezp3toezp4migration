#!/usr/bin/env php
<?php

require 'autoload.php';

$cli = eZCLI::instance();

$scriptSettings = array();
$scriptSettings['description'] = 'Find includes and requires in PHP files, using the tokenizer';
$scriptSettings['use-session'] = true;
$scriptSettings['use-modules'] = true;
$scriptSettings['use-extensions'] = true;

$script = eZScript::instance( $scriptSettings );
$script->startup();

$config = '';
$argumentConfig = '[path]';
$optionHelp = false;
$arguments = false;

$useStandardOptions = true;

$options = $script->getOptions( $config, $argumentConfig, $optionHelp, $arguments, $useStandardOptions );
$script->initialize();

$path = count( $options['arguments'] ) > 0 ? $options['arguments'][0] :  '.';

$exclusionFilter = array();
//$exclusionFilter = array( "@^{$path}/(var|settings|benchmarks|autoload|port_info|templates|tmp|UnitTest|tests|extension)/@" );
$phpFiles = ezcFile::findRecursive( $path, array( '@\.php$@' ), $exclusionFilter );

foreach( $phpFiles as $file )
{
    $tokens = @token_get_all( file_get_contents( $file ) );
    foreach ( $tokens as $key => $token )
    {
        if ( is_array( $token ) )
        {
            switch( $token[0] )
            {
                case T_INCLUDE_ONCE:
                case T_INCLUDE:
                case T_REQUIRE_ONCE:
                case T_REQUIRE:
                {
                    // make sure we store cross-platform file system paths,
                    // using a forward slash as directory separator
                    if ( DIRECTORY_SEPARATOR != '/' )
                    {
                        $file = str_replace( DIRECTORY_SEPARATOR, '/', $file );
                    }

                    $parts = array( $file, $token[2], $token[1] );

                    // lookup the path we are including
                    $path = array();
                    $nextTokenKey = $key + 1;
                    while ( true )
                    {
                        $nextToken = $tokens[$nextTokenKey];

                        if ( is_array( $nextToken ) )
                        {
                            //var_dump( $nextToken );
                            $found = false;
                            switch ( $nextToken[0] )
                            {
                                case T_CONSTANT_ENCAPSED_STRING:
                                {
                                    $path[] = $nextToken[1];
                                } break;

                                case T_VARIABLE :
                                {
                                    $path[] = 'variable';
                                } break;
                            }
                        }
                        else if ( $nextToken == ';' )
                        {
                            break;
                        }

                        $nextTokenKey++;
                    }

                    $parts[] = implode( ' - ', $path );

                    $cli->output( implode( ';', $parts ) );
                } break;
            }
        }
    }
}

$script->shutdown( 0 );

?>