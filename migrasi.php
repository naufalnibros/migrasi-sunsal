<?php

class Migrasi extends LandaDb {
  private $db_source;
  private $db_target;
  private $old_table;
  private $new_table;
  private $columns;
  private $update_columns;
  private $add_columns;

  public function __construct($source, $target){
    parent::__construct();
    $this->db_target = $target;
    $this->db_source = $source;
  }

  public function all_table(){
    $source = LandaDb::findAll("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA='{$this->db_source}'");
    $target = LandaDb::findAll("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA='{$this->db_target}'");
    $model  = (object) [
      'source' => $source,
      'target' => $target
    ];
    return $model;
  }

  public function all_column(){
    $tables = $this->all_table();
    $source = array();
    foreach ($tables->source as $key => $value) {
      $source[$key]['TABLE_NAME'] = $value->TABLE_NAME;
      $source[$key]['COLUMNS']    = LandaDb::findAll("SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$this->db_source}' AND TABLE_NAME='{$value->TABLE_NAME}'");
    }
    $target = array();
    foreach ($tables->target as $key => $value) {
      $target[$key]['TABLE_NAME'] = $value->TABLE_NAME;
      $target[$key]['COLUMNS']    = LandaDb::findAll("SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$this->db_target}' AND TABLE_NAME='{$value->TABLE_NAME}'");
    }
    return (object)[
      'source' => $source,
      'target' => $target
    ];
  }

  public function get_column($TABLE_NAME = NULL, $DEST = NULL){
    if ($TABLE_NAME === NULL) {
      return (object)[
        'message' => "Parameter Table tidak boleh kosong",
        'error'   => TRUE
      ];
    }

    if ($DEST == "source") {
      return LandaDb::findAll("SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$this->db_source}' AND TABLE_NAME='{$TABLE_NAME}'");
    } elseif ($DEST == "target") {
      return LandaDb::findAll("SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$this->db_target}' AND TABLE_NAME='{$TABLE_NAME}'");
    } else {
      return (object)[
        'source' => LandaDb::findAll("SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$this->db_source}' AND TABLE_NAME='{$TABLE_NAME}'"),
        'target' => LandaDb::findAll("SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$this->db_target}' AND TABLE_NAME='{$TABLE_NAME}'")
      ];
    }
  }

  public function compare_tables(){
    $tables = $this->all_table();
    $old_table = array();
    foreach ($tables->source as $key => $value) {
      foreach ($tables->target as $key2 => $value2) {
        if ($value->TABLE_NAME == $value2->TABLE_NAME) {
          $old_table[$key]['TABLE_NAME'] = $value2->TABLE_NAME;
        }
      }
    }

    $new_table = array();
    foreach ($tables->source as $key => $value) {
      $new_table[$key]['TABLE_NAME'] = $value->TABLE_NAME;
      foreach ($old_table as $key2 => $value2) {
        if ($new_table[$key]['TABLE_NAME'] == $value2['TABLE_NAME']) {
          unset($new_table[$key]);
          goto next;
        }
      }
      next:
    }

    /** init **/
    $this->old_table = $old_table;
    $this->new_table = $new_table;
    return $this;
  }

  private function old_table(){
    $tables = $this->all_table();
    $old_table = array();
    foreach ($tables->source as $key => $value) {
      foreach ($tables->target as $key2 => $value2) {
        if ($value->TABLE_NAME == $value2->TABLE_NAME) {
          $old_table[$key]['TABLE_NAME'] = $value2->TABLE_NAME;
        }
      }
    }
    /** init **/
    return $old_table;
  }

  public function compare_columns(){
    $tables = ($this->old_table == NULL) ? $this->old_table() : $this->old_table;

    $columns = array();
    foreach ($tables as $key => $value) {
      $source_columns = LandaDb::select("COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE, COLUMN_COMMENT")
      ->from("INFORMATION_SCHEMA.COLUMNS")
      ->where("=", "TABLE_SCHEMA", $this->db_source)
      ->andWhere("=", "TABLE_NAME", $value['TABLE_NAME'])
      ->findAll();

      $target_columns = LandaDb::select("COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE, COLUMN_COMMENT")
      ->from("INFORMATION_SCHEMA.COLUMNS")
      ->where("=", "TABLE_SCHEMA", $this->db_target)
      ->andWhere("=", "TABLE_NAME", $value['TABLE_NAME'])
      ->findAll();

      $old_columns = array();
      foreach ($source_columns as $key_source => $val_source) {
        foreach ($target_columns as $key_target => $val_target) {
          if ($val_source->COLUMN_NAME == $val_target->COLUMN_NAME) {
            $old_columns[$key_source] = (array) $val_target;
          }
        }
      }

      $add_columns = array();
      foreach ($source_columns as $key_source => $val_source) {
        $add_columns[$key_source] = (array) $val_source;
        foreach ($old_columns as $key_old => $val_old) {
          if ($add_columns[$key_source]['COLUMN_NAME'] == $val_old['COLUMN_NAME']) {
            unset($add_columns[$key_source]);
            goto next;
          }
        }
        next:
      }

      $data_update_column = array();
      foreach ($old_columns as $key_old => $val_old) {
        $data_update_column[$key_old] = (array) $val_old;
        foreach ($add_columns as $key_add => $val_add) {
          if ($data_update_column[$key_old]['COLUMN_NAME'] == $val_add['COLUMN_NAME']) {
            unset($data_update_column[$key_old]);
            goto jump;
          }
        }
        jump:
      }

      $update_columns = array();
      foreach ($source_columns as $key_source => $val_source) {
        foreach ($data_update_column as $key_columns => $val_columns) {
          if ($val_source->COLUMN_NAME !== $val_columns['COLUMN_NAME']) {
            goto loop;
          }
          $DATA_COLUMN['COLUMN_NAME']    = ($val_source->COLUMN_NAME === $val_columns['COLUMN_NAME']) ? NULL : $val_source->COLUMN_NAME;
          $DATA_COLUMN['COLUMN_DEFAULT'] = ($val_source->COLUMN_DEFAULT === $val_columns['COLUMN_DEFAULT']) ? NULL : $val_source->COLUMN_DEFAULT;
          $DATA_COLUMN['IS_NULLABLE']    = ($val_source->IS_NULLABLE === $val_columns['IS_NULLABLE']) ? NULL : $val_source->IS_NULLABLE;
          $DATA_COLUMN['COLUMN_TYPE']    = ($val_source->COLUMN_TYPE === $val_columns['COLUMN_TYPE']) ? NULL : $val_source->COLUMN_TYPE;
          $DATA_COLUMN['COLUMN_COMMENT'] = ($val_source->COLUMN_COMMENT === $val_columns['COLUMN_COMMENT']) ? NULL : $val_source->COLUMN_COMMENT;

          $COLUMN = array();
          foreach ($DATA_COLUMN as $key_column => $val_column) {
            if(isset($DATA_COLUMN[$key_column])){
              $COLUMN['COLUMN_NAME']      = $val_source->COLUMN_NAME;
              $COLUMN['COLUMN_DEFAULT']   = $val_source->COLUMN_DEFAULT;
              $COLUMN['IS_NULLABLE']      = $val_source->IS_NULLABLE;
              $COLUMN['COLUMN_TYPE']      = $val_source->COLUMN_TYPE;
              $COLUMN['COLUMN_COMMENT']   = $val_source->COLUMN_COMMENT;
              // $COLUMN[$key_column]   = $DATA_COLUMN[$key_column];
            }
          }

          if (!empty($COLUMN)) {
            $update_columns[$key_source] = $COLUMN;
          }
          loop:
        }
      }

      $columns[$key] = (array) $value;
      $columns[$key]['update_columns'] = empty($update_columns) ? [] : $update_columns;
      $columns[$key]['add_columns']    = empty($add_columns) ? [] : $add_columns;
    }

    $update_columns = array();
    foreach ($columns as $key => $value) {
      foreach ($value['update_columns'] as $key2 => $value2) {
        $update_columns[] = array('TABLE_NAME' => $value['TABLE_NAME'], 'COLUMNS' => $value2);
      }
    }

    $add_columns = array();
    foreach ($columns as $key => $value) {
      foreach ($value['add_columns'] as $key2 => $value2) {
        $add_columns[] = array('TABLE_NAME' => $value['TABLE_NAME'], 'COLUMNS' => $value2);
      }
    }

    $this->columns        = (object)['add_columns' => $add_columns, 'update_columns' => $update_columns];
    $this->add_columns    = $add_columns;
    $this->update_columns = $update_columns;
    return $this;
  }

  public function create_table($params){
    $data = array();
    foreach ($params as $key => $value) {
      $source_columns = $this->get_column($value['TABLE_NAME'], "source");
      $data[$key]['TABLE_NAME'] = $value['TABLE_NAME'];
      $data[$key]['COLUMNS']    = (array) $source_columns;
    }

    $scripts = array();
    foreach ($data as $key => $value) {
      $script = "CREATE TABLE IF NOT EXISTS {$value['TABLE_NAME']} ";
      $script .= "(";
      foreach ($value['COLUMNS'] as $key2 => $value2) {
        $DEFAULT_NULL     = ($value2->IS_NULLABLE == "YES") ? "DEFAULT NULL" : "NOT NULL";
        $AUTO_INCREMENTS  = ($value2->COLUMN_NAME === "id") ? "AUTO_INCREMENT" : "";
        $COLUMN_COMMENT   = (!empty($value2->COLUMN_COMMENT)) ? "COMMENT '$value2->COLUMN_COMMENT'" : "";
        $COLUMN_DEFAULT   = ($value2->COLUMN_DEFAULT !== NULL) ? "DEFAULT '{$value2->COLUMN_DEFAULT}'" : $DEFAULT_NULL;
        $script .= " {$value2->COLUMN_NAME} {$value2->COLUMN_TYPE} {$COLUMN_DEFAULT} {$COLUMN_COMMENT} {$AUTO_INCREMENTS}, ";
      }
      $script .= "PRIMARY KEY (id)";
      $script .= ")";
      $scripts[] = $script;
    }

    $scripts = implode(";<br>", $scripts);

    return (object)['scripts' => $scripts, 'data' => $data];
  }

  public function add_column($params){
    $scripts = array();
    foreach ($params as $key => $value) {
      $script = "ALTER TABLE ";
      $script .= "'{$value['TABLE_NAME']}'";
      $script .= " ADD ";
      $DEFAULT_NULL     = ($value['COLUMNS']['IS_NULLABLE'] == "YES") ? "DEFAULT NULL" : "NOT NULL";
      $AUTO_INCREMENTS  = ($value['COLUMNS']['COLUMN_NAME'] === "id") ? "AUTO_INCREMENT" : "";
      $COLUMN_COMMENT   = (!empty($value['COLUMNS']['COLUMN_COMMENT'])) ? "COMMENT '{$value['COLUMNS']['COLUMN_COMMENT']}'" : "";
      $COLUMN_DEFAULT   = ($value['COLUMNS']['COLUMN_DEFAULT'] !== NULL) ? "DEFAULT '{$value['COLUMNS']['COLUMN_DEFAULT']}'" : $DEFAULT_NULL;
      $script           .= " '{$value['COLUMNS']['COLUMN_NAME']}' {$value['COLUMNS']['COLUMN_TYPE']} {$COLUMN_DEFAULT} {$COLUMN_COMMENT} {$AUTO_INCREMENTS}";
      $scripts[] = $script;
    }

    $scripts = implode(";<br>", $scripts);
    return (object)['scripts' => $scripts, 'data' => $params];
  }

  public function update_column($params){
    $scripts = array();
    foreach ($params as $key => $value) {
      $script = "ALTER TABLE ";
      $script .= "{$value['TABLE_NAME']}";
      $script .= " MODIFY ";
      $DEFAULT_NULL     = ($value['COLUMNS']['IS_NULLABLE'] == "YES") ? "DEFAULT NULL" : "NOT NULL";
      $AUTO_INCREMENTS  = ($value['COLUMNS']['COLUMN_NAME'] === "id") ? "AUTO_INCREMENT" : "";
      $COLUMN_COMMENT   = (!empty($value['COLUMNS']['COLUMN_COMMENT'])) ? "COMMENT '{$value['COLUMNS']['COLUMN_COMMENT']}'" : "";
      $COLUMN_DEFAULT   = ($value['COLUMNS']['COLUMN_DEFAULT'] !== NULL) ? "DEFAULT '{$value['COLUMNS']['COLUMN_DEFAULT']}'" : $DEFAULT_NULL;
      $script           .= " {$value['COLUMNS']['COLUMN_NAME']} {$value['COLUMNS']['COLUMN_TYPE']} {$COLUMN_DEFAULT} {$COLUMN_COMMENT} {$AUTO_INCREMENTS}";
      $scripts[] = $script;
    }

    $scripts = implode(";<br>", $scripts);
    return (object)['scripts' => $scripts, 'data' => $params];
  }

  public function get_query(){
    $model['columns']   = $this->columns;
    $model['new_table'] = $this->new_table;

    if ($this->old_table == NULL) {
      $add_columns      = $this->add_column($this->add_columns)->scripts;
      $update_columns   = $this->update_column($this->update_columns)->scripts;
      $return = (object) [
        'all'            => "{$update_columns}; <br><br><br> {$add_columns};",
        'update' => "{$update_columns};",
        'add'    => "{$add_columns};",
      ];
      return $return;
    }

    $create_table = $this->create_table($model['new_table'])->scripts;
    if (!empty($model['columns'])) {
      $add_columns      = $this->add_column($this->add_columns)->scripts;
      $update_columns   = $this->update_column($this->update_columns)->scripts;
      $return = (object) [
        'all'            => "{$create_table}; <br><br><br> {$update_columns}; <br><br><br> {$add_columns};",
        'update_columns' => "{$update_columns};",
        'add_columns'    => "{$add_columns};",
        'create_table'   => "{$create_table};;"

      ];
      return $return;
    } else {
      return "{$create_table};";
    }
  }

}
