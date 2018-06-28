<?php
include_once './snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;
$pagetitle = 'Устройства';
//$slidebar = "admin_slidebar.php";
$content = basename($_SERVER['PHP_SELF']);
include_once "htmlstart.php";
if($snrapp->CheckRole('admin')) {
    
require_once './lib/grid/EyeDataSource.php';
require_once './lib/grid/class.eyedatagrid.inc.php';


class DeviceTable extends EyeDataSource {
    private $mysqlres;
   
    public function DoQuery($filter = "", $order = "", $limit = "") {
        if ($filter != "") $filter = " WHERE " . $filter;
        if ($order != "") $order = " ORDER BY " . $order;
        else $order = " ORDER BY organization, location ";
        if ($limit != "") $limit = " LIMIT " . $limit;        
        $query = 'SELECT * FROM devices LEFT JOIN 
                    (SELECT device_id,
                            class_id,     
                            COUNT(*) AS full_cnt, 
                            SUM(IF(sensors.state = 0 OR sensors.state = 1, 1, 0)) AS online_cnt, 
                            SUM(IF(sensors.state = 1, 1, 0)) AS signaled_cnt, 
                            MAX(sensors.state_changed) AS state_changed, 
                            MIN(sensors.last_check) AS last_check, 
                            GROUP_CONCAT(if(ISNULL(sensors.display_name) OR TRIM(sensors.display_name) = "", sensors_classes.sensor_class, CONCAT(sensors.display_name, " (",sensors_classes.sensor_class,")")) ORDER BY class_id SEPARATOR "@#") AS sensor_names,
                            GROUP_CONCAT(sensors.sensor_id ORDER BY class_id) AS sensor_ids,
                            GROUP_CONCAT(sensors.state ORDER BY class_id) AS sensor_states,
                            GROUP_CONCAT(TRIM(CONCAT(sensors.value, " ", sensors_classes.value_pt)) ORDER BY class_id  SEPARATOR "@#") AS sensor_values
                      FROM sensors LEFT JOIN sensors_classes USING (class_id) GROUP BY sensors.device_id order by sensor_class) AS tbl 
                    USING (device_id)' . $filter . ' ' . $order . ' ' . $limit;
        //echo $query;
        global $snrapp;
        $this->mysqlres = $snrapp->db->query($query);
        return $this->mysqlres;
    }
    
    public function GetRowCount($filter = "", $order = "") {
        if ($filter != "") $filter = " WHERE " . $filter;
        $query =  'SELECT count(*) FROM devices ' . $filter;
        //echo $query;
        global $snrapp;
        return $snrapp->db->query($query)->fetchColumn();
    }
    
    public function error() {
        return mysql_error();
    }
    
    public function fetchAssoc($result) {
        global $snrapp;
        return $result->fetch(PDO::FETCH_ASSOC);
    }
    
}

class ProblemDeviceTable extends DeviceTable {
     public function ModifyFilter($filter = ''){
         if($filter !='') {
             return $filter .' AND (state < 0)';
         } else {
             return ' (state < 0)';
         }
     }       
     public function DoQuery($filter = "", $order = "", $limit = "") {
         return parent::DoQuery($this->ModifyFilter($filter), $order, $limit);
     }
     
      public function GetRowCount($filter = "", $order = "") {
          return parent::GetRowCount($this->ModifyFilter($filter), $order);
      }
}



?>

<h2><a>Устройства</a></h2>

<div class="entry">
    
<?php

if(!function_exists('AddDevicesCols')){
    
    function BuildSensorsCell($row){
        $titles = explode('@#', $row['sensor_names']);
        $ids = explode(',', $row['sensor_ids']);
        $states = explode(',', $row['sensor_states']);
        $values = explode('@#', $row['sensor_values']);
        $result = '<table class="blanktable" width=100% cellspacing=0>';
        for ($i = 0; $i < count($titles); $i++) {
            $result .= '<tr class="blanktable">';
            $result .= '<td class="blanktable" style="padding: 2px;">';
            $result .= '<a href="sensor_editor.php?sensor_id='.$ids[$i].'">'.$titles[$i].'</a>';
            $result .= '</td>';
            $result .= '<td class="blanktable" align="right" style="padding: 0px;">';
            if($states[$i] < 0) {
                $result .= '<font color="orange">'.SNRStateToStr($states[$i]).'</font>';
            } else {
                if($states[$i] == 0) {
                    $result .= $values[$i];
                } else {
                    $result .= '<font color="red">'.SNRStateToStr($states[$i]) . ' - ' . $values[$i] . '</font>';
                }
            }
            $result .= '</td>';
            $result .= '</tr>';
        }
        $result .= '</table>';
        return $result;
    }
    
    function AddDevicesCols($grid){
        $grid->showRowNumber();
        $grid->AddCol('tool', '<center><img src="images/database_edit.png"></center>', false, EyeDataGrid::TYPE_CUSTOM, '<center><a href="device_editor.php?device_id=%device_id%"><img src="images/pencil.png"></a></center>', true);
        $grid->AddCol('organization', 'Организация', true, EyeDataGrid::TYPE_CUSTOM, '%organization%', true);
        $grid->AddCol('location', 'Размещение', true, EyeDataGrid::TYPE_CUSTOM, '%location%', true);
        $grid->AddCol('display_name', 'Объект', true, EyeDataGrid::TYPE_CUSTOM, '%display_name%', true);
        $grid->AddCol('device_model', 'Модель устройства', true, EyeDataGrid::TYPE_CUSTOM, '%device_model%', true);
        $grid->AddCol('snmp_host', 'Хост', true, EyeDataGrid::TYPE_CUSTOM, '<a href="http://%snmp_host%" target="blank">%snmp_host%</a>', true);
        $grid->AddCol('full_cnt', 'Сенсоры', true, EyeDataGrid::TYPE_FUNCTION_ROW, 'BuildSensorsCell', true);
        //$grid->AddCol('online_cnt', 'В сети', true, EyeDataGrid::TYPE_CUSTOM, '%online_cnt%', true);
        //$grid->AddCol('signaled_cnt', 'Тревога', true, EyeDataGrid::TYPE_CUSTOM, '%signaled_cnt%', true);
        //$grid->AddCol('state', 'Состояние', true, EyeDataGrid::TYPE_FUNCTION, 'StateToStr', '%state%');
        //$grid->AddCol('last_error', 'Ошибка', true, EyeDataGrid::TYPE_CUSTOM, '%last_error%', true);
        //$grid->AddCol('snmp_host', 'Хост', true, EyeDataGrid::TYPE_CUSTOM, '%snmp_host%', true);
    }
    
}
    
$all = new EyeDataGrid(new DeviceTable(), 'lib/grid/images/');
$all->caption = 'Все устройства зарегистрированные в системе';
AddDevicesCols($all);
$all->setResultsPerPage(20);
//$all->useAjaxTable();
echo '<a href="device_editor.php"><img src="images/brick_add.png"> Добавить устройство</a>';
$all->printTable();

?>

</div>
<?php } else {
    echo "<h2><a>Доступ к данному разделу запрещен</a></h2>";
    echo "<div class='entry'>";
    echo "</div>";
}