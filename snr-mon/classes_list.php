<?php
include_once './snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;
$pagetitle = 'Клссы сенсоров';
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
        if ($order != "") $order = " ORDER BY " . $order . ", device_model ";
        else $order = " ORDER BY device_model, sensor_class";
        if ($limit != "") $limit = " LIMIT " . $limit;        
        $query = 'SELECT * FROM sensors_classes ' . $filter . ' ' . $order . ' ' . $limit;
        //echo $query;
        global $snrapp;
        $this->mysqlres = $snrapp->db->query($query);
        return $this->mysqlres;
    }
    
    public function GetRowCount($filter = "", $order = "") {
        if ($filter != "") $filter = " WHERE " . $filter;
        $query =  'SELECT count(*) FROM sensors_classes ' . $filter;
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



?>

<h2><a>Классы сенсоров</a></h2>

<div class="entry">
    
<?php

    
$grid = new EyeDataGrid(new DeviceTable(), 'lib/grid/images/');
$grid->showRowNumber();
$grid->caption = 'Классы сенсоров и модели устройств';
$grid->AddCol('tool', '<center><img src="images/database_edit.png"></center>', false, EyeDataGrid::TYPE_CUSTOM, '<center><a href="class_editor.php?class_id=%class_id%"><img src="images/pencil.png"></a></center>', true);
$grid->AddCol('device_model', 'Модель устройства', true, EyeDataGrid::TYPE_CUSTOM, '%device_model%', true);
$grid->AddCol('sensor_class', 'Класс сенсора', true, EyeDataGrid::TYPE_CUSTOM, '%sensor_class%', true);
$grid->AddCol('description', 'Описание', true, EyeDataGrid::TYPE_CUSTOM, '%description%', true);
$grid->setResultsPerPage(20);
//$all->useAjaxTable();
echo '<a href="class_editor.php"><img src="images/brick_add.png"> Добавить класс сенсоров</a>';
$grid->printTable();

?>

</div>
<?php } else {
    echo "<h2><a>Доступ к данному разделу запрещен</a></h2>";
    echo "<div class='entry'>";
    echo "</div>";
}