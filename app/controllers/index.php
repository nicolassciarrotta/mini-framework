<?php
	namespace App\Controllers;

	use \Render;

	class Index {

		public function __construct() {

		}
	

    public function getIndex() {
			$data = [
				'titulo'     => 'Ambrosía restobar',
			];
			return Render::interpolate(Render::view('index'), $data);
	}
	
	}