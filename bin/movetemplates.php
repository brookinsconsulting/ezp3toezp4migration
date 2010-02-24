#!/usr/bin/env php
<?php

if ( !function_exists( 'readline' ) )
{
    function readline( $prompt = '' )
    {
        echo $prompt . ' ';
        return trim( fgets( STDIN ) );
    }
}

require 'autoload.php';

$cli = eZCLI::instance();

/*
    general script settings

    array with the following keys:

    debug-message: false
    debug-output: false
    debug-include: false
    debug-levels: false
    debug-accumulator: false
    debug-timing: false
    use-session: false
    use-extensions: false
    use-modules: false
    user: false, or an array with the keys 'login' and 'password'
    description: 'eZ publish script',
    site-access: false
    min-version: false
    max-version: false

*/
$scriptSettings = array();
$scriptSettings['description'] = 'your description of this script comes here';
$scriptSettings['use-session'] = false;
$scriptSettings['use-modules'] = true;
$scriptSettings['use-extensions'] = true;

$script = eZScript::instance( $scriptSettings );
$script->startup();

/*
    script option configuration

    [optionname]

    value indications
    --------------------
    : -> must have a value
    ; -> can have a value

    quantity indications
    ----------------------
    ? -> min: 0; max: 1
    * -> min: 0, max: unbounded
    + -> min: 1, max: unbounded
*/
$config = '';

/*
    script argument configuration
*/
$argumentConfig = '';

/*
    script option help

    specify a hash with option identifiers as keys and their help text as values
*/
$optionHelp = false;

/*
    arguments
*/
$arguments = false;

/*
    standard options

    array( 'debug' => true,
          'colors' => true,
          'log' => true,
          'siteaccess' => true,
          'verbose' => true,
          'user' => false )
*/
$useStandardOptions = true;

$options = $script->getOptions( $config, $argumentConfig, $optionHelp, $arguments, $useStandardOptions );
$script->initialize();

$overrideINI = eZINI::instance( 'override.ini' );

// group by match file
$overridesByMatchFile = array();
foreach ( $overrideINI->groups() as $overrideName => $overrideConfig )
{
    $matchFile = $overrideConfig['MatchFile'];
    if ( !array_key_exists( $matchFile, $overridesByMatchFile ) )
    {
        $overridesByMatchFile[$matchFile] = array();
    }

    $overridesByMatchFile[$matchFile][$overrideName] = $overrideConfig;
}

$designBases = eZTemplateDesignResource::allDesignBases();

$cli->output( 'Design bases found: ' . count($designBases) );

foreach ( $designBases as $designBase )
{
    $cli->output( '' );
    $cli->output( '' );

    $cli->output( 'Design base: ' . $designBase );

    $cli->output( str_repeat( '-', 20 ) );

    $overridesPath = "$designBase/override/templates";
    $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $overridesPath ) );

    foreach ( $iterator as $file )
    {
        if ( !is_file($file->getRealPath()) )
        {
            continue;
        }

        // skip all files in .svn dir
        if ( strpos($file->getPathname(), DIRECTORY_SEPARATOR . '.svn' . DIRECTORY_SEPARATOR) !== false )
        {
            continue;
        }

        $unixPathName = str_replace( DIRECTORY_SEPARATOR, '/', $file->getPathname() );
        $path = substr($unixPathName, strlen( $overridesPath ) + 1 );
        if ( array_key_exists( $path, $overridesByMatchFile ) )
        {
            continue;
        }

        $cli->output($unixPathName);

        do {
            $action = false;
            $action = readline( "do you want to move this file to $designBase/templates ? (y|n|exit)" );
        } while ( !in_array( $action, array( 'y', 'n', 'exit' ) ) );

        if ( $action === 'y' )
        {
            $targetDir = "$designBase/templates/" . dirname( $path );
            eZDir::mkdir( $targetDir, false, true);
            rename( $file->getPathname(), "$targetDir/" . $file->getFilename() );
        }
        else if ( $action === 'exit' )
        {
            break 2;
        }
    }
}

$script->shutdown( 0 );

?>
