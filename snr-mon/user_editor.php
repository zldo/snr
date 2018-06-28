<?php
include_once 'snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;
global $user_id;
if(isset($_GET['user_id'])){
    if($_GET['user_id'] <> '') {
        $user_id = (int) $_GET['user_id'];
    } else {
        $user_id = null;
    }    
} else {
    $user_id = isset($_POST['user_id'])?(int)$_POST['user_id']:'';
    if($user_id <> '') {
        $user_id = (int) $user_id;
    } else {
        $user_id = null;
    }
}
$pagetitle = isset($user_id)?'Редактирование свойств пользователя':'Добавление пользователя в систему';
//$slidebar = "./admin_slidebar.php";
$content = basename($_SERVER['PHP_SELF']);
include_once "./htmlstart.php";

if($snrapp->CheckRole('admin')) {
    
    if($rout) exit; 
    
    if(isset($_GET['deleted_id'])){
        $user_id = (int) $_GET['deleted_id'];
        if($user_id >= 0){
            $snrapp->db->query('DELETE FROM users WHERE user_id = ' . $user_id);            
            $snrapp->db->query('DELETE FROM user_roles WHERE user_id = ' . $user_id);
            header('Location: users_editor.php');
        }
    }

    $errors = [];
    
    if(isset($_POST['dname'])){ 
        if(isset($_POST['user_id'])){ // Обновление существующего пользователя
            $user_id = (int) $_POST['user_id'];
            $st = $snrapp->db->query('SELECT * FROM users WHERE user_id = ' . $user_id);
            if($st->execute() and ($st->rowCount() > 0)) {
                $user = $st->fetchObject();
                $pass = $_POST['pass'] == '********'?$user->pass:$_POST['pass'];
                if($user->login != $_POST['login']) { // проверка на совпадение логинов
                    if($st = $snrapp->db->query('SELECT * FROM users WHERE login = ' . $snrapp->db->quote($_POST['login']))) {
                        $exist_id = $st->rowCount() > 0 ? $st->fetchColumn(): null;
                    } else {
                        $exist_id = null;
                    }
                    if(isset($exist_id) and ($user->user_id != (int) $exist_id)) {
                        $errors['login'] = 'Логин <u>'.$_POST['login'].'</u> уже используется';
                    }
                }
                if($_POST['login'] == '') {
                        $errors['login'] = 'Логин не может быть пустым';
                }
            } else {
                $errors['db'] = 'Пользователь не найден в базе данных.';
            }
            if(count($errors) == 0) {
                $st = $snrapp->db->prepare('UPDATE users SET '
                        . 'changed = CURRENT_TIMESTAMP, '
                        . 'login = :login, '
                        . 'dname = :dname, '
                        . 'title = :title, '
                        . 'pass = :pass, '
                        . 'blocked = :blocked WHERE user_id = ' . $user_id);
                $st->bindValue('login', $_POST['login']);
                $st->bindValue('title', $_POST['title']);
                $st->bindValue('blocked', isset($_POST['blocked']));
                $st->bindValue('dname', $_POST['dname']);
                $st->bindValue('pass', $pass);
                if(!$st->execute()) { 
                    $errors['db'] = 'Невозможно обновить информацию в базе данных';
                    $errors['db_info'] = print_r($st->errorInfo(), true);
                }
            }
            if(count($errors) == 0) {
                                
                $snrapp->db->query('DELETE FROM user_roles WHERE user_id = ' . $user_id);
                $st = $snrapp->db->prepare('INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)');
                foreach ($_POST['role'] as $key => $value) {
                    if(isset($_POST['role_'.$value])) {
                        $st->bindValue('user_id', $user_id);
                        $st->bindValue('role', $value);
                        if(!$st->execute()){
                            $errors['db'] = 'Невозможно обновить информацию в базе данных';
                            $errors['db_info'] = print_r($st->errorInfo(), true);
                        }
                    }
                }
                
            }
             if(count($errors) == 0) {
                 header('Location: user_editor.php?saved=1&user_id='.$user_id);
             }
        } else { // Добавление нового пользователя
            $pass = $_POST['pass'] == '********'?null:$_POST['pass'];
            if(!isset($pass)){
                $errors['pass'] = 'Пароль не может быть пустым';
            }
            if($snrapp->db->query('SELECT * FROM users WHERE login = ' . $snrapp->db->quote($_POST['login']))->rowCount() > 0) {
                $errors['login'] = 'Такой логин уже используется';
            }
            if($_POST['login'] == '') {
                $errors['login'] = 'Логин не может быть пустым';
            }
            if(count($errors) == 0) {
                $st = $snrapp->db->prepare('INSERT INTO users (login, dname, title, pass, blocked, created, changed) '
                                         . 'VALUES (:login, :dname, :title, :pass, :blocked, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
                $st->bindValue('login', $_POST['login']);
                $st->bindValue('title', $_POST['title']);
                $st->bindValue('blocked', isset($_POST['blocked']));
                $st->bindValue('dname', $_POST['dname']);
                $st->bindValue('pass', $pass);
                if(!$st->execute()) { 
                    $errors['db'] = 'Невозможно добавить пользователя в базу данных';
                    $errors['db_info'] = print_r($st->errorInfo(), true);
                } else {
                    $user_id = $snrapp->db->lastInsertId();
                }
            }
            if(count($errors) == 0) {
                                
                $st = $snrapp->db->prepare('INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)');
                foreach ($_POST['role'] as $key => $value) {
                    if(isset($_POST['role_'.$value])) {
                        $st->bindValue('user_id', $user_id);
                        $st->bindValue('role', $value);
                        if(!$st->execute()){
                            $errors['db'] = 'Невозможно обновить информацию в базе данных';
                            $errors['db_info'] = print_r($st->errorInfo(), true);
                        }
                    }
                }
                
            }
             if(count($errors) == 0) {
                 header('Location: users_editor.php');
             }
        }
    }

    require_once './lib/grid/EyeDataSource.php';
    require_once './lib/grid/class.eyedatagrid.inc.php';
    
    class RolesTable extends EyeDataSource {
        private $mysqlres;

        public function DoQuery($filter = "", $order = "", $limit = "") {
            global $user_id;
            if ($filter != "") $filter = " WHERE " . $filter;
            if ($order != "") $order = " ORDER BY " . $order;
            if ($limit != "") $limit = " LIMIT " . $limit;        
            if(isset($user_id)) {
                $query = 'SELECT DISTINCT roles.*, NOT ISNULL(tbl0.user_id) AS checked  
                          FROM roles LEFT JOIN 
                            (SELECT * FROM user_roles WHERE user_id='.$user_id.') tbl0  
                          USING (role) ' . $filter . $order . $limit;
            } else {
                $query = 'SELECT *, 0 AS checked FROM roles ' . $filter . $order . $limit;
            }
            //echo $query;
            global $snrapp;
            $this->mysqlres = $snrapp->db->query($query);
            return $this->mysqlres;
        }

        public function GetRowCount($filter = "", $order = "") {
            if ($filter != "") $filter = " WHERE " . $filter;
            $query =  'SELECT count(role) FROM roles ' . $filter;
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

    if(!function_exists('MakeRoleCheckbox')){
        function MakeRoleCheckbox($row){
            $s = $row['checked']?'checked':'';
            return
                '<input type="hidden" name="role[]" value="'.$row['role'].'">'
              . '<input type="checkbox" name="role_'.$row['role'].'" ' . $s . '>'
              . $row['dname'];      
        }
        
    }
    
    if(isset($user_id)){
        $st = $snrapp->db->query('SELECT * FROM users WHERE user_id = ' . (int) $user_id);
        if(!$st->execute()){
            if(isset($_POST['dname'])) {
                $user['login'] = $_POST['login'];
                $user['pass'] = $_POST['pass'];
                $user['dname'] = $_POST['dname'];
                $user['title'] = $_POST['title'];
                $user['blocked'] = isset($_POST['blocked']);
            } else {
                $errors['main'] = 'Пользователь не найден';
                $user['login'] = '';
                $user['pass'] = '';
                $user['dname'] = '';
                $user['title'] = '';
                $user['blocked'] = false;
            }
        } else {
            $user = $st->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        if(isset($_POST['dname'])) {
            $user['login'] = $_POST['login'];
            $user['pass'] = $_POST['pass'];
            $user['dname'] = $_POST['dname'];
            $user['title'] = $_POST['title'];
            $user['blocked'] = isset($_POST['blocked']);
        } else {
            //$errors['main'] = 'Пользователь не найден';
            $user['login'] = '';
            $user['pass'] = '';
            $user['dname'] = '';
            $user['title'] = '';
            $user['blocked'] = false;
        }
    }
    if(isset($_GET['saved'])) {
        $errors[] = 'Данные сохранены.';
    }
    echo "<h2><a>".$pagetitle."</a></h2>";
    echo '<a href="users_editor.php"><img src="images/group.png"> Вернуться к списку пользователей</a>';
    echo "<div class='entry'>";
    if(count($errors) > 0) {
        echo '';
        foreach ($errors as $value) {
            echo '<h3>' . $value . '</h3>';
        }
    }
    echo '<table class="blanktable"><tr class="blanktable" width="80%">';
    echo '<td class="blanktable" valign="top">';
    ?>
<form action="<?php echo $_SERVER['PHP_SELF'] . '?user_id='.$user_id; ?>" method="POST">
        <?php 
          if(isset($user_id)){
              echo '<input type="hidden" name="user_id" value="'.$user_id.'">';
          }
        ?>
        <table class="blanktable">
        <caption>Основные параметры:</captio
        <tr><td class="blanktable">Логин</td><td class="blanktable"><input name="login" value="<?php echo $user['login'];?>"></td></tr>
        <tr><td class="blanktable">Пароль</td><td class="blanktable"><input type='password' name="pass" value="********"></td></tr>
        <tr><td class="blanktable">Отображаемое имя</td><td class="blanktable"><input name="dname" value="<?php echo $user['dname'];?>"></td></tr>
        <tr><td class="blanktable">Должность</td><td class="blanktable"><input name="title" value="<?php echo $user['title'];?>"></td></tr>
        <tr><td class="blanktable" colspan="2"><input type="checkbox" name="blocked" <?php echo $user['blocked']?'checked':'';?>> Заблокирован</td></tr>
        <?php if(isset($user_id)){ ?>
        <tr>
            <td class="blanktable" colspan="2">
                <a href="user_editor.php?deleted_id=<?php echo $user_id; ?>" onclick="return confirm('Вы действительно хотите удалить этого пользователя?') ? true : false;">
                    <img src="images/delete.png"> Удалить пользователя <b> <?php echo $user['dname']; ?> </b>
                </a>
            </td>
        </tr>
        <?php } ?>
        </td></tr></table>
    <?php
    
    // Роли пользователя в системе
    $grid = new EyeDataGrid(new RolesTable, 'lib/grid/images/');
    $grid->setResultsPerPage(150);
    $grid->width = "100%";
    $grid->hideFooter();
    $grid->hideHeader();
    $grid->caption = 'Роли пользователя в системе:';
    $grid->UseFormTag = false;
    $grid->tbl_class = 'blanktable';
    //$grid->tbl_row_class = 'blanktable';
    $grid->tbl_cell_class = 'blanktable';
    $grid->AddCol('dname', 'Роль', false, EyeDataGrid::TYPE_FUNCTION_ROW, 'MakeRoleCheckbox');
    $grid->AddCol('description', 'Описание', true, 0, '');
    $grid->printTable();
    echo '</td>';
    echo '</tr>';
    echo '<tr class="blanktable">';
    echo '<td class="blanktable" align="right" colspan="2">';
    echo '<br><input type="submit" value="Сохранить"></form><br>';
    echo '</td>';
    echo '</tr></table>';
    echo "</div>";
} else {
    echo "<h2><a>Доступ к данному разделу запрещен</a></h2>";
    echo "<div class='entry'>";
    echo "</div>";
}




