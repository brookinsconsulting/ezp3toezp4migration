#!/usr/bin/env php
<?php

require 'autoload.php';

$cli = eZCLI::instance();

$scriptSettings = array();
$scriptSettings['description'] = 'Find constants in PHP files and replaces them based on replacement values in a CSV file';
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

$replacements = array();

$handle = @fopen( 'new_constants.csv', 'r' );

if ( !$handle )
{
    $script->shutdown( 1, 'Unable to open CSV file new_constants.csv' );
}

$headers = fgetcsv( $handle, 1000, ',' );

while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false )
{
    $oldConstant = $data[1];
    $newConstant = $data[2];

    if ( $newConstant != '' );
    {
        $replacements[$oldConstant] = $newConstant;
    }
}
fclose($handle);

$exclusionFilter = array();
$phpFiles = ezcFile::findRecursive( $path, array( '@\.php$@' ), $exclusionFilter );
$updatedFileCount = 0;

foreach( $phpFiles as $file )
{
    $cli->output( $file );
    $storeRequired = false;

    $tokens = @token_get_all( file_get_contents( $file ) );

    foreach ( $tokens as $key => $token )
    {
        if ( is_array( $token ) )
        {
            switch( $token[0] )
            {
                case T_STRING:
                {
                    if ( !isset( $tokens[$key - 1] ) ||
                         !is_array( $tokens[$key - 1] ) ||
                         !in_array( $tokens[$key - 1][0], array( T_DOUBLE_COLON, T_OBJECT_OPERATOR ) ) )
                    {
                        if ( array_key_exists( $token[1], $replacements ) )
                        {
                            $storeRequired = true;
                            $tokens[$key][1] = $replacements[$token[1]];
                        }
                    }
                } break;
            }
        }
    }

    if ( $storeRequired )
    {
        $contents = '';
        foreach ( $tokens as $token )
        {
            if ( is_array( $token ) )
            {
                $contents .= $token[1];
            }
            else
            {
                $contents .= $token;
            }
        }
        file_put_contents( $file, $contents );
        $updatedFileCount++;
    }
}

$cli->output( 'updated files: ' . $updatedFileCount );

$script->shutdown( 0 );

?>