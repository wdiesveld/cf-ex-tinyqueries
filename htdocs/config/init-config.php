<?php
/**
 * Setup script TinyQueries
 *
 * This script is invoked during setup of the server by the script .extensions/tinyqueries/extensions.py 
 *
 * @author wouter@tinyqueries.com
 */
 
require_once( dirname(__FILE__) . '/../libs/TinyQueries/SetupCloudFoundry.php' );
require_once( dirname(__FILE__) . '/../libs/TinyQueries/TinyQueries.php' );

/**
 * Runs the setup for CloudFoundry (see class SetupCloudFoundry).
 * Additionally initializes a sample database and compiles the sample tinyqueries project.
 *
 */
function setup()
{
	// Run CloudFoundry setup
	list($dbcred, $tqcred) = TinyQueries\SetupCloudFoundry::run();
	
	// Initialize the sample database
	initSampleDB($dbcred);
	
	// Ensure the sample TQ project is ready to be used
	compileSampleProject();
}

/**
 * Sets up a sample database
 *
 * @param assoc $dbcred The DB credentials 
 */
function initSampleDB($dbcred)
{
	$sql = @file_get_contents( dirname(__FILE__) . '/../../sample-db/classicmodels.v3.0.sql' );
	
	if (!$sql)
		throw new Exception('Cannot read sample DB file');
		
	$dsn = $dbcred['driver'] . ":dbname=" . $dbcred['name'] . ";host=" . $dbcred['hostname'];
	$pdo = new PDO($dsn, $dbcred['username'], $dbcred['password']);
		
	// throw exception for each error
	$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	
	$sth = $pdo->prepare($sql);
	
	$r = $sth->execute();

	if (!$r) 
	{
		$error = $sth->errorInfo();
		if ($error && is_array($error) && count($error)>=3)
			throw new \Exception($error[1] . " - " . $error[2]);
		throw new \Exception('unknown error during execution of query');
	}	
}

/**
 * Ensure the sample TQ project is ready to be used
 *
 */
function compileSampleProject()
{
	$configFile = dirname(__FILE__) . '/config.xml';
	$compiler 	= new TinyQueries\Compiler( $configFile );
	
	$compiler->compile();
}

/**
 * Run the setup
 *
 */
try
{
	setup();
}
catch (Exception $e)
{
	echo "Error during setup TinyQueries: " . $e->getMessage() . "\n";
	exit(1);
}

echo "TinyQueries setup complete\n";
exit(0);

