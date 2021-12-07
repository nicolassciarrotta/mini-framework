<?php
 class Dbal implements arrayaccess, iteratoraggregate, countable, serializable
 {
	/*/
     * Constantes de errores, pueden traducirse a cualquier idioma.
	 *
	 * @since 1.9.0
	/*/
    const ERROR_CONNECTION = "No se pudo conectar a la base de datos.";
    const ERROR_RELATION_2_TABLES = "No se pudo establecer relacion entre %1\$s y %2\$s.";
    const ERROR_RELATION_3_TABLES = "No se pudo establecer relacion entre %1\$s y %2\$s utilizando como nexo a %3\$s.";
    const ERROR_PREPARE_STMT = "Error %1\$n preparando la sentencia. %2\$s.";
    const ERROR_BIND_PARAM = "Error al agregar los parametros a la sentencia.";
    const ERROR_EXECUTE_STMT = "Error %1\$n ejecutando la sentencia. %2\$s.";
    const ERROR_COUNT_PK_VALUES = "La pk tiene %1\$s valor(es), pero se especifico %2\$s.";

	/*/
     * Nombre de la base de datos que se usara.
	 *
	 * @since 1.9.0
	 * @var String
	/*/
	public static $_conn = ["server" => "", "user" => "", "pass" => "", "db" => ""];

	/*/
     * Indica si se deben loguear las consultas realizadas a la base.
     *
	 * @since 1.9.0
	/*/
    public static $log_querys = false;

	/*/
     * Conexion Mysqli abierta.
	 *
	 * @since 1.9.0
	 * @var Mysqli
	/*/
	private static $_instance = null;


	/*/
     * Nombre de la tabla principal con la que se trabajara.
	 *
	 * @since 1.9.0
	 * @var String
	/*/
	private $_table = "";

	/*/
     * Las claves primarias de la tabla.
	 *
	 * @since 1.9.0
	 * @var Array
	/*/
	protected $_pk = [];

	/*/
     * Las claves foraneas de la tabla.
	 *
	 * @since 1.9.0
	 * @var Array
	/*/
	protected $_fk = [];

	/*/
     * Sql con las partes de una consulta.
	 *
	 * @since 1.9.0
	 * @var Array
	/*/
	private $blocks = [];

	/*/
     * Referencias a parametros.
	 *
	 * @since 1.9.0
	 * @var Array
	/*/
	private $params = [];

	/*/
     * Ultima sentencia preparada, lista para ser ejecutada.
	 *
	 * @since 1.9.0
	 * @var Mysqli_stmt
	/*/
	private $_stmt = null;

	/*/
     * Variables que usan los metodos para comunicarse internamente entre ellos.
	 *
	 * @since 1.9.0
	 * @var Array
	/*/
	private $_dialog = [];

	/*/
     * Array cuyos indices son los campos de la tabla.
	 *
	 * @since 1.9.0
	 * @var Array
	/*/
	public $props = [];

	/*/
     * Arreglo que contiene los result de un SELECT.
	 *
	 * @since 1.9.0
	 * @var Array
	/*/
	public $result = null;

	/*/
     * Metodos para el manejo de cache, tambien pueden usarse publicamente para guardar y recuperar datos.
	 *
	 * @since 1.9.0
	/*/
    public static function setCache($name, $params)
    {
        return file_put_contents(__DIR__."/cache/".md5($name), serialize($params));
    }

    public static function getCache($name, &$params = null)
    {
        return ($params = unserialize(file_get_contents(__DIR__."/cache/".md5($name))));
    }

    public static function unsetCache($name)
    {
        return unlink(__DIR__."/cache/".md5($name));
    }

    public static function existsCache($name)
    {
        return file_exists(__DIR__."/cache/".md5($name));
    }

	/*/
     * Acceso a las propiedades de la tabla por medio de la interface arrayAccess.
	 *
	 * @since 1.9.0
	 * @link http://www.php.net/manual/en/class.arrayaccess.php
	/*/
	public function offsetGet   ($offset)         { return (array_key_exists($offset, $this->props) ? $this->props[$offset] : null); }
	public function offsetSet   ($offset, $value) { if(array_key_exists($offset, $this->props)) $this->props[$offset] = $value;      }
	public function offsetExists($offset)         { return array_key_exists($offset, $this->props);                                  }
	public function offsetUnset ($offset)         { if(array_key_exists($offset, $this->props)) $this->props[$offset] = null;        }

	/*/
     * Iteracion de los resultados de la ultima consulta por medio de la interface IteratorAggregate.
	 *
	 * @since 1.9.0
	 * @link http://www.php.net/manual/en/class.iteratoraggregate.php
	/*/
    public function getIterator() { return new ArrayIterator($this->result); }

	/*/
     * Acceso a las propiedades de la tabla por medio de sobrecarga.
	 *
	 * @since 1.9.0
	 * @link http://www.php.net/manual/en/language.oop5.overloading.php
	/*/
	public function __get($offset) 			{ return (array_key_exists($offset, $this->props) ? $this->props[$offset] : null); }
	public function __set($offset, $value) 	{ if(array_key_exists($offset, $this->props)) $this->props[$offset] = $value;      }
	public function __isset($offset)        { return array_key_exists($offset, $this->props);                                  }
	public function __unset($offset)        { if(array_key_exists($offset, $this->props)) $this->props[$offset] = null;        }

	/*/
     * Retorna algunos atributos del objeto actual serializados. Estos atributos podran utilizarse con unserialize.
     *
	 * @since 1.9.0
	 * @link http://www.php.net/manual/en/class.serializable.php
	/*/
    public function serialize() { return serialize(["_table" => $this->_table, "_pk" => $this->_pk, "props" => $this->props, "result" => $this->result]); }

	/*/
     * Al unserializar se restauran los valores previamente almacenados y se llama al constructor.
	 *
	 * @since 1.9.0
	 * @link http://www.php.net/manual/en/class.serializable.php
	/*/
    public function unserialize($data)
    {
        $this->__construct();
        $data = unserialize($data);
        $this->_table = $data["_table"];
        $this->_pk    = $data["_pk"];
        $this->props  = $data["props"];
        $this->result = $data["result"];

        return $this;
    }

	/*/
     * Alias magico de Dbal::table($table).
	 *
	 * @since 1.9.0
	 * @link http://www.php.net/manual/en/language.oop5.magic.php#object.invoke
	/*/
    public function __invoke($table) { return $this->table($table); }

	/*/
     * Retorna una lista html con información sobre la tabla actual.
	 *
	 * @since 1.9.0
	 * @link http://www.php.net/manual/en/language.oop5.magic.php#object.tostring
	/*/
    public function __tostring()
    {
        return "<dl class=\"Dbal_resume\">
                  <dt>DB</dt>
                  <dd>".self::$_conn["db"]."</dd>
                  <dt>Tabla</dt>
                  <dd>".$this->_table."</dd>
                  <dt>PK</dt>
                  <dd>
                    <table>
                        <tr><td>".implode("</td><td>", $this->_pk)."</td></tr>
                    </table>
                  </dd>
                  <dt>Propiedades</dt>
                  <dd>
                    <table>
                        <tr><th>".implode("</th><th>", array_keys($this->props))."</th></tr>
                        <tr><td>".implode("</td><td>", array_values($this->props))."</td></tr>
                    </table>
                  </dd>
                  <dt>Resultados</dt>
                  <dd>
                    <table>
                        <tr><th>".implode("</th><th>", array_keys((array) $this->result[0]))."</th></tr>
                        <tr><td>".implode("</td></tr><tr><td>", array_map(function($res){ return implode("</td><td>",$res); }, $this->result))."</td></tr>
                    </table>
                  </dd>
                </dl>";
    }

	/*/
     * Constructor de la clase, lleva encapsulados los datos de conexión por defecto.
	 *
	 * @since 1.9.0
	 * @param string  $table Tabla de la base de datos a utilizar.
	 * @return Dbal
	/*/
	public function __construct($table = false)
	{
		if(is_null(self::$_instance))
		{
            self::$_instance = new mysqli(self::$_conn["server"], self::$_conn["user"], self::$_conn["pass"], self::$_conn["db"]);
			if(self::$_instance->connect_errno)
				throw new Exception(self::ERROR_CONNECTION);
			else
				self::$_instance->set_charset("utf8");
		}

        if($table)
            $this->table($table);

		return $this;
	}

	/*/
     * Define la tabla principal de trabajo.
	 *
	 * @since 1.9.0
	 * @param  string  $table Tabla de la base de datos a utilizar.
	 * @return Dbal
	/*/
 	public function table($table)
	{
		$this->props = [];
        $this->_pk = [];
        $this->_table = $table;

        $this->rawQuery("DESCRIBE $table;", false, true);
        foreach($this->result as $row)
        {
            $this->props[$row["Field"]] = $row["Default"];
            if($row["Key"] == "PRI")
                $this->_pk[] = $row["Field"];
        }

        $this->_fk = $this->_fkLoad($table);

        return $this->flush();
	}

	/*/
     * Limpia las propiedades y deja a la clase lista para realizar consultas nuevas.
	 *
	 * @since 1.9.0
	 * @return Dbal
     *
     * @TODO: El flush de propiedades debe poner los valores por defecto en vez de en null.
	/*/
	public function flush($section = null)
	{
		if($this->_stmt)
        {
            unset($this->_dialog["query"]);
            $this->_stmt = !$this->_stmt->close();
        }

        if(is_null($section))
        {
            $this->props = array_fill_keys(array_keys($this->props), null);
            $this->blocks = ["from" => $this->_table];
            $this->result = [];
            $this->params = [];
            $this->_dialog = [];
        }
        else
            unset($this->blocks[$section], $this->params[$section]);

		return $this;
	}

	/*/
     * Importa desde un conjunto de valores aquellos que coincidan con las propiedades de la tabla.
	 *
	 * @since 1.9.0
	 * @return Dbal
	/*/
    public function import($values)
    {
        foreach(array_intersect_key($values, $this->props) as $key => $val)
            $this->props[$key] = $val;
            return $this;
        
    }

	/*/
     * Verifica si el objeto actual tiene una pk definida.
	 *
	 * @since 1.9.0
	 * @return Dbal
	/*/
    public function issetpk()
    {
        $defined = true;
        foreach($this->_pk as $pk)
        {
            if(!$this->props[$pk])
            {
                $defined = false;
                break;
            }
        }

        return $defined;
    }

	/*/
     * Cargar las claves foraneas de una tabla.
	 *
	 * @since 1.9.0
     * @param string $table Tabla de la que se quieren obtener las claves foraneas.
	 * @return Array
     *
     * @TODO: Hacer que este metodo sea static
	/*/
    private function _fkLoad($table)
    {
        $this->flush();
        $relations = $this->exec("SELECT
                       ie.column_name AS `from`,
                       ie.referenced_table_name AS `table`,
                       ie.referenced_column_name AS `field`
                     FROM
                       information_schema.KEY_COLUMN_USAGE AS ie
                     WHERE
                       ie.referenced_table_name IS NOT NULL
                       AND ie.table_name = ?
                       AND ie.table_schema = ?"
                 , [$table, self::$_conn["db"]], true);
        $fkeys = [];
        foreach($relations as $relation)
            $fkeys[$relation["table"]] = ["from" => $relation["from"], "field" => $relation["field"]];

        return $fkeys;
    }

	/*/
     * Obtiene el objeto extrangero relacionado con el actual.
     * Este metodo sirve para los siguientes casos considerando a TA como la tabla actual y a TO como la tabla objetivo:
     * a) Relaciones n-1 Ej: TA[TO.key, ...], TO[key, ...]
     * b) Relaciones 1-1 Ej: TA[TO.key=key, ...], TO[TA.key=key, ...]
	 *
	 * @since 1.9.0
	 * @param string          $ftable   Tabla de datos extrangeros objetivo.
	 * @param array    | null $fields   Campos de interes de la tabla objetivo.
	 * @return Dbal
	/*/
    public function foreign($ftable, $fields = null)
    {
        if(!isset($this->_fk[$ftable]))
            throw new Exception(sprintf(self::ERROR_RELATION_2_TABLES, $this->_table, $ftable));

        $foreign = new Dbal($ftable);
        if($fields)
        {
            foreach($fields as $field)
                $foreign->field($field);
        }
        $foreign->join($this->_table)->on($ftable.".".$this->_fk[$ftable]["field"], "=", $this->_table.".".$this->_fk[$ftable]["from"]);

		$foreign->where($ftable.".".$this->_fk[$ftable]["field"], (is_null($this->props[$this->_fk[$ftable]["from"]]) ? "IS NULL" : "="), $this->props[$this->_fk[$ftable]["from"]]);

        return $foreign->first();
    }

	/*/
     * Obtiene informacion de objetos extrangeros pudiendo utilizar una tabla como nexo.
     * Este metodo sirve para los siguientes casos considerando a TA como la tabla actual, TN como la tabla nexo y a TO como la tabla objetivo:
     * a) Relaciones n-n con tabla intermedia Ej: TA[key, ...], TN[TA.key, TO.key], TO[key, ...]
     * b) Relaciones 1-n Ej: TA[key, ...], TO[TA.key, ...]
	 *
	 * @since 1.9.0
	 * @param string          $ftable   Tabla de datos extrangeros objetivo.
	 * @param string   | null $ntable   Tabla nexo intermedia.
	 * @param array    | null $fields   Campos de interes de la tabla objetivo.
	 * @return Dbal
	/*/
    public function foreigns($ftable, $ntable = null, $fields = null)
    {
        $foreign = new Dbal($ftable);

        if(is_null($ntable))
        {
            $nFKeys = $this->_fkLoad($ftable);

            if(!isset($nFKeys[$this->_table]))
                throw new Exception(sprintf(self::ERROR_RELATION_2_TABLES, $ftable, $this->_table));
        }
        else
        {
            $nFKeys = $this->_fkLoad($ntable);

            if(!isset($nFKeys[$this->_table]) || !isset($nFKeys[$ftable]))
                throw new Exception(sprintf(self::ERROR_RELATION_3_TABLES, $ftable, $this->_table, $ntable));

            $foreign->join($ntable)->on($nFKeys[$ftable]["field"], "=", $nFKeys[$ftable]["from"]);
        }

        $foreign->join($this->_table)->on($nFKeys[$this->_table]["from"], "=", $nFKeys[$this->_table]["field"]);

        if($fields)
        {
            foreach($fields as $field)
                $foreign->field($field);
        }

        return $foreign->select();
    }

	/*/
     * Ejecuta una consulta SQL con los parametros proporcionados.
	 *
	 * @since 1.9.0
	 * @param String 	     $query  Consulta SQL.
	 * @param Array          $params Parametros a bindear.
	 * @param Bool           $cache  Indica si el resultado de la consulta sera cacheada.
	 * @return Dbal
	/*/
	public function rawQuery($query, $params = [], $cache = false)
	{
        if(isset($this->_dialog["query"]) && ($this->_dialog["query"] == "rawQuery"))
            $this->result = $this->exec();
        else
        {
            if($this->_stmt)
                $this->_stmt->close();

            $this->result = $this->exec($query, $params, $cache);

            $this->_dialog["query"] = "rawQuery";
        }

		return $this;
	}

	/*/
     * Realiza una consulta SELECT previamente definida.
	 *
	 * @since 1.9.0
	 * @param int 	   $rows  Numero de filas a traer, 0 = todas.
	 * @param int 	   $page  Numero de pagina.
	 * @param bool     $cache Indica si la consulta sera cacheada.
	 * @param bool     $config Configuraciones opcionales para agregarle a la consulta.
	 * @return Dbal
	/*/
	public function select($rows = 0, $page = 1, $cache = false, $config = [])
	{
        if($rows)
        {
            $this->offset((($page-1) * $rows));
            $this->limit($rows);
        }
        else
            $this->limit(null);

        if(isset($this->_dialog["query"]) && ($this->_dialog["query"] == "select"))
            $this->result = $this->exec();
        else
        {
            if(isset($this->blocks["fields"]))
            {
                $fields = " ";
                foreach($this->blocks["fields"] as $field => $alias)
                    $fields .= "$field $alias, ";

                $fields = rtrim($fields, ", ");
            }
            else
                $fields = " ".$this->_table.".*";

            // Si la tabla soporta bajas logicas, y no se esta utilizando este campo en las condiciones, por defecto no trae las eliminadas.
            if(array_key_exists("delete_at", $this->props) && (!isset($this->blocks["where"]) || (strpos($this->blocks["where"], "delete_at") === false)))
                $this->where($this->_table.".delete_at", "IS NULL");
                
            $this->result = $this->exec("SELECT ".implode(" ", $config).$fields
                                        ." FROM "
                                        .(isset($this->blocks["from"])   ? $this->blocks["from"]   : "")
                                        .(isset($this->blocks["where"])  ? $this->blocks["where"]  : "")
                                        .(isset($this->blocks["group"])  ? $this->blocks["group"]  : "")
                                        .(isset($this->blocks["having"]) ? $this->blocks["having"] : "")
                                        .(isset($this->blocks["order"])  ? $this->blocks["order"]  : "")
                                        .(isset($this->blocks["limit"])  ? $this->blocks["limit"]  : ""),
                                        array_merge(
                                           (isset($this->params["fields"]) ? $this->params["fields"] : [])
                                         , (isset($this->params["table"])  ? $this->params["table"]  : [])
                                         , (isset($this->params["where"])  ? $this->params["where"]  : [])
                                         , (isset($this->params["having"]) ? $this->params["having"] : [])
                                         , (isset($this->params["limit"])  ? $this->params["limit"]  : [])
                                        ), $cache);

            $this->_dialog["query"] = "select";
        }

		return $this;
	}
    
	/*/
     * Alias amigables de Dbal::select();
	 *
	 * @since 1.9.0
	 * @param int 	   $rows  Numero de filas a traer, 0 = todas.
	 * @param int 	   $page  Numero de pagina.
	 * @param bool     $cache Indica si la consulta sera cacheada.
	 * @return Dbal
	/*/
	public function selectDistinct($rows = 0, $page = 1, $cache = false) { return $this->select($rows, $page, $cache, ["DISTINCT"]);            }
	public function selectCalcRows($rows = 0, $page = 1, $cache = false) { return $this->select($rows, $page, $cache, ["SQL_CALC_FOUND_ROWS"]); }

	/*/
     * Carga el primer registro que coincida con las condiciones previamente definidas.
     *
	 * @since 1.9.0
	 * @param bool  $cache Indica si los valores seran cacheados o no.
	 * @return Dbal
	/*/
	public function first($cache = false)
    {
        $this->select(1, 1, $cache);

        foreach($this->props as $key => &$value)
            $value = (isset($this->result[0][$key]) ? $this->result[0][$key] : null);

        $this->result = [];

        return $this;
    }

	/*/
     * Retorna el valor de una celda del conjunto de resultados, la columna puede ser indicada por posicion o por nombre.
     * Por defecto retorna el primer valor encontrado.
     *
	 * @since 1.9.0
	 * @param mixed $col Nro de columna, empezando por 1 o el nombre.
	 * @param int   $row Nro de fila, empezando por 1.
	 * @param bool  $cache Indica si el valor sera cacheado o no.
	 * @return mixed
	/*/
    public function cell($col = 1, $row = 1, $cache = false)
    {
        $this->select(1, $row, $cache);
        return (is_numeric($col) ? array_values($this->result[0])[$col-1] : $this->result[0][$col]);
    }

	/*/
     * Encuentra el registro que se corresponde con la clave primaria proporcionada.
     *
	 * @since 1.9.0
	 * @param mixed $pk Valor(es) de la clave primaria, si es mas de uno, el orden importa.
	 * @return Dbal
	/*/
	public function find($pk) { return $this->wherePk($pk)->first(); }

	/*/
     * Verifica si existen registros que se correspondan con la clave primaria proporcionada.
     *
	 * @since 1.9.0
	 * @param mixed Valor(es) de la clave primaria, si es mas de uno, el orden importa.
	 * @return int  Cantidad de registros coincidentes.
	/*/
	public function exist($values) { return count($this->wherePk($values)); }

	/*/
     * Si la ultima consulta fue cacheada, al llamar a este metodo los datos de la cache seran actualizados por los actuales.
     * Esto puede ser util para cachear valores calculados con logica propia.
	 *
	 * @since 1.9.0
	 * @return Dbal
	/*/
    public function updateCache()
    {
        if($this->_dialog["cache"])
            self::setCache($this->_dialog["cache"], $this->result);

        return $this;
    }

	/*/
     * Realiza llamadas a procedimientos almacenados de Mysql.
	 *
	 * @since 1.9.0
	 * @param string   $proc    Nombre del procedimiento.
	 * @param array    $params  Parametros a utilizar.
	 * @return Dbal
     *
     * @TODO: Permitir que los parametros de OUT regresen con valores.
	/*/
	public function call($proc, $params = [])
    {
        if(isset($this->_dialog["query"]) && $this->_dialog["query"] == "call")
            $this->exec();
        else
        {
            $this->exec("CALL $proc(".rtrim(str_repeat("?, ", count($params)), ", ").")", $params, false);
            $this->_dialog["query"] = "call";
        }

        return $this;
    }

	/*/
     * Define una variable mysql para la proxima sentencia.
	 *
	 * @since 1.9.0
	 * @param string   $var    Nombre de la variable.
	 * @param array    $value  Valor inicial.
	 * @return Dbal
	/*/
	public function setVar($var, $value = 0)
    {
        self::$_instance->query("SET $var := $value;");
        return $this;
    }

	/*/
     * Ejecuta una sentencia preparada o prepara y ejecuta una nueva.
	 *
	 * @since 1.9.0
	 * @param String 	     $query  Consulta SQL.
	 * @param Array          $params Parametros a bindear.
	 * @param Bool           $cache  Indica si el resultado de la consulta sera cacheada.
	 * @return Array
     *
     * @TODO: Hacer este metodo estatico
	/*/
	public function exec($query = "", $params = [], $cache = false)
	{
        if(self::$log_querys)
        {
            $arch = fopen(__DIR__."/Dbal_querys.log", "a+");
            fwrite($arch, "[".date("Y-m-d H:i:s")." $_SERVER[REMOTE_ADDR]] $query -> (".implode(" | ", ($params ? $params : ["Sin parametros"])).")\n");
            fclose($arch);
        }

        if($query && $this->_stmt)
            $this->_stmt = !$this->_stmt->close();

        if($cache)
        {
			$this->_dialog["cache"] = $query;

			if($params)
			{
				foreach($params as $value)
					$this->_dialog["cache"] = substr_replace($this->_dialog["cache"], $value, strpos($this->_dialog["cache"], "?"), 1);
			}

			if(self::existsCache($this->_dialog["cache"]))
				return self::getCache($this->_dialog["cache"]);
        }

		if(!$this->_stmt)
		{
			if(!($this->_stmt = self::$_instance->prepare($query)))
                throw new Exception(sprintf(self::ERROR_PREPARE_STMT, self::$_instance->errno, self::$_instance->error." (SQL = \"$query\")"));
			elseif($params)
			{
				$bind_params = array("");
				foreach($params as &$param)
				{
					switch(gettype($param))
					{
						case "integer": case "boolean": $bind_params[0] .= "i"; break;
						case "double" : $bind_params[0] .= "d"; break;
						case "blob"   : $bind_params[0] .= "b"; break;
						default       : $bind_params[0] .= "s"; break;
					}

					$bind_params[] = &$param;
				}

				if(!call_user_func_array(array($this->_stmt, "bind_param"), $bind_params))
                    throw new Exception(self::ERROR_BIND_PARAM);
			}
		}

		if(!$this->_stmt->execute())
            throw new Exception(sprintf(self::ERROR_EXECUTE_STMT, self::$_instance->error, self::$_instance->errno));
		else
		{
			$result = [];
			if($registros = $this->_stmt->get_result())
			{
                while($registro = $registros->fetch_array(MYSQLI_ASSOC))
                    if(isset($this->_dialog["index_by"]))
                        $result[$registro[$this->_dialog["index_by"]]] = $registro;
                    else
                        $result[] = $registro;
			}
		}

        if($cache)
            self::setCache($this->_dialog["cache"], $result);

		return $result;
	}

    public function index_by($column)
    {
        $this->_dialog["index_by"] = $column;
        return $this;
    }

	/*/
     * Llama a una funcion de mysql con los parametros proporcionados y retorna el resultado.
	 *
	 * @since 1.9.0
	 * @param String $type   Nombre de la funcion a ajecutar.
	 * @param String $field  Campo que sera pasado a la funcion.
	 * @param Bool   $cache  Indica si la consulta sera cacheada.
	 * @return Mixed
     *
     * @TODO: Permitir parametros multiples y bindeo de valores.
	/*/
    public function fnc($type, $field, $cache)
    {
        if(isset($this->_dialog["query"]) && $this->_dialog["query"] == "function $type($field)")
            $value = array_values($this->exec()[0])[0];
        else
        {
            $this->limit(null);
            // Si la tabla soporta bajas logicas, y no se esta utilizando este campo en las condiciones, por defecto no trae las eliminadas.
            if(array_key_exists("delete_at", $this->props) && (!isset($this->blocks["where"]) || (strpos($this->blocks["where"], "delete_at") === false)))
                $this->where($this->_table.".delete_at", "IS NULL");

            $value = $this->exec("SELECT $type($field) fnc FROM "
                            .(isset($this->blocks["from"])   ? $this->blocks["from"]   : "")
                            .(isset($this->blocks["where"])  ? $this->blocks["where"]  : "")
                            .(isset($this->blocks["group"])  ? $this->blocks["group"]  : "")
                            .(isset($this->blocks["having"]) ? $this->blocks["having"] : ""),
                            array_merge(
                               (isset($this->params["table"])  ? $this->params["table"]  : [])
                             , (isset($this->params["where"])  ? $this->params["where"]  : [])
                            ), $cache)[0]["fnc"];

            $this->_dialog["query"] = "function $type($field)";
        }

        return $value;
    }

	/*/
     * Alias amigables de Dbal::fnc();
	 *
	 * @since 1.9.0
	 * @param String $field  Campo que sera pasado a la funcion.
	 * @param Bool   $cache  Indica si la consulta sera cacheada.
	 * @return Dbal
	/*/
	public function count($field = "*", $cache = false) { return $this->fnc('count', $field, $cache); }
	public function min($field, $cache = false)         { return $this->fnc('min',   $field, $cache); }
	public function max($field, $cache = false)         { return $this->fnc('max',   $field, $cache); }
	public function sum($field, $cache = false)         { return $this->fnc('sum',   $field, $cache); }
	public function avg($field, $cache = false)         { return $this->fnc('avg',   $field, $cache); }

	/*/
     * Inserta un registro en la tabla, si la PK ya existe, puede actualizar los campos o reemplazar el registro.
     *
	 * @since 1.9.0
	 * @param string $which  Tipo de sentencia a ajecutar.
	 * @param array  $update Campos que seran actualizados.
	 * @return int | null
	/*/
	private function register($which, $update = false)
	{
        if(isset($this->_dialog["query"]) && $this->_dialog["query"] == $which.($update ? " UPDATE" : ""))
            $this->exec();
        else
        {
            if(isset($this->blocks["fields"]))
                $keys = array_keys($this->blocks["fields"]);
            else
                $keys = array_diff(array_keys($this->props), ["create_at", "update_at", "delete_at"]);

            $data = [];
            foreach($keys as $key)
                $data[$key] = &$this->props[$key];
            unset($keys);

            if($which == "INSERT INTO" && array_key_exists("create_at", $this->props))
            {
                $fecha = date("Y-m-d H:i:s");
                $data["create_at"] = &$fecha;
            }

            if($update)
            {
                $aditional = " ON DUPLICATE KEY UPDATE ";
                if(array_key_exists("update_at", $this->props))
                    $aditional .= "update_at = NOW(), ";
                $update = array_diff($update, $this->_pk, ["create_at", "update_at", "delete_at"]);

                foreach($update as $field)
                {
                    if(is_array($field))
                    {
                        $key = array_keys($field)[0];
                        if(!isset($this->blocks["fields"]) || array_key_exists($key, $this->blocks["fields"]))
                            $aditional .= $key." = ".$field[$key].", ";
                    }
                    elseif(!isset($this->blocks["fields"]) || array_key_exists($field, $this->blocks["fields"]))
                        $aditional .= "$field = VALUES($field), ";
                }

                $aditional = rtrim($aditional, ", ");
            }

            $this->exec("$which ".$this->_table
                        ." (".implode(", ", array_keys($data)).") VALUES"
                        ." (".rtrim(str_repeat("?, ", count($data)), ", ").")"
                        .(isset($aditional) ? $aditional : "")
                        , $data);

            $this->_dialog["query"] = $which.($update ? " UPDATE" : "");
        }

		return $this->_stmt->insert_id;
	}

	/*/
     * Alias amigables de Dbal::register();
	 *
	 * @since 1.9.0
	 * @param array $update Campos que seran actualizados. Ej: ['d', ['e' => 'e+1'], ['f' => 'f+?', 5], ['g' => 'g*?', $a], ['h' => '[h]*?', $b]]
	 * @return Dbal
	/*/
    public function insert() { return $this->register("INSERT INTO"); }
    public function replace() { return $this->register("REPLACE INTO"); }
    public function insertIgnore() { return $this->register("INSERT IGNORE INTO"); }
    public function insertUpdate($update) { return $this->register("INSERT INTO", $update); }
    public function save() { return $this->register("INSERT INTO", array_keys($this->props)); }

	/*/
     * Actualiza registros en la tabla.
	 * @since 1.9.0
	 * @param int $rows Cantidad maxima de registros a actualizar.
	 * @return int
	/*/
	public function update($rows = null)
	{
        if(isset($this->_dialog["query"]) && $this->_dialog["query"] == "update")
            $this->exec();
        else
		{
            // Si no hay condiciones, filtra por la clave primaria y actualiza solo 1 registro.
            if(!isset($this->blocks["where"]) && $this->issetpk())
            {
                foreach($this->_pk as $field)
                    $this->where($field, "=", $this->props[$field]);

                $this->limit(1);
            }
            else
                $this->limit($rows);

            $uFields = "";
            $uValues = [];
            if(isset($this->blocks["fields"]))
            {
                if(array_key_exists("update_at", $this->props) && !array_key_exists("delete_at", $this->blocks["fields"]) && !array_key_exists("update_at", $this->blocks["fields"]))
                {
                    $this->blocks["fields"]["update_at"] = "update_at";
                    $this->props["update_at"] = date("Y-m-d H:i:s");
                }

                foreach($this->blocks["fields"] as $field => $value)
                {
                    if($field == $value)
                    {
                        $uFields .= ($uFields ? ", " : "")."$field = ?";
                        $uValues[] = &$this->props[$field];
                    }
                    else
                        $uFields .= ($uFields ? ", " : "")."$field = $value";
                }

                if(isset($this->params["fields"]))
                    $uValues = array_merge($uValues, $this->params["fields"]);
            }
            else
            {
                if(array_key_exists("update_at", $this->props))
                    $this->props["update_at"] = date("Y-m-d H:i:s");
                foreach(array_diff(array_keys($this->props), $this->_pk, ["create_at", "delete_at"]) as $field)
                {
                    $uFields .= ($uFields ? ", " : "")."$field = ?";
                    $uValues[] = &$this->props[$field];
                }
            }

            $this->exec("UPDATE "
                        .$this->blocks["from"]
                        ." SET $uFields"
                        .$this->blocks["where"]
                        .(isset($this->blocks["limit"]) ? $this->blocks["limit"] : ""),
                        array_merge($uValues, $this->params["where"], (isset($this->params["limit"]) ? $this->params["limit"] : []))
                       );

            $this->_dialog["query"] = "update";
		}

		return $this->_stmt->affected_rows;
	}

    public function affected_rows()
    {
        return $this->_stmt->affected_rows;
    }

	/*/
     * Borra registros en la tabla.
	 * @since 1.9.0
	 * @param int $rows Cantidad maxima de registros a borrar.
	 * @return int
	/*/
	public function delete($rows = null)
	{
        // Si el campo existe se realiza una baja logica.
        if(array_key_exists("delete_at", $this->props))
        {
            unset($this->params["fields"]);
            $this->blocks["fields"] = ["delete_at" => "delete_at"];
            $this->props["delete_at"] = date("Y-m-d H:i:s");

            return $this->update();
        }

        if(isset($this->_dialog["query"]) && $this->_dialog["query"] == "delete")
            $this->exec();
        else
		{
            if(!isset($this->blocks["where"]) && $this->issetpk())
            {
                foreach($this->_pk as $field)
                    $this->where($field, "=", $this->props[$field]);

                $this->limit(1);
            }
            else
                $this->limit($rows);

            $this->exec("DELETE FROM "
                        .$this->blocks["from"]
                        .$this->blocks["where"]
                        .(isset($this->blocks["limit"]) ? $this->blocks["limit"] : "")
                        ,array_merge($this->params["where"], (isset($this->params["limit"]) ? $this->params["limit"] : [])));

            $this->_dialog["query"] = "delete";
		}

		return $this->_stmt->affected_rows;
	}

	/*/
     * Define un campo a seleccionar en la proxima consulta, puede ser una funcion y llevar parametros.
     *
	 * @since 1.9.0
	 * @param string | array $fields nombre de campo a seleccionar. ['p.nombre' => 'nombre_perro']
	 * @param array          $params parametros involucrados a bindear.
	 * @return Dbal
	/*/
	public function field($field, $params = [])
	{
        if(is_array($field))
            $this->blocks["fields"][array_keys($field)[0]] = array_values($field)[0];
        else
            $this->blocks["fields"][$field] = $field;

        if($params)
        {
            foreach($params as &$param)
                $this->params["fields"][] = &$param;
        }

		return $this;
	}

	/*/
     * Define una lista de campos a seleccionar en la proxima consulta.
     *
	 * @since 1.9.0
	 * @params string
	 * @return Dbal
	/*/
	public function fields()
	{
        foreach(func_get_args() as $field)
        {
            if(is_array($field))
                $this->blocks["fields"][array_keys($field)[0]] = array_values($field)[0];
            else
                $this->blocks["fields"][$field] = $field;
        }

		return $this;
	}

	/*/
     * Metodos para agregar relaciones entre tablas a la proxima consulta.
     *
	 * @since 1.9.0
	 * @param string | array  $table Nombre de la tabla, puede usarse un alias enviandolo como array.
	 * @param string | array  $using Campo(s) comunes para realizar la union.
	 * @param string          $type  Tipo de la union.
	 * @return Dbal
	/*/
	private function joiner($table, $using, $type)
    {
        $this->blocks["from"] .= (is_array($table) ? " $type ".array_keys($table)[0]." ".array_values($table)[0] : " $type $table");
        if($using)
            $this->blocks["from"] .= " USING (".(is_array($using) ? implode(", ",$using) : $using).") ";

        $this->_dialog["from"] = true; 
        $this->_dialog["on"] = true;

		return $this;
	}
	public function join        ($table, $using = false) { return $this->joiner($table, $using, "INNER JOIN");    }
	public function crossJoin   ($table, $using = false) { return $this->joiner($table, $using, "CROSS JOIN");    }
	public function leftJoin    ($table, $using = false) { return $this->joiner($table, $using, "LEFT JOIN");     }
	public function rightJoin   ($table, $using = false) { return $this->joiner($table, $using, "RIGHT JOIN");    }
	public function naturalJoin ($table, $using = false) { return $this->joiner($table, $using, "NATURAL JOIN");  }
	public function straightJoin($table, $using = false) { return $this->joiner($table, $using, "STRAIGHT_JOIN"); }
	public function andJoin     ($table)                 { return $this->joiner($table, false , ",");             }

	/*/
     * Metodos para agregar condiciones a las relaciones entre tablas.
     *
	 * @since 1.9.0
	 * @param string $from   Nombre del campo de origen.
	 * @param string $rel    Tipo de relacion.
	 * @param string $to     Nombre del campo de destino.
	 * @param string $joiner Union de condiciones.
	 * @return Dbal
	/*/
	private function oner($from, $rel, $to, $joiner, $bind)
	{
        if(isset($this->_dialog["on"]))
        {
            $this->blocks["from"] .= " ON ";
            unset($this->_dialog["on"], $this->_dialog["from"]);
        }
        
        $this->cond($from, $rel, $to, $joiner, "from", $bind);

		return $this;
	}
	public function on   ($from, $rel = null, $to = null, $bind = false) { return $this->oner($from, $rel, $to, "AND", $bind); }
	public function orOn ($from, $rel = null, $to = null, $bind = false) { return $this->oner($from, $rel, $to, "OR", $bind);  }
	public function xorOn($from, $rel = null, $to = null, $bind = false) { return $this->oner($from, $rel, $to, "XOR", $bind); }

	/*/
     * Metodos para agregar condiciones a una consulta.
	 * @since 1.9.0
	 * @param string         $prop    La propiedad a condicionar.
	 * @param string         $rel     La relacion a utilizar.
	 * @param string | array $val     Valores a utilizar en la condicion.
	 * @param string         $joiner  Tipo de union entre parametros.
	 * @return Dbal
	/*/
	private function cond($prop, $rel, $values, $joiner, $cond, $bind = true)
	{
        if(isset($this->_dialog["query"]))
            unset($this->_dialog["query"]);

        if(!isset($this->blocks[$cond]) || !$this->blocks[$cond])
        {
            $this->blocks[$cond] = " ".strtoupper($cond)." ";
            unset($this->_dialog[$cond]);
        }

        if($prop == ")")
        {
            $this->blocks[$cond] .= " ) ";
            $this->_dialog[$cond] = true;
        } else {
            if(isset($this->_dialog[$cond]))
                $this->blocks[$cond] .= $joiner;

            if($prop == "(")
            {
                $this->blocks[$cond] .= " ( ";
                unset($this->_dialog[$cond]);
            }
            else
            {
                switch($rel)
                {
                    case "BETWEEN" :
                        $this->blocks[$cond] .= " $prop BETWEEN ? AND ? ";
                        $this->params[$cond][] = &$values[0];
                        $this->params[$cond][] = &$values[1];
                    break;
                    case "IS BETWEEN" :
                        $this->blocks[$cond] .= " ? BETWEEN $values[0] AND $values[1] ";
                        $this->params[$cond][] = &$prop;
                    break;
                    case "RAW BETWEEN" :
                        $this->blocks[$cond] .= " $prop BETWEEN $values[0] AND $values[1] ";
                    break;
                    case "IN" :
                        if(is_array($values))
                        {
                            if($values)
                            {
                                $this->blocks[$cond] .= " $prop IN (";
                                $this->blocks[$cond] .= rtrim(str_repeat("?, ", count($values)), ", ");
                                foreach($values as &$val)
                                    $this->params[$cond][] = &$val;
                                $this->blocks[$cond] .= ") ";
                            }
                            else
                                $this->blocks[$cond] .= " FALSE ";
                        } else {
                            $this->blocks[$cond] .= " $prop = ? ";
                            $this->params[$cond][] = &$values;
                        }
                    break;
                    case "IS NULL" :
                    case "IS NOT NULL" :
                        $this->blocks[$cond] .= " $prop $rel ";
                    break;
                    default :
                        if($bind)
                        {
                            $this->blocks[$cond] .= " $prop $rel ? ";
                            if(is_array($values))
                                $this->params[$cond][] = &$values[0];
                            else
                                $this->params[$cond][] = &$values;
                        }
                        else
                            $this->blocks[$cond] .= " $prop $rel $values ";
                    break;
                }
                $this->_dialog[$cond] = true;
            }
        }

		return $this;
	}
	public function where    ($prop, $rel = null, $val = null, $bind = true) { return $this->cond($prop, $rel, $val, "AND", "where", $bind); }
	public function orWhere  ($prop, $rel = null, $val = null, $bind = true) { return $this->cond($prop, $rel, $val, "OR" , "where", $bind); }
	public function xorWhere ($prop, $rel = null, $val = null, $bind = true) { return $this->cond($prop, $rel, $val, "XOR", "where", $bind); }

	/*/
     * Crea una agrupamiento para la proxima consulta.
	 * @since 1.9.0
	 * @param string $field nombre(s) de campo(s) para agrupar.
	 * @return Dbal
	/*/
	public function group($field)
	{
        if(isset($this->blocks["group"]))
            $this->blocks["group"] .= ", $field";
        else
            $this->blocks["group"] = " GROUP BY $field";

		return $this;
	}
	public function having   ($prop, $rel = null, $val = null) { return $this->cond($prop, $rel, $val, "AND", "having"); }
	public function orHaving ($prop, $rel = null, $val = null) { return $this->cond($prop, $rel, $val, "OR" , "having"); }
	public function xorHaving($prop, $rel = null, $val = null) { return $this->cond($prop, $rel, $val, "XOR", "having"); }

	/*/
     * Condiciona a un valor de pk especifico.
     *
	 * @since 1.9.0
	 * @params mixed $values
	 * @return Dbal
	/*/
    public function wherePk($values)
    {
        if(!is_array($values))
            $values = [$values];

        if(count($this->_pk) != count($values))
            throw new Exception(sprintf(self::ERROR_COUNT_PK_VALUES, count($this->_pk), count($values)));

        foreach($this->_pk as $nro => $field)
            $this->cond($this->_table.".$field", "=", $values[$nro], "AND", "where");

        return $this;
    }

	/*/
     * Estos metodos definen el rango de resultados a traer de las consultas.
     *
	 * @since 1.9.0
	 * @param int
	 * @return Dbal
	/*/
    public function limit($limit)
    {
        if(is_null($limit))
            unset($this->params['limit'], $this->blocks['limit']);
        else
        {
            if(!isset($this->blocks['limit']))
                $this->blocks['limit'] = " LIMIT ?";

            if(is_array($limit))
                $this->params['limit'][0] = &$limit[0];
            else
                $this->params['limit'][0] = $limit;

            ksort($this->params['limit']);
        }

        return $this;
    }

    public function offset($offset)
    {
        $this->blocks['limit'] = " LIMIT ? OFFSET ?";

        if(is_array($offset))
            $this->params['limit'][1] = &$offset[0];
        else
            $this->params['limit'][1] = $offset;

        ksort($this->params['limit']);

        return $this;
    }

	/*/
     * Getter de las claves primarias.
     *
	 * @since 1.9.0
	 * @return Array
	/*/
    public function getPk() { return $this->_pk; }

	/*/
     * Este metodo agrega campos para ordenar los resultados.
	 * @since 1.9.0
	 * @param string   $field Nombre del campo a ordenar.
	 * @param string   $type  Indica si el orden es ascendente o descendente.
	 * @return Dbal
     * @TODO: Permitir bindeo de parametros para orden personalizado.
	/*/
	public function sort($field, $type = "ASC")
	{
        if(isset($this->blocks["order"]))
            $this->blocks["order"] .= ", $field $type";
        else
            $this->blocks["order"] = " ORDER BY $field $type";

		return $this;
	}

	/*/
     * Activa/Desactiva el uso de transacciones.
	 * @since 1.9.0
	 * @param bool     $on Indica si se esta en una transaccion.
	 * @return Dbal
	/*/
	public function transaction($on = true)
	{
        if($on)
        {
            $this->exec("START TRANSACTION");
            self::$_instance->autocommit(false);
        }
        else
            self::$_instance->autocommit(true);

		return $this;
	}

	/*/
     * Realiza un commit manual de las sentencias actuales.
	 * @since 1.9.0
	 * @param mixed     $sp Indica si se confirmara hasta un savepoint determinado o toda la transaccion.
	 * @return Dbal
	/*/
	public function commit($sp = false)
	{
		if($sp)
			$this->exec("RELEASE SAVEPOINT $sp");
		else
			self::$_instance->commit();

		return $this;
	}

	/*/
     * Realiza un rollback manual de las sentencias actuales.
	 * @since 1.9.0
	 * @param bool     $sp Indica si se revertira hasta un savepoint determinado o toda la transaccion.
	 * @return Dbal
	/*/
	public function rollback($sp = false)
	{
		if($sp)
			$this->exec("ROLLBACK TO $sp");
		else
			self::$_instance->rollback();

		return $this;
	}

	/*/
     * Crea un savepoint en la transaccion actual.
	 * @since 1.9.0
	 * @return Dbal
	/*/
	public function savepoint($sp)
	{
		$this->exec("SAVEPOINT $sp");

		return $this;
	}

    /*/
     * Destructor. Borra los datos del objeto.
     *
	 * @since 1.9.0
	/*/
    public function __destruct()
    {
        if($this->_stmt)
            $this->_stmt->close();
    }

 }