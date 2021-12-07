<?php 

	class Router {
		
		public static function resolve($method, $uri) {
			$method = strtolower($method);
			
			if ($uri == '') {
				$uri = 'index';
			}
			
			if (strpos($uri, '/') === false) {
				$uri .= '/index';
			}
			
			if ( strpos($uri, '.') !== false ) {
				throw new Exception("Posible path injection ($uri) ($method).");
			}
			
			$uri = explode('/', $uri);			
			$controller = __DIR__.'/../app/controllers/'.$uri[0].'.php';
			
			if ( !file_exists($controller) ) {
				throw new Exception("El controlador no existe ($controller) ($method).");
			}
			
			require_once($controller);
			
      $controller = "App\\Controllers\\{$uri[0]}";
			
			if ( !class_exists($controller) ) {
				throw new Exception("El controlador existe. ($controller) ($method).");
			}
			
      $controller = new $controller();
						
			$params = [];
			$files 	= [];
			
			switch ($method) {
				case 'get':
					$params = $_GET;
					break;
					
				case 'post':
					$params = $_POST;
					$files = $_FILES;
					break;
			}
			
			$name = array_shift($uri);
			$method .= implode('', array_map('ucfirst', $uri));
			
			if ( !method_exists($controller, $method) ) {
				throw new Exception("El controlador no responde a la accion. ($name->$method).");
			}
			
			return $controller->{$method}($_SESSION, $params, $files);
		}		
		
		public static function redirect($path) {
			header("Location:".$path);
		}
	}