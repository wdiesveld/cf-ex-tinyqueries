<?php

/**
 * Setup script TinyQueries
 *
 * This script is invoked during setup of the server by the script .extensions/tinyqueries/extensions.py 
 *
 * @author wouter@tinyqueries.com
 */

/**
 * Checks if the config file is up to date, e.g. has DB-credentials. If not, adds the credentials from the VCAP_SERVICES env var
 * Additionally sets the TinyQueries api_key and projectLabel and sends the url of this app to the tinyqueries server
 * to enable publishing of queries to the app.
 *
 */
function setup()
{
	// Get credentials from Bluemix env var and app var
	$services 		= getEnvJson("VCAP_SERVICES");
	$application 	= getEnvJson("VCAP_APPLICATION");
	$dbcred 		= getDBcred($services);
	$tqcred 		= getTQcred($services);
	
	// Initializes config.xml
	initConfigFile($dbcred, $tqcred);
		
	// Send the publish_url to TQ
	sendPublishUrl($tqcred, $application);
	
	// Initialize the sample database
	initSampleDB($dbcred);
	
	// Ensure the sample TQ project is ready to be used
	compileSampleProject();
}

/**
 * Initializes config.xml
 *
 */
function initConfigFile($dbcred, $tqcred)
{
	// Read config file
	$configFile = dirname(__FILE__) . '/config.xml';

	$config = @file_get_contents( $configFile );
	
	if (!$config)
		throw new Exception('Cannot read config file');
		
	// Check if config file is already initialized
	if (strpos($config, '{driver}') === false)
		return;

	// Fill in template vars
	$config = str_replace('{driver}', 	$dbcred['driver'], 	$config);	
	$config = str_replace('{host}', 	$dbcred['hostname'], $config);	
	$config = str_replace('{name}', 	$dbcred['name'], 	$config);	
	$config = str_replace('{user}', 	$dbcred['username'], $config);	
	$config = str_replace('{password}', $dbcred['password'], $config);	
	
	$config = str_replace('{api_key}', 		$tqcred['api_key'], 		$config);	
	$config = str_replace('{projectLabel}', $tqcred['projectLabel'], 	$config);	
	
	$r = @file_put_contents( $configFile, $config );
	
	if (!$r)
		throw new \Exception("Cannot write configfile $configFile");
}

/**
 * Uses curl to send the url of this app to the tinyqueries server
 *
 */
function sendPublishUrl($tqcred, $application)
{
	$errorPublishURL = ' - you need to set publish-URL in TinyQueries manually';
		
	// Add publish_url which is needed for the TQ IDE to know where to publish the queries	
	if (!array_key_exists('uris', $application))
		throw new \Exception('Application URI not found' . $errorPublishURL);
	
	// This will be sent to tinyqueries
	$curlBody = array();	
		
	$protocol = (!array_key_exists('HTTPS', $_SERVER) || !$_SERVER['HTTPS']) ? 'http://' : 'https://';
	$curlBody['activeBinding']['publish_url']	= $protocol . $application['uris'][0] . '/api/';	
	$curlBody['activeBinding']['label']			= $tqcred['bindingLabel'];
		
	// Init curl
	$ch = curl_init();

	if (!$ch) 
		throw new \Exception( 'Cannot initialize curl' . $errorPublishURL );
		
	// Set options
	curl_setopt($ch, CURLOPT_HEADER, true); 		// Return the headers
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	// Return the actual reponse as string
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curlBody));
	curl_setopt($ch, CURLOPT_URL, 'https://compiler1.tinyqueries.com/api/clients/projects/' . $tqcred['projectLabel'] . '/?api_key=' . $tqcred['api_key']);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // nodig omdat er anders een ssl-error is; waarschijnlijk moet er een intermediate certificaat aan curl worden gevoed.
	curl_setopt($ch, CURLOPT_HTTPHEADER,array('Expect:')); // To disable status 100 response 
	
	// Execute the API call
	$raw_data = curl_exec($ch); 
	
	if ($raw_data === false) 
		throw new \Exception('Did not receive a response from tinyqueries.com' . $errorPublishURL );
	
	// Split the headers from the actual response
	$response = explode("\r\n\r\n", $raw_data, 2);
		
	// Find the HTTP status code
	$matches = array();
	if (preg_match('/^HTTP.* ([0-9]+) /', $response[0], $matches)) 
		$status = intval($matches[1]);

	if ($status != 200)
		throw new \Exception('Received status code ' . $status . ': ' . $response[1] . $errorPublishURL);

	curl_close($ch);
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
	require_once( dirname(__FILE__) . '/../libs/TinyQueries/TinyQueries.php' );
	
	$configFile = dirname(__FILE__) . '/config.xml';
	$compiler 	= new TinyQueries\Compiler( $configFile );
	
	$compiler->compile();
}

/**
 * Fetch DB credentials from env var
 *
 */
function getDBcred(&$services)
{
	foreach ($services as $id => $service)
	{
		switch ($id)
		{
			case 'cleardb': 
				$dbcred = $service[0]['credentials'];
				$dbcred['driver'] = 'mysql';
				return $dbcred;
		}
	}
	
	throw new \Exception("Cannot find an (appropriate) SQL database service in VCAP_SERVICES");
}

/**
 * Fetch TinyQueries credentials from env var
 *
 */
function getTQcred(&$services)
{
	if (!array_key_exists('tinyqueries', $services))
		throw new \Exception("Cannot find an TinyQueries credentials in VCAP_SERVICES");
		
	return $services['tinyqueries'][0]['credentials'];
}

/**
 * Get env var content and parse as json
 *
 */
function getEnvJson($varname)
{
	$value = getenv($varname);
	
	if (!$value)
		throw new \Exception("No $varname found");
	
	return json_decode($value, true);
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

