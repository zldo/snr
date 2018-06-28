<?php
include_once './snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;
$pagetitle = 'Редактор сенсора';
//$slidebar = "admin_slidebar.php";
$db = $snrapp->db;
if($snrapp->CheckRole('admin')) {
if(isset($_GET['sensor_id'])) { // Идентификатор редактируемого сенсора
    $sensor_id = $_GET['sensor_id'];
    $pagetitle = 'Редактирование параметров сенсора';
    $st = $db->prepare('SELECT * FROM sensors WHERE sensor_id=:sensor_id');
    $st->bindValue(':sensor_id', $sensor_id, PDO::PARAM_INT);
    if($st->execute()) {
            $sensor = $st->fetch(PDO::FETCH_ASSOC); 
    } else {
        $sensor = null;
        $sensor_id = null;
        $page_error = "Устройство не найдено.";
    }
} else {
    $sensor_id = null;
    $pagetitle = 'Сенсор не найден';
    $sensor = null;
}

if(isset($_POST['display_name'])) { // Обработка сохранения
    $sensor_id = $_POST['sensor_id'];
    $st = $db->prepare('DELETE FROM sensors_alerts WHERE sensor_id = :sensor_id');
    $st->bindValue(':sensor_id', $sensor_id, PDO::PARAM_INT);
    $st->execute();
    $st = $db->prepare('UPDATE sensors SET '
                . ' enablied = :enablied, '
                . ' display_name = :display_name, '
                . ' store_history = :store_history, '
                . ' allow_chart = :allow_chart, '
                . ' check_interval = :check_interval, '
                . ' max_retry_count = :max_retry_count, '
                . ' timeout = :timeout, '
                . ' signaled_check_interval = :signaled_check_interval, '
                . ' stable_timeout = :stable_timeout, '
                . ' signaled_error = :signaled_error, '
                . ' exp_vars = :exp_vars, '
                . ' force_update = 1 '
                . ' WHERE sensor_id = :sensor_id');
    $st->bindValue(':sensor_id', $sensor_id, PDO::PARAM_INT);
    $st->bindValue(':display_name', $_POST['display_name'], PDO::PARAM_STR);
    $st->bindValue(':enablied', isset($_POST['enablied']), PDO::PARAM_BOOL);
    $st->bindValue(':store_history', isset($_POST['store_history']), PDO::PARAM_BOOL);
    $st->bindValue(':allow_chart', isset($_POST['allow_chart']), PDO::PARAM_BOOL);
    $st->bindValue(':check_interval', $_POST['check_interval'], PDO::PARAM_INT);
    $st->bindValue(':max_retry_count', $_POST['max_retry_count'], PDO::PARAM_INT);
    $st->bindValue(':timeout', $_POST['timeout'], PDO::PARAM_INT);
    $st->bindValue(':signaled_check_interval', $_POST['signaled_check_interval'], PDO::PARAM_INT);
    $st->bindValue(':stable_timeout', $_POST['stable_timeout'], PDO::PARAM_INT);
    $st->bindValue(':signaled_error', $_POST['signaled_error'], PDO::PARAM_STR);
    if(!isset($_POST['exp_var_enablied']) and isset($_POST['exp_vars'])){
        $vars = '';
        foreach ($_POST['exp_vars'] as $key => $value) {
            $vars .= $key . '=' . $value . "\n\r";
        }
        $st->bindValue(':exp_vars', $vars, PDO::PARAM_STR);
    } else {
        $st->bindValue(':exp_vars', NULL, PDO::PARAM_STR);
    }   
    if(!$st->execute()){
        print_r($st->errorInfo());        
        $st->debugDumpParams();
        exit;
    }
    $st = $db->prepare('INSERT INTO sensors_alerts (sensor_id, alert_id) VALUES (:sensor_id, :alert_id)');
    foreach ($_POST['alerts_ids'] as $alert_id) {
        $st->bindValue(':sensor_id', $sensor_id, PDO::PARAM_INT);
        $st->bindValue(':alert_id', $alert_id, PDO::PARAM_INT);
        $st->execute();
        //print_r($st->errorInfo());
    }
    //exit;
    header('Location: sensor_editor.php?sensor_id='.$sensor_id);
    exit;   
}

//$slidebar = "mainslidebar.php";
}
$content = basename($_SERVER['PHP_SELF']);
if($rout) exit; 
include_once "htmlstart.php";
if($snrapp->CheckRole('admin')) {

    // Получение информации о классе сенсора
    $st = $db->prepare('SELECT * FROM sensors_classes WHERE class_id = :class_id');
    $st->bindValue(':class_id', $sensor['class_id'], PDO::PARAM_INT);
    if($st->execute()){
        $class = $st->fetch(PDO::FETCH_ASSOC);
    } else {
        print_r($st->errorInfo());
    }
    
    // Дополнительные переменные
    $exp_vars = parse_ini_string($class['exp_vars_desc']);
    if(trim($sensor['exp_vars']) == '') {
        $exp_defs = parse_ini_string($class['exp_vars_def']);
    } else {
        $exp_defs = parse_ini_string($sensor['exp_vars']);
    }
    $vars = '';
    foreach ($exp_vars as $key => $value) {
        $vars .= '<tr><td class="blanktable">'.$value.'</td><td class="blanktable"><input style ="width: 250px" name="exp_vars['.$key.']" value="'.$exp_defs[$key].'"></td></tr>';
    }
    
    $selected_alerts = array();
    if(isset($sensor_id)) {
        $st = $db->prepare('SELECT alert_id FROM sensors_alerts WHERE sensor_id = :sensor_id');
        $st->bindValue(':sensor_id', $sensor['sensor_id'], PDO::PARAM_INT);
        if($st->execute()){
            while($row = $st->fetch(PDO::FETCH_NUM)){
                $selected_alerts[$row[0]] = true;
            }
        }
    }
    
    $alerts = '';
    $st = $db->prepare('SELECT alert_id, display_name, description FROM alerts ');
    if($st->execute()){
        while($row = $st->fetch(PDO::FETCH_ASSOC)){
            $checked = isset($selected_alerts[$row['alert_id']]) ? 'checked' : '';
            $alerts .= '<input type="checkbox" name="alerts_ids[]" value="'.$row['alert_id'].'" '. $checked .'>'.$row['display_name'].'</a><br>';
        }
    }
?>

<?php if(!isset($sensor_id)){ 
    echo '<h2><a>Сенсор не найден.</a></h2>';
} else {
    echo '<h2><a>Редактирование сенсора "'.$class['sensor_class'].'".</a></h2>';
?>    
<a href="device_editor.php?device_id=<?php echo $sensor['device_id']; ?>"><img src="images/cog.png">Редактирование устройства</a>,
&nbsp;<a href="devices.php">Список устройств</a>
    
<div class="entry">
    
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
        <?php if(isset($sensor_id)){
           echo '<input type="hidden" name="sensor_id" value="'.$sensor_id.'">';
        } ?>
        <table class="blanktable" valign="top"><tr class="blanktable"><td class="blanktable" valign="top"> 
            <table class="blanktable">
            <caption>Параметры сенсора:</caption>             
            <tr><td class="blanktable">Отображаемое имя объекта</td><td class="blanktable"><input style ="width: 250px" name="display_name" value="<?php echo $sensor['display_name'];?>"></td></tr>
            <tr><td class="blanktable"><b>Опрашивать этот сенсор</b></td><td class="blanktable"><input type="checkbox" style= "width: 250px" name="enablied" <?php echo $sensor['enablied'] ? 'checked': '' ;?> ></td></tr>
            <tr><td class="blanktable">Хранить историю изменений значений</td><td class="blanktable"><input type="checkbox" style= "width: 250px" name="store_history" <?php echo $sensor['store_history'] ? 'checked' : '';?> ></td></tr>        
            <tr><td class="blanktable">Для значения возможно построение графика</td><td class="blanktable"><input type="checkbox" style= "width: 250px" name="allow_chart" <?php echo $sensor['allow_chart'] ? 'checked' : '';?> ></td></tr>
            <tr><td class="blanktable">Минимальный интервал обновления значения в секундах</td><td class="blanktable"><input style ="width: 250px" name="check_interval" value="<?php echo $sensor['check_interval'];?>"></td></tr>
            <tr><td class="blanktable">Максимальное число попыток опроса датчика (при неудаче опроса)</td><td class="blanktable"><input style ="width: 250px" name="max_retry_count" value="<?php echo $sensor['max_retry_count'];?>"></td></tr>
            <tr><td class="blanktable">Таймаут опроса милисекунд</td><td class="blanktable"><input style ="width: 250px" name="timeout" value="<?php echo $sensor['timeout'];?>"></td></tr>        
            <tr><td class="blanktable">Интервал обновления в состоянии "тревога" секунд<br> (-1 не изменять интервал опроса)</td><td class="blanktable"><input style ="width: 250px" name="signaled_check_interval" value="<?php echo $sensor['signaled_check_interval'];?>"></td></tr>
            <tr><td class="blanktable">Тамаут стабилизации значения<br> (-1 без ожидания стабилизации)</td><td class="blanktable"><input style ="width: 250px" name="stable_timeout" value="<?php echo $sensor['stable_timeout'];?>"></td></tr>
            <tr><td class="blanktable">Сообщение о состоянии "тревога"</td><td class="blanktable"><input style ="width: 250px" name="signaled_error" value="<?php echo $sensor['signaled_error'];?>"></td></tr>                                                                                              
            <?php 
              if($vars != ''){
                  echo '<tr><td class="blanktable" colspan="2"><h3>Дополнительные параметры:<h3></td></tr>';
                  $checked = $sensor['exp_vars'] == '' ? 'checked': '';
                  echo '<tr><td class="blanktable"><b>Использовать параметры по умолчанию для класса сенсоров</b></td><td class="blanktable"><input type="checkbox" style= "width: 250px" name="exp_var_enablied" ' . $checked . ' ></td></tr>';
                  echo $vars;
              }
            ?>
            </td></tr></table></td>
        <td class="blanktable" valign="top">
        <table class="blanktable"> 
        <caption>События:</caption>
        <tr class="blanktable"><td class="blanktable">
          <?php echo $alerts ?>
        </tr></td>
        </table>
        </table>
        <br><input id='save_btn' type="submit" value="Сохранить"></form><br>

</div>
<?php }
}
 else {
    echo "<h2><a>Доступ к данному разделу запрещен</a></h2>";
    echo "<div class='entry'>";
    echo "</div>";
}