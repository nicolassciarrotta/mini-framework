<?php
	namespace App\Controllers;

	require_once('app/models/usuario.php');
	require_once('app/models/auth.php');
	

	use \Render;
	use \Router;
	use \Logger;
	use \App\Models\Auth;
	class Admin {

		public function __construct() {

		}

  	  public function getIndex($session, $params, $data) {
		$this->authenticate($session, "Anonimo intentando acceder al administrador", "getIndex");
			if ( empty($data) ) {
				$data = [];
			}
			$data =[
				'usuario' 		=> [ $session['usuario'] ],
				'titulo' 		=> '',
			];
			if (empty($data['mensajes']) ) {
				$data['mensajes'] = [];
			}
			
			return Render::interpolate(Render::view('admin/index'), $data);
    }

		// ------------------------------ LOGIN ------------------------------------------

    	public function getLogin($session) {
			$data = [ 'mensajes' => [],
			'titulo' => 'Login' ];
			Auth::setCurrentUser($session, null);
			return Render::interpolate(Render::view('admin/login'), $data);
    	}

    	public function postLogin(&$session, $params, $files) {
			$data = [ 'mensajes' => [],
			'titulo' => 'Login'];
			$usuario = new \App\Models\Usuario();

			if ( $usuario->login($params['user'], $params['pass']) ) {
				Auth::setCurrentUser($session, $usuario);
				return Router::redirect('/admin/index');
			} else {
				Logger::error("Login fallido ({$params['user']})");
				array_push($data['mensajes'], [
					'style' => 'error',
					'mensaje' => 'Usuario/Clave incorrectos.'
				]);
			}
			return Render::interpolate(Render::view('admin/login'), $data);
		}
	
		public function getLogout($session) {
			Auth::destroy();
			return Router::redirect('/admin/index');
		}

		private function authenticate($session,$messageLog,$position){
			if (!Auth::isAdminLogged($session) ) {
				Logger::error($messageLog."($position)");
				return Router::redirect('/admin/login');
			}
		}

		
	}