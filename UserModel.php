<?php
    require("/server/www/.base/config.php");
    /*$d_root = isset($c_dir) ? $c_dir : $_SERVER['DOCUMENT_ROOT'];

    if( !defined('API_VERSION') ) {
        require("/server/www/.base/config.php");
        require("$d_root/config.php");
    }*/
    
    function headersJSON(){
        ob_start ("ob_gzhandler");
        header("Content-type: application/json");
        header("charset: utf-8");
        header("Cache-Control: no-cache");
        header("Expires: -1");
    }
    
    function isAjax(){ return isset($_SERVER["HTTP_X_REQUESTED_WITH"]); }
    
    function isAsyncView(){ return isset($_SERVER["HTTP_X_VIEW"]); }
    
    function ajaxOnly(){
        if( !isAjax() ) {
            headersJSON();
            die(json_encode(array("error" => "unauthorized")));
        }
    }
    
    function isPOST() { return $_SERVER['REQUEST_METHOD'] == "POST"; }
    
    function replaceKeys($str,$keys,$patt = "/\\\${([\\-\\w\\.]+)}/"){
        //if( !is_string($patt) ) $patt = "/\\\${([\\-\\w\\.]+)}/";
        return preg_replace_callback($patt,function($coincidencias) use ($keys) {
            if( isset($coincidencias[1]) ) {
                $key = $coincidencias[1];
                if( preg_match("/\./",$key) ) {
                    $path = explode(".",$key);
                    $in_keys = $keys;
                    
                    for( $k = 0; $k < count($path) ; $k++ ) {
                        if( !isset($in_keys[$path[$k]]) ) return $coincidencias[0];
                        $in_keys = $in_keys[$path[$k]];
                    }
                    return "$in_keys";
                    
                } else if( is_string($keys[$key]) ) return $keys[$key];
                return $coincidencias[0];
            } else return $coincidencias[0];
        },$str);
    }
    
    $_templates = array();
    function template($name,$value){
        if(isset($value)) {
            $_templates[$name] = $value;
            $template = $value;
        } else {
            if( isset($_templates[$name]) ) $template = $_templates[$name];
            else {
                if(isset($name)) {
                    $_templates[$name] = file_get_contents($_SERVER['DOCUMENT_ROOT']."/templates/$name.html");
                    $template = $_templates[$name];
                } else return file_get_contents($_SERVER['DOCUMENT_ROOT']."/templates/layout.html");
            }
        }
        return is_string($template) ? $template : "";
    }
    
    function i18n($env,$lang) {
        $i18n = [];
        
        if( isset($env) && $env != "default" ) {
            if( !SITE_DEBUG ) $i18n = apc_fetch("i18n-$lang-$env");
            if( !$i18n ) {
                $msql = new ModelSQL();
                $i18n = $msql->model('_i18n')->getMap("key",$lang,[ "where" => "env = '$env'" ]);
            }
                
        } else {
            if( !SITE_DEBUG ) $i18n = apc_fetch("i18n-$lang-default");
            if( !$i18n ) {
                $msql = new ModelSQL();
                $i18n = $msql->model('_i18n')->getMap("key",$lang,[ "where" => "env IS NULL" ]);
            }
        }
        
        return $i18n;
    }
    
    function i18nReplace($str,$lang,$keys = false){
        $i18n = [];
        
        if( is_array($keys) ) $str = replaceKeys($str,$keys);
        
        return preg_replace_callback("/\\\$i18n{([:\\-\\w\\.]+)}/",function($coincidencias) use ($lang) {
            if( isset($coincidencias[1]) ) {
                $cmd = explode(":",$coincidencias[1]);
                
                if( $cmd[1] ) { $env = $cmd[0]; $key = $cmd[1]; }
                else { $env = "default"; $key = $cmd[0]; }
                
                if( !isset($i18n[$env]) ) $i18n[$env] = i18n($env,$lang) or [];
                
                //echo "env: $env, key: $key, i18n: ".$i18n[$env][$key]."<br/>";
                
                if( isset($i18n[$env][$key]) ) return $i18n[$env][$key];
                else return $coincidencias[0];
                
            } else return $coincidencias[0];
        },$str);
    }
    
    function i18nTemplate($name,$lang,$keys = []){
        if( $tmpl = template($name) ) {
            if( count($keys) ) return i18nReplace(replaceKeys($tmpl,$keys),$lang);
            return i18nReplace($tmpl,$lang);
        } else return false;
    }
    
    function json_stringify($data){
        return json_encode($data,JSON_UNESCAPED_UNICODE);
    }
    function json_b64($data){
        return "[\"".base64_encode(json_stringify($data))."\"]";
    }
    
    function echoJSON($data,$args){
        if( !is_array($args) ) $args = array();
        headersJSON();
        
        if( $args['allow-origin'] ) header( "Access-Control-Allow-Origin: ".$args['allow-origin'] );
        
        if( $args['b64'] ) echo json_b64($data);
        else echo json_stringify($data);
    }
    
    function json_parse($json){
        return json_decode($json,true);
    }
    
    function payloadJSON(){
        $body = file_get_contents("php://input");
        if( is_string($body) ) return json_parse($body);
        return new stdClass();
        /*if( isset($GLOBALS['HTTP_RAW_POST_DATA']) ) return json_parse($GLOBALS['HTTP_RAW_POST_DATA']);
        else return new stdClass();*/
    }
    
    function upassHash($str) { return hash('sha512',$str); }
    
    function alphaID($in, $to_num = false, $pad_up = false, $pass_key = null) {
      $out   =   '';
      $index = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $base  = strlen($index);
    
      if ($pass_key !== null) {
        // Although this function's purpose is to just make the
        // ID short - and not so much secure,
        // with this patch by Simon Franz (http://blog.snaky.org/)
        // you can optionally supply a password to make it harder
        // to calculate the corresponding numeric ID
    
        for ($n = 0; $n < strlen($index); $n++) {
          $i[] = substr($index, $n, 1);
        }
    
        $pass_hash = hash('sha256',$pass_key);
        $pass_hash = (strlen($pass_hash) < strlen($index) ? hash('sha512', $pass_key) : $pass_hash);
    
        for ($n = 0; $n < strlen($index); $n++) {
          $p[] =  substr($pass_hash, $n, 1);
        }
    
        array_multisort($p, SORT_DESC, $i);
        $index = implode($i);
      }
    
      if ($to_num) {
        // Digital number  <<--  alphabet letter code
        $len = strlen($in) - 1;
    
        for ($t = $len; $t >= 0; $t--) {
          $bcp = bcpow($base, $len - $t);
          $out = $out + strpos($index, substr($in, $t, 1)) * $bcp;
        }
    
        if (is_numeric($pad_up)) {
          $pad_up--;
    
          if ($pad_up > 0) {
            $out -= pow($base, $pad_up);
          }
        }
      } else {
        // Digital number  -->>  alphabet letter code
        if (is_numeric($pad_up)) {
          $pad_up--;
    
          if ($pad_up > 0) {
            $in += pow($base, $pad_up);
          }
        }
    
        for ($t = ($in != 0 ? floor(log($in, $base)) : 0); $t >= 0; $t--) {
          $bcp = bcpow($base, $t);
          $a   = floor($in / $bcp) % $base;
          $out = $out . substr($index, $a, 1);
          $in  = $in - ($a * $bcp);
        }
      }
    
      return $out;
    }
    
    function crockford_encode( $base10 ) {
        return strtr( base_convert( $base10, 10, 32 ),
                      "abcdefghijklmnopqrstuv",
                      "ABCDEFGHJKMNPQRSTVWZYZ" );
    }
    
    function crockford_decode( $base32 ) {
        $base32 = strtr( strtoupper( $base32 ), 
                         "ABCDEFGHJKMNPQRSTVWZYZILO",
                         "abcdefghijklmnopqrstuv110" );
        return base_convert( $base32, 32, 10 );
    }
    
    function slugify($string) {
        $a = array('À','Á','Â','Ã','Ä','Å','Æ','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ð','Ñ','Ò','Ó','Ô','Õ','Ö','Ø','Ù','Ú','Û','Ü','Ý','ß','à','á','â','ã','ä','å','æ','ç','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','õ','ö','ø','ù','ú','û','ü','ý','ÿ','A','a','A','a','A','a','C','c','C','c','C','c','C','c','D','d','Ð','d','E','e','E','e','E','e','E','e','E','e','G','g','G','g','G','g','G','g','H','h','H','h','I','i','I','i','I','i','I','i','I','i','?','?','J','j','K','k','L','l','L','l','L','l','?','?','L','l','N','n','N','n','N','n','?','O','o','O','o','O','o','Œ','œ','R','r','R','r','R','r','S','s','S','s','S','s','Š','š','T','t','T','t','T','t','U','u','U','u','U','u','U','u','U','u','U','u','W','w','Y','y','Ÿ','Z','z','Z','z','Ž','ž','?','ƒ','O','o','U','u','A','a','I','i','O','o','U','u','U','u','U','u','U','u','U','u','?','?','?','?','?','?');
        $b = array('A','A','A','A','A','A','AE','C','E','E','E','E','I','I','I','I','D','N','O','O','O','O','O','O','U','U','U','U','Y','s','a','a','a','a','a','a','ae','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','o','u','u','u','u','y','y','A','a','A','a','A','a','C','c','C','c','C','c','C','c','D','d','D','d','E','e','E','e','E','e','E','e','E','e','G','g','G','g','G','g','G','g','H','h','H','h','I','i','I','i','I','i','I','i','I','i','IJ','ij','J','j','K','k','L','l','L','l','L','l','L','l','l','l','N','n','N','n','N','n','n','O','o','O','o','O','o','OE','oe','R','r','R','r','R','r','S','s','S','s','S','s','S','s','T','t','T','t','T','t','U','u','U','u','U','u','U','u','U','u','U','u','W','w','Y','y','Y','Z','z','Z','z','Z','z','s','f','O','o','U','u','A','a','I','i','O','o','U','u','U','u','U','u','U','u','U','u','A','a','AE','ae','O','o');
        
        $url = str_replace($a,$b,$string);
        $url = preg_replace('~[^\\pL0-9_]+~u', '-', $url); // substitutes anything but letters, numbers and '_' with separator
        $url = trim($url, "-");
        $url = iconv("utf-8", "us-ascii//TRANSLIT", $url); // TRANSLIT does the whole job
        $url = strtolower($url);
        $url = preg_replace('~[^-a-z0-9_]+~', '', $url); // keep only letters, numbers, '_' and separator
        return $url;
    }
    
    
    class TableModel {
        private $sql = false;
        public $table = "";
        public $indexes = false;
        public $columns = false;
        public $have_uid = false;
        public $have_deleted = false;
        
        public function TableModel(&$model_sql,&$table,$args){
            if( !is_array($args) ) $args = array();
            //$this->uid = isset($args['uid']) ? $args['uid'] : false;
            $this->table = $table;
            $this->model_sql = $model_sql;
            $this->sql = $model_sql->sql;
            
            if( !SITE_DEBUG ) $this->columns = apc_fetch("sql-columns-$this->table");
            if( !$this->columns ) {
                $this->columns = new stdClass();
                $this->columns->index = array();
                $this->columns->list = array();
                $this->columns->length = 0;
                $query = "SHOW COLUMNS FROM `$this->table`;";
                if($result = $this->sql->query($query) ) {
                    $index = 0;
                    while($row = $result->fetch_array(MYSQLI_ASSOC)) {
                        $field = $row['Field'];
                        array_push($this->columns->list,array( "field" => $field, "type" => $row['Type'], "null" => ($row['Null'] == "YES"), "text" => eregi("(^varchar|text)",$row['Type']) ) );
                        $this->columns->index[$field] = $index++;
                    }
                    $this->columns->length = $index;
                    mysqli_free_result($result);
                    apc_store("sql-columns-$this->table",$this->columns);
                }
            }
            $this->have_uid = isset($this->columns->index['uid']);
            $this->have_deleted = isset($this->columns->index['deleted']);
            
            if( !SITE_DEBUG ) $this->indexes = apc_fetch("sql-indexes-$this->table");
            if( !$this->indexes ) {
                $this->indexes = array();
                $query = "SHOW INDEXES FROM `$this->table` WHERE `Key_name` != 'PRIMARY';";
                
                if($result = $this->sql->query($query) ) {
                    while($row = $result->fetch_array(MYSQLI_ASSOC)) {
                        $comumn_name = $row['Column_name'];
                        if( $comumn_name != "uid" && $this->columns->list[$this->columns->index[$comumn_name]]['type'] == "int(11)" ) {
                            $this->indexes[$comumn_name] = $row['Key_name'];
                        }
                    }
                    mysqli_free_result($result);
                    apc_store("sql-indexes-$this->table",$this->indexes);
                }
            }
        }
        
        public function get($args){
            if( !is_array($args) ) $args = array();
            $id_array = is_array($args['id']);
            
            if( is_array($args['columns']) ) {
                $index = 0;
                foreach( $args['columns'] as &$column ) $columns .= ($index++?",":"")."`$column`";
            } else $columns = "*";
            //$columns = is_array($args['columns']) ? implode(",",$args['columns']) : "*";
            
            if( isset($args['id']) ) {
                if( $id_array ) $query = "SELECT $columns FROM `$this->table` WHERE id IN (".implode(",",$args['id']).")";
                else $query = "SELECT $columns FROM `$this->table` WHERE id IN (".$args['id'].")";
            } else $query = "SELECT $columns FROM `$this->table` WHERE 1";
            
            //echo "have_uid: $this->have_uid, ".$this->model_sql->uid;
            if( $this->have_uid && !$args['all-uid'] ) {
                $query .= " AND uid = ".$this->model_sql->uid;
            }
            if( $this->have_deleted ) $query .= " AND deleted IS NULL";
            if( isset($args['where']) ) $query .= " AND ".$args['where'];
            
            $query .= " ORDER BY ".( isset($args['order']) ? $args['order'] : "id DESC" );
            
            if( isset($args['length']) ) {
                if( isset($args['from']) ) $query .= " LIMIT ".$args['from'].", ".$args['length'];
                else $query .= " LIMIT ".$args['length'];
            }
            
            $query .= ";";
            
            if( $args['debug'] ) die(json_encode([ "query" => $query ]));
            
            $exclude = false;
            if( is_string($args['exclude']) ) {
                $aux = explode(",",$args['exclude']);
                $exclude = array();
                foreach( $aux as &$field ) $exclude[$field] = true;
            }
            
            $data = new stdClass();
            $data->data = array();
            $data->index = array();
            $data->list = array();
            $data->indexes = array();
            $data->length = 0;
            
            if( $result = $this->sql->query($query) ) {
                if( (isset($args['id']) and !$id_array) or $args['1-item'] ) {
                    if( $result->num_rows == 1 ) {
                        return $result->fetch_array(MYSQLI_ASSOC);
                    } else return false;
                }
                
                while( $row = $result->fetch_row() ) {
                    $row_data = array();
                    $column_index = 0;
                    
                    if( is_array($args['columns']) ) {
                        $columns = $args['columns'];
                        foreach( $columns as $field ) {
                            if( !$exclude[$field] ) {
                                $value = $row[$column_index];
                                $row_data[$field] = is_numeric($value) ? ($value + 0) : $value;
                            }
                            $column_index++;
                        }
                    } else foreach( $this->columns->list as $column ) {
                        if( !$exclude[($field = $column['field'])] ) {
                            $value = $row[$column_index];
                            $row_data[$field] = is_numeric($value) ? ($value + 0) : $value;
                        }
                        $column_index++;
                    }
                    
                    $data->index[$row_data['id']] = $data->length++;
                    
                    array_push($data->list,$row_data['id']);
                    array_push($data->data,$row_data);
                }
                $data->indexes = $this->indexes;
                mysqli_free_result($result);
            }
            return $data;
        }
        
        public function getFull($args){
            $data = $this->get($args);
            foreach( $data->indexes as $index => $table ) {
                $list_id = array();
                foreach( $data->data as &$item ) {
                    if( $item[$index] ) array_push($list_id,$item[$index]);
                }
                $list_data = $this->model_sql->model($table)->getFull([ "id" => $list_id ]);
                foreach( $data->data as &$item ) {
                    if( isset($list_data->index[$item[$index]]) ) {
                        $item[$index] = $list_data->data[$list_data->index[$item[$index]]];
                    }
                }
            }
            return $data;
        }
        
        public function getWithDependencies($args){
            $data = [];
            if( !isset($args) ) $args = [];
            if( !isset($args['loaded']) ) $args['loaded'] = [];
            
            $table = $this->get($args);
            $data[$this->table] = $table;
            
            foreach( $table->indexes as $index => $fk_table ) {
                if( !isset($args['loaded'][$index]) ) {
                    $tables = $this->model_sql->model($fk_table)->getWithDependencies([ "loaded" => $data ]);
                    foreach( $tables as $table_name => $fk_table2 ) {
                        $data[$table_name] = $fk_table2;
                    }
                }
            }
            return $data;
        }
        
        public function getItem($args){
            $args['1-item'] = 1;
            return $this->get($args);
        }
        
        public function getMap($key,$value,$args){
            if( !is_array($args) ) $args = array();
            $args['columns'] = [$key,$value];
            $data = $this->get($args);
            $map = array();
            foreach( $data->data as $row ) $map[$row[$key]] = $row[$value];
            return $map;
        }
        
        public function data2SQL($data) {
            if( $this->have_uid && !isset($data['uid']) ) {
                $data['uid'] = $this->model_sql->uid;
            }
            $sql_data = new stdClass();
            $sql_data->fields = array();
            $sql_data->values = array();
            $sql_data->index = array();
            
            $index = 0;
            foreach( $data as $key => $value ) {
                if( is_numeric($value) ) {
                    $sql_data->values[$index] = $value;
                } else if( preg_match("^\\s*\\w+\\(\\)\\s*$",$value) ) {
                    $sql_data->values[$index] = $value;
                } else $sql_data->values[$index] = "'".$this->sql->real_escape_string("".$value)."'";
                
                $sql_data->fields[$index] = $key;
                $sql_data->index[$key] = $index;
                $index++;
                if( !isset($this->columns->index[$key]) ) $sql_data->columns_mismatch = true;
            }
            
            /*if( $this->have_uid && !isset($data['uid']) ) {
                $sql_data->values[$index] = $this->model_sql->uid;
                $sql_data->fields[$index] = "uid";
                $sql_data->index = $index;
            }*/
            
            return $sql_data;
        }
        
        public function addItem($data){
            $sql_data = $this->data2SQL($data);
            
            if( !$sql_data->columns_mismatch ) {
                $query = "INSERT INTO `$this->table` (`".implode("`,`",$sql_data->fields)."`) VALUES (".implode(",",$sql_data->values).");";
                
                if( $this->sql->query($query) ) {
                    return $this->sql->insert_id;
                } else die("addItem :: SQL ERROR [".$this->sql->errno."] ".$this->sql->error." | $query");
            }
            
            return [ "error" => "columns mismatch" ];
        }
        
        public function add($items){ foreach( $items as &$item ) $this->addItem($item); }
        
        public function setItem($data,$args){
            if( !is_array($args) ) $args = array();
            
            if( isset($data['id']) ) {
                if( $data['id'] == "" ) unset($data['id']);
            }
            
            if( isset($data['id']) ) {
                $sql_data = $this->data2SQL($data);
                
                $row_id = $sql_data->values[$sql_data->index['id']];
                
                $query = "UPDATE `$this->table` SET ";
                
                $index = 0;
                $first = true;
                foreach( $sql_data->values as &$value ) {
                    if( $sql_data->fields[$index] != "id" ) {
                        $query .= ($first?"`":", `").$sql_data->fields[$index]."` = ".$value;
                        $first = false;
                    }
                    $index++;
                }
                
                $query .= " WHERE id = $row_id;";
                
                if( $args['debug'] ) die(json_encode([ "query" => $query ]));
                
                if( $this->sql->query($query) ) {
                    return $this->sql->affected_rows;
                } else {
                    return [ "errno" => $this->sql->errno, "error" => $this->sql->error, "query" => $query ];
                    //die("setItem :: SQL ERROR [".$this->sql->errno."] ".$this->sql->error." | $query");
                }
                
            } else return $this->addItem($data);
        }
        public function set(&$items){ foreach( $items as &$item ) $this->setItem($item); }
        
        public function deleteItem($id,$args){
            if( !is_array($args) ) $args = array();
            
            if( is_numeric($id) ) {
                
                $query = "UPDATE `$this->table` SET `deleted` = ".($args['undo']?"NULL":"NOW()")." WHERE id = $id;";
                
                if( $args['debug'] ) die(json_encode([ "query" => $query ]));
                
                if( $this->sql->query($query) ) {
                    return [ "success" => true ]; //$this->sql->affected_rows;
                } else return [ "errno" => $this->sql->errno, "error" => $this->sql->error, "query" => $query ];
            }
            return [ "error" => "id-missing" ];
        }
        
        public function removeItems($args){
            if( !is_array($args) ) $args = array();
                
            $query = "DELETE FROM `$this->table`".($args['id']?(" WHERE id = ".$args['id'].";"):($args['where']?" WHERE ".$args['where']:"WHERE 0"));
            
            if( $args['debug'] ) die(json_encode([ "query" => $query ]));
            
            if( $this->sql->query($query) ) {
                return [ "success" => true ];
            } else return [ "errno" => $this->sql->errno, "error" => $this->sql->error, "query" => $query ];
        }
        
        public function delete($ids,$args){
            if( is_array($ids) ) { foreach( $ids as &$id ) $this->deleteItem($id,$args);  }
            else if( is_numeric($ids) ) return $this->deleteItem($ids,$args);
            return false;
        }
        
    }
    
    
    class ModelSQL {
        private $host = DB_HOST;
        private $user = DB_USER;
        private $pass = DB_PASS;
        private $db_name = DB_NAME;
        private $tables = array();
        public $uid = 0;
        
        public $sql;
        
        function ModelSQL($args) {
            if( !is_array($args) ) $args = array();
            
            $this->sql = new mysqli($this->host, $this->user, $this->pass, isset($args['db-name']) ? $args['db-name'] : $this->db_name );
            if( isset($args['uid']) ) $this->uid = $args['uid'];
            
            if( $this->sql->connect_errno ) { die('Could not connect: ' . $this->sql->connect_error );	}
    		else {
                @$this->sql->set_charset("utf8");
    			@$this->sql->query("SET NAMES 'utf8';");
    			if( isset($_COOKIE['client_tz_name']) ) {
    				@date_default_timezone_set($_COOKIE['client_tz_name']);
    				@$this->sql->query("set time_zone = '".$_COOKIE['client_tz_name']."'");
    			} else @$this->sql->query("set time_zone = 'Europe/Madrid'");
    		}
        }
        
        public function model($table) {
            if( !isset($this->tables[$table]) ) $this->tables[$table] = new TableModel($this,$table);
            return $this->tables[$table];
        }
        
        public function userId($uid) {
            if( isset($uid) ) $this->uid = $uid;
            return $this->uid;
        }
        
        public function close() {
            $this->sql->close();
        }
        
        public function debug() {
            return "hola caracola";
        }
    }
    
    class UserModel {
        private $msql;
        public $session = false;
        private $uname = "default";
        private $default = array( "id" => 0, "uname" => DEFAULT_UNAME, "alias" => DEFAULT_ALIAS, "email" => DEFAULT_EMAIL );
        private $table = false;
        private $logged = false;
        public $lang = "en";
        
        function UserModel($args){
            if( !is_array($args) ) $args = array();
            
            if( isset($args->msql) ) $this->msql = $args->msql;
            else $this->msql = new ModelSQL();
            
            if( isset($args['uname']) ) { $this->uname = $args['uname']; }
            else { if( isset($_COOKIE['uname']) ) $this->uname = $_COOKIE['uname']; }
            
            //http_response_code(404); var_dump($this->uname); die();
            
            session_name( md5("user/".$this->uname) );
            session_start();
            
            if( !isset($_SESSION['sql-session-id']) ) {
                session_regenerate_id(true);
                $user_url = $this->msql->model('_users')->getItem([ "where" => "uname = '$this->uname'" ]);
                $this->msql->userId($user_url['id']);
                
                require_once("Browser.php");
                $browser = new Browser();
                $session_data = array(
                    "hash" => session_id(),
                    "ip" => ip2long($_SERVER['REMOTE_ADDR']),
                    "browser" => $browser->getBrowser(),
                    "b_version" => $browser->getVersion(),
                    "platform" => $browser->getPlatform(),
                    "user_agent" => (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "")
                );
                
                //var_dump($session_data); die();
                
                //$this->msql->model('_sessions')->addItem($session_data);
                if( $session_row = $this->msql->model('_sessions')->addItem($session_data) ) $_SESSION['sql-session-id'] = $session_row;
                
                $this->generateQuestion(true);
            }
            
            $this->session = $_SESSION['sql-session-id'];
            
            if( !isset($_SESSION['lang']) ){
                $this->lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
                if( $this->lang != "en" && $this->lang != "es" ) $this->lang = "en";
                $_SESSION['lang'] = $this->lang;
            } else $this->lang = $_SESSION['lang'];
            
            $this->updateUID();
        }
        
        private function generateQuestion($force_refresh) {
            if( $force_refresh or !isset($_SESSION['question']) ) {
                $question = md5(uniqid(rand(), true));
            	$_SESSION['question'] = $question;
        		setcookie('question', $question,-1,"/");
            }
    	}
        
        private function updateUID($uid){
            $this->isLogged();
            if( isset($uid) ) $_SESSION['uid'] = $uid;
            else if( !isset($_SESSION['uid']) ) {
                if( $this->logged ) {
                    $_SESSION['uid'] = $this->logged;
                } else if( $this->uname == "default" ) {
                    $this->table = $this->default;
                    $_SESSION['uid'] = 0;
                } else {
                    $this->table = $this->msql->model('_users')->getItem([ "where" => "uname = '$this->uname'" ]);
                    $_SESSION['uid'] = intval($this->table['id']);
                }
            }
            
            $this->msql->userId($_SESSION['uid']);
            $this->generateQuestion();
        }
        
        public function data($array_mode){
            if( !$this->table ) {
                if( $this->logged ) {
                    $this->table = $this->msql->model('_users')->getItem([ "id" => $this->logged ]);
                    $this->msql->userId($this->logged);
                } else {
                    $this->table = $this->msql->model('_users')->getItem([ "where" => "uname = '$this->uname'" ]);
                    $this->msql->userId(intval($this->table['id']));
                }
            }
            
            if($array_mode) {
                $user = array();
                $user['id'] = $this->table['id'];
                $user['uname'] = $this->table['uname'];
                $user['email'] = $this->table['email'];
                $user['alias'] = $this->table['alias'];
                $user['role'] = $this->table['role'];
            } else {
                $user = new stdClass();
                $user->id = $this->table['id'];
                $user->uname = $this->table['uname'];
                $user->email = $this->table['email'];
                $user->alias = $this->table['alias'];
                $user->role = $this->table['role'];
            }
            return $user;
        }
        
        public function logIn(&$uname,&$answer){
            $user_data = $this->msql->model('_users')->getItem([ "where" => "uname = '$uname'" ]);
            $question = $_SESSION['question'];
            $this->generateQuestion(true);
            
            if( $user_data ) {
                $uid = $user_data['id'];
                $correct_answer = upassHash($question.$user_data['upass']);
                
                /*http_response_code(400);
                var_dump($user_data);
                echo "\nuname: $uname\n";
                echo "\nquestion: $question\n";
                echo "\nanswer: $answer\n";
                echo "\ncorrect_answer: $correct_answer\n";
                die();*/
                
                if( $answer == $correct_answer ) {
                    
                    $_SESSION['uid'] = $uid;
                    if( $this->msql->model('_logins')->addItem([ "uid" => $uid, "sid" => $_SESSION['sql-session-id'], "logged" => 1 ]) ) {
                        $this->table = $user_data;
                        $this->updateUID($uid);
                        return [ "uid" => $uid ];
                    } else return [ "error" => "sql", "log" => "inserting login status" ];
                    
                } else return [ "error" => "password", "log" => "password mismatch" ];
                
            } else return [ "error" => "username", "log" => "username mismatch" ];
            
            return [ "error" => "unknown", "log" => "unknown login error" ];
        }
        
        public function logOut(&$uname,&$answer){
            if( $uid = $this->isLogged() ) {
                if( isset($_SESSION['sql-session-id']) ) {
                    if( $this->msql->model('_logins')->addItem([ "uid" => $uid, "sid" => $_SESSION['sql-session-id'], "logged" => 0 ]) ) {
                        $this->logged = false;
                        $this->updateUID(0);
                        return [ "uid" => $uid ];
                        
                    } else return [ "error" => "sql", "log" => "inserting login status" ];
                    
                } else return [ "error" => "session", "log" => "session expired" ];
                
            } else return [ "warning" => "login", "log" => "already logged out" ];
            
            return [ "error" => "unknown", "log" => "unknown login error" ];
        }
        
        public function isLogged(){
            if( $_SESSION['uid'] ) $this->msql->userId($_SESSION['uid']);
            else $this->msql->userId(0);
            
            if( is_numeric($this->logged) ) return $this->logged;
            
            if( isset($_SESSION['sql-session-id']) ) {
                $login = $this->msql->model('_logins')->getItem([ "length" => 1, "where" => "sid = ".$_SESSION['sql-session-id'] ]);
                
                if( is_array($login) ) {
                    if( $login['logged'] ) {
                        $this->logged = $login['uid'];
                        return $this->logged;
                    }
                }                
            }
            return false;
        }
        
        // -------------------------------------------------------
        
        public function escapeSQL(&$str){ return $args->msql->sql->real_escape_string($str); }
        
        public function model($args){
            return $this->msql->model($args);
        }
        
        public function close(){
            $this->msql->close();
            session_write_close();
        }
        
        public function quit($text = ""){
            $this->close();
            die($text);
        }
    }
?>