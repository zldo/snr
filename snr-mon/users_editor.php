<?php
include_once 'snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;
$pagetitle = 'Управление пользователями системы';
//$slidebar = "./admin_slidebar.php";
$content = basename($_SERVER['PHP_SELF']);
include_once "./htmlstart.php";

if($snrapp->CheckRole('admin')) {

    require_once './lib/grid/EyeDataSource.php';
    require_once './lib/grid/class.eyedatagrid.inc.php';


    class UsersTable extends EyeDataSource {
        private $mysqlres;

        public function DoQuery($filter = "", $order = "", $limit = "") {
            if ($filter != "") $filter = " WHERE " . $filter;
            if ($order != "") $order = " ORDER BY " . $order;
            if ($limit != "") $limit = " LIMIT " . $limit;        
            $query = '
                SELECT * FROM 
                (SELECT * FROM users ' . $filter . $order . ') tbl0                
                LEFT JOIN 
                  (SELECT user_id, GROUP_CONCAT(roles.dname) AS roles FROM user_roles LEFT JOIN roles USING (role) GROUP BY  user_id) tbl2 
                USING (user_id) ' . $order;
            //echo $query;
            global $snrapp;
            $this->mysqlres = $snrapp->db->query($query);
            return $this->mysqlres;
        }

        public function GetRowCount($filter = "", $order = "") {
            if ($filter != "") $filter = " WHERE " . $filter;
            $query =  'SELECT count(user_id) FROM users ' . $filter;
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

    if(!function_exists('MakeMultiLine')){
        function MakeMultiLine($line){
            $lines = explode(',', $line);
            return implode('<br>', $lines);
        }
    }

    echo "<h2><a>Пользователи системы</a></h2>";
    echo "<div class='entry'>";
    echo '<a href="user_editor.php"><img src="images/user_add.png"> Добавить нового пользователя</a>';
    $grid = new EyeDataGrid(new UsersTable, 'lib/grid/images/');
    $grid->setResultsPerPage(15);
    $grid->showRowNumber();
    $grid->AddCol('tool', '<center><img src="images/database_edit.png"></center>', false, EyeDataGrid::TYPE_CUSTOM, 
                  '<center>'
                . '<a href="user_editor.php?user_id=%user_id%"><img src="images/pencil.png"></a>'
                . '</center>'
            , false);
    $grid->AddCol('dname', 'Имя', true, 0, '');
    $grid->AddCol('title', 'Должность', true, 0, '');
    $grid->AddCol('login', 'Логин', true, 0, '');
    $grid->AddCol('created', 'Создан', true, EyeDataGrid::TYPE_DATE, 'd.m.Y H:i', true);
    $grid->AddCol('changed', 'Изменен', true, EyeDataGrid::TYPE_DATE, 'd.m.Y H:i', true);
    $grid->AddCol('roles', 'Роли в системе', false, EyeDataGrid::TYPE_FUNCTION, 'MakeMultiLine', '%roles%');    
    $grid->printTable();
    echo "</div>";
} else {
    echo "<h2><a>Доступ к данному разделу запрещен</a></h2>";
    echo "<div class='entry'>";
    echo "</div>";
}


