<?php
	namespace App\Models;
	
	class Auth {
		
		public static function setCurrentUser(&$session, $user) {			
			if (is_null($user)) {
				unset($session['usuario']);
			} else {
				$session['usuario'] = [
					'id'     => $user->getId(),
					'nombre' => $user->getNombre()
				];				
			}
		}
		
		public static function isAdminLogged(&$session) {			
			return !empty($session['usuario']) && !empty($session['usuario']['id']);
		}

		public static function destroy(){
			return session_destroy();
		}

	}