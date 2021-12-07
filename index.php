<?php

	// --------------------------------------------------------------------------

	require('config.php');

	if (Config::$debug) {
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(E_ALL);
	} else {
		ini_set('display_errors', 0);
		ini_set('display_startup_errors', 0);
		error_reporting(0);
	}
	
	// --------------------------------------------------------------------------
	
	session_name('bares');
	session_start();
	
	
	
	// --------------------------------------------------------------------------

	require('mvc/dbal.php');
	require('mvc/render.php');
	require('mvc/router.php');
	require('mvc/logger.php');

	// --------------------------------------------------------------------------

	Dbal::$_conn['server'] = Config::$db_server;
	Dbal::$_conn['user']   = Config::$db_user;
	Dbal::$_conn['pass']   = Config::$db_pass;
	Dbal::$_conn['db']     = Config::$db_db;

	// --------------------------------------------------------------------------
	

	$req_uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
	$req_method = $_SERVER['REQUEST_METHOD'];

	
	// --------------------------------------------------------------------------

	try {
		echo Router::resolve($req_method, $req_uri);
	} catch(Exception $e) {
		$error = [
			'titulo'	=> 'Error',
			'codigo' 	=> base_convert(round(microtime(true) * 1000), 10, 32),
			'detalle' => 'Ocurrio un error inesperado, utilice el codigo para recibir soporte.'
		];

		Logger::error($e->getMessage(), $error['codigo']);
		echo Render::interpolate(Render::view('error'), $error);
	}