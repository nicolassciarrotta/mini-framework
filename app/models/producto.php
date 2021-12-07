<?php
	namespace App\Models;

	require_once('app/models/categoria.php');
	use \Dbal;
	use \App\Models\Categoria;
	
	class Producto {

		private $db;

		public function validate() {
			$errors = [];
			
			if(empty(trim($this->db->categoria))){
				array_push($errors, "Ingrese una categoria");
			}
			
			if(empty(trim($this->db->nombre))){
				array_push($errors, "Ingrese un nombre del producto");
			}
			
			if(empty(trim($this->db->precio))){
				array_push($errors, "Ingrese un precio del producto");
			}
			

			return $errors;
		}

		public function __construct() {
			$this->db = new Dbal('PRODUCTO');
		}

		public function import($params) {
			$this->db->flush();
			$this->db->import($params);
		}

		public function save() {
			if (empty($this->db->id)) {
				$this->db->id = 0;
			}
			
			if ( count($this->validate()) > 0 ) {
				throw new \Exception('Errores de validacion en los datos a insertar.');
			}
			
			$this->db->id = $this->db->save();
		}

		public function delete($id) {
			$this->db->flush();
			$this->db->where('id', '=', $id);
			$this->db->delete();
		}

		public function get($id) {
			$this->db->flush();
			$this->db->find($id);
			
			return $this->db->props;
		}

		public function getAllWC($id) {
			$this->db->flush();
			$this->db->where('categoria', '=', $id);
			$this->db->select();
			return $this->db->result;
		}

		public function getAll() {
			$this->db->flush();
			$this->db->sort('categoria');
			$this->db->select();		
			return $this->db->result;
		}		

		public function alterateCategory($productos){
			$categoria = New Categoria();
			$categorias = $categoria->getAll();
			for($i=0; $i<count($productos); $i++){
				for($j=0; $j<count($categorias); $j++){
					if($productos[$i]['categoria'] == $categorias[$j]['id']){
						$productos[$i]['categoria'] = $categorias[$j]['nombre'];
					}
				}
			}
			return $productos;
		}
	}