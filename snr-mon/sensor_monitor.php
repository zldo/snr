<?php
include_once './snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;
global $sensor_filters; // Глобальные фильтры
global $sensor;
global $table_class; // Тип отображаемой в таблице информации
global $table_classes;
$table_classes = array(0 => 'События', 1 => 'История изменения значения', 2 => 'SNMP Traps', 3 => 'График');
$pagetitle = 'Информация о датчике';
$slidebar = "dev_filter_slidebar.php";
$content = basename($_SERVER['PHP_SELF']);
include_once "htmlstart.php";
if(true/*$snrapp->CheckRole('admin')*/) {
    
require_once './lib/grid/EyeDataSource.php';
require_once './lib/grid/class.eyedatagrid.inc.php';

if(isset($_GET['sensor_id'])){
    $st = $snrapp->db->prepare('SELECT * FROM sensors LEFT JOIN sensors_classes USING (class_id) WHERE sensor_id = :sensor_id');
    $st->bindValue('sensor_id', $_GET['sensor_id']);
    if($st->execute() and ($st->rowCount() > 0)) {
        $sensor = $st->fetch(PDO::FETCH_ASSOC);
    } else  {
        $sensor = null;
    }
} else {
    $sensor = null;
}

if(isset($_GET['table_class'])) {
    $table_class = $_GET['table_class']; 
} else {
    $table_class = 0; 
}

class AlertTable extends EyeDataSource {
    private $mysqlres;
    
    public function AplySensorFilter(&$params) {
        global $sensor_filters; // Глобальные фильтры
        global $filter_fields_order;
        $result = '';
        $r = array('true');                  
        if(isset($sensor_filters['SQLWhere'])) {
            $r[] = $sensor_filters['SQLWhere'];
        }
        
        if(isset($sensor_id)){
            $r[] = '(sensor_id = :sensor_id';
            $params['sensor_id'] = $sensor_id;
        }
        if(isset($sensor_filters['alert_type']) and ($sensor_filters['alert_type'] >= 0)) {
            $r[] = '(alert_type = '.$sensor_filters['alert_type'].')';
        }
            
        return implode(' AND ', $r);
    }
   
    public function DoQuery($filter = "", $order = "", $limit = "") {
        $params = array();
        $gfilter = $this->AplyGFilter($params);
        //print_r($gfilter);
        $filter = " WHERE " . $gfilter;
        if ($order != "") $order = " ORDER BY " . $order;
        else $order = " ORDER BY changed DESC";
        if ($limit != "") $limit = " LIMIT " . $limit;        
        global $snrapp;
        $st = $snrapp->db->prepare('SELECT * FROM (SELECT * FROM alerts_log ' . $filter . ') as t1 LEFT JOIN alerts USING(alert_id) ' . $order . ' ' . $limit);               
        //print_r($st);
        if($st->execute($params)){
            $this->mysqlres = $st;
        } else {
            $this->mysqlres = null;
        }
        return $this->mysqlres;
    }
    
    public function GetRowCount($filter = "", $order = "") {
        $params = array();
        $gfilter = $this->AplyGFilter($params);
        $filter = " WHERE " . $gfilter;
        global $snrapp;
        $st = $snrapp->db->prepare('SELECT count(*) FROM alerts_log ' . $filter);
        if($st->execute($params)){
            return $st->fetchColumn();
        } else {
            return 0;
        }
    }
    
    public function error() {
        return mysql_error();
    }
    
    public function fetchAssoc($result) {
        global $snrapp;
        return $result->fetch(PDO::FETCH_ASSOC);
    }
    
}




?>

<h2><a>Информация о датчике</a></h2>

<div class="entry">
    
<?php

if(!function_exists('AddDevicesCols')){
    
    function alert_type_echo($row){
        //print_r($row);
        switch ($row['alert_type']) {
            case 0:
                return '<img src="./images/info.png">&nbsp'. $row['display_name'];
                break;
            
            case 1:
                return '<img src="./images/warning.png">&nbsp'. $row['display_name'];
                break;
            
            case 2:
                return '<img src="./images/alert.png">&nbsp'. $row['display_name'];
                break;

            default:
                return '<img src="./images/info.png">&nbsp'. $row['display_name'];
                break;
        }        
    }
    
    function AddDevicesCols($grid){
        $grid->showRowNumber();
        $grid->AddCol('changed', 'Дата', true, EyeDataGrid::TYPE_DATE, 'd.m.Y H:i', true);
        $grid->AddCol('alert_type', 'Тип', false, EyeDataGrid::TYPE_FUNCTION_ROW, 'alert_type_echo');
        $grid->AddCol('title', 'Событие', true, EyeDataGrid::TYPE_CUSTOM, '%title%', true);
        $grid->AddCol('body', 'Описание', true, EyeDataGrid::TYPE_CUSTOM, '%body%', true);
    }
    
}
    
$all = new EyeDataGrid(new AlertTable(), 'lib/grid/images/');
$all->caption = 'События зарегистрированные в системе';
AddDevicesCols($all);
$all->setResultsPerPage(20);
$all->printTable();

?>

</div>
<?php } else {
    echo "<h2><a>Доступ к данному разделу запрещен</a></h2>";
    echo "<div class='entry'>";
    echo "</div>";
}