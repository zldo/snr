<?php
include_once './snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;
$pagetitle = 'События';
//$slidebar = "admin_slidebar.php";
$content = basename($_SERVER['PHP_SELF']);

include_once "htmlstart.php";

if($snrapp->CheckRole('admin')) {

    require_once './lib/grid/EyeDataSource.php';
    require_once './lib/grid/class.eyedatagrid.inc.php';


    class AlertsTable extends EyeDataSource {
        private $mysqlres;

        public function DoQuery($filter = "", $order = "", $limit = "") {
            if ($filter != "") $filter = " WHERE " . $filter;
            if ($order != "") $order = " ORDER BY " . $order . ", display_name ";
            else $order = " ORDER BY display_name ";
            if ($limit != "") $limit = " LIMIT " . $limit;        
            $query = 'SELECT * FROM alerts ' . $filter . ' ' . $order . ' ' . $limit;
            //echo $query;
            global $snrapp;
            $this->mysqlres = $snrapp->db->query($query);
            return $this->mysqlres;
        }

        public function GetRowCount($filter = "", $order = "") {
            if ($filter != "") $filter = " WHERE " . $filter;
            $query =  'SELECT count(*) FROM alerts ' . $filter;
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

    if(!function_exists('BuildWandedState')){
        function BuildWandedState($row){
            switch ($row['wanted_state']) {
                case -6:
                    $result = 'Нестабильное подключение';
                    break;
                case -5:
                    $result = 'Показание нестабильно';
                    break;
                case -4:
                    $result = 'Ошибка';
                    break;
                case -2:
                    $result = 'Не в сети';
                    break;
                case -1:
                    $result = 'Неизвестно';
                    break;
                case -0:
                    $result = 'В сети';
                    break;
                case -1:
                    $result = 'Тревога';
                    break;
                default:
                    $result = 'n/a';
                    break;
            }
            return $result;
        }

        function BuildAction($row){
            switch ($row['action']) {
                case 'MAIL':
                    $result = 'Отправить электронную почту';
                    break;
                case 'EXEC':
                    $result = 'Выполнить команду на сервере';
                    break;
                default:
                    $result = 'Только добавить в журнал';
                    break;
            }
            return $result;
        }
    }


    ?>

    <h2><a>События</a></h2>

    <div class="entry">

    <?php


    $grid = new EyeDataGrid(new AlertsTable(), 'lib/grid/images/');
    $grid->showRowNumber();
    $grid->caption = 'Типы событий';
    $grid->AddCol('tool', '<center><img src="images/database_edit.png"></center>', false, EyeDataGrid::TYPE_CUSTOM, '<center><a href="alert_editor.php?alert_id=%alert_id%"><img src="images/pencil.png"></a></center>', true);
    $grid->AddCol('tool2', '<center><img src="images/folder_link.png"></center>', false, EyeDataGrid::TYPE_CUSTOM, '<center><a href="alert_sensors.php?alert_id=%alert_id%"><img src="images/link_edit.png"></a></center>', true);
    $grid->AddCol('device_model', 'Имя', true, EyeDataGrid::TYPE_CUSTOM, '%display_name%', true);
    $grid->AddCol('description', 'Описание', true, EyeDataGrid::TYPE_CUSTOM, '%description%', true);
    $grid->AddCol('wanted_state', 'Ожидаемое состояние', true, EyeDataGrid::TYPE_FUNCTION_ROW, 'BuildWandedState', true);
    $grid->AddCol('action', 'Действие', true, EyeDataGrid::TYPE_FUNCTION_ROW, 'BuildAction', true);
    $grid->setResultsPerPage(20);
    //$all->useAjaxTable();
    echo '<a href="alert_editor.php"><img src="images/brick_add.png"> Добавить новое событие</a>';
    $grid->printTable();

    ?>

    </div>
<?php } else {
    echo "<h2><a>Доступ к данному разделу запрещен</a></h2>";
    echo "<div class='entry'>";
    echo "</div>";
}