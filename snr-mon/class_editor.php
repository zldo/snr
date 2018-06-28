<?php
include_once './snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;
$pagetitle = 'Редактор класса сенсора';
//$slidebar = "admin_slidebar.php";
$db = $snrapp->db;
if($snrapp->CheckRole('admin')) {
    if(isset($_GET['deleted_id']) and ($_GET['deleted_id'] != '')) {
        $st = $db->prepare('DELETE FROM sensors_classes WHERE class_id=:class_id');
        $st->bindValue(':class_id', $_GET['deleted_id'], PDO::PARAM_INT);
        if(!$st->execute()) {
            print_r($st->errorInfo());
        } else {
            header('Location: classes_list.php');
            exit;
        }
    }

    if(isset($_GET['class_id'])) { // Идентификатор редактируемого устройства
        $class_id = $_GET['class_id'];
        $pagetitle = 'Редактирование параметров класса сенсоров';
        $st = $db->prepare('SELECT * FROM sensors_classes WHERE class_id=:class_id');
        $st->bindValue(':class_id', $class_id, PDO::PARAM_INT);
        if($st->execute()) {
                $class = $st->fetch(PDO::FETCH_ASSOC); 
        } else {
            $class = null;
            $page_error = "Класс не найден.";
        }
    } else {
        $class_id = null;
        $pagetitle = 'Добавление класса сенсоров';
        $class = null;
    }

    if(!isset($class)) {
        $class['device_model'] = 'Модель устройства';
        $class['sensor_class'] = 'Класс сенсоров';
        $class['description'] = '';
        $class['snmp_oid'] = '';
        $class['snmp_version'] = 0;
        $class['snmp_V3Flags'] = 0;
        $class['snmp_V3Auth'] = 0;
        $class['snmp_datatype'] = 0;
        $class['value_pt'] = '';
        $class['allow_chart'] = 0;
        $class['error_int_value'] = 0;
        $class['check_interval'] = 120;
        $class['max_retry_count'] = 3;
        $class['timeout'] = 3000;
        $class['signaled_error'] = '';
        $class['stable_timeout'] = -1;
        $class['signaled_error'] = '';
        $class['signaled_error'] = '';
        $class['state_expr'] = 'str0="Ok"';
        $class['value_expr'] = 'str0';
        $class['value_int_expr'] = 'int0';
        $class['exp_vars_desc'] = 'var0=Переменная 1';
        $class['exp_vars_def'] = 'var0=1';
    }

    if(isset($_POST['sensor_class'])) { // Обработка сохранения
        $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : null;
        if(isset($class_id)) { // Обновление существующего класса
            $st = $db->prepare('UPDATE sensors_classes SET 
                                device_model    = :device_model,
                                sensor_class    = :sensor_class,
                                description     = :description,
                                snmp_version    = :snmp_version,
                                snmp_oid        = :snmp_oid,
                                snmp_V3Flags    = :snmp_V3Flags,
                                snmp_V3Auth     = :snmp_V3Auth,
                                state_expr      = :state_expr,
                                value_expr      = :value_expr,
                                value_int_expr  = :value_int_expr,
                                allow_chart     = :allow_chart,
                                value_pt        = :value_pt,
                                error_int_value = :error_int_value,
                                check_interval  = :check_interval,
                                max_retry_count = :max_retry_count,
                                timeout         = :timeout,
                                signaled_error  = :signaled_error,
                                stable_timeout  = :stable_timeout,
                                exp_vars_desc   = :exp_vars_desc,
                                exp_vars_def    = :exp_vars_def'
                    . ' WHERE class_id = :class_id');
            $st->bindValue(':class_id', $class_id, PDO::PARAM_INT);
        } else { // Добавление нового устройства
            $st = $db->prepare('INSERT INTO sensors_classes (device_model, sensor_class, description, snmp_version, snmp_oid, snmp_V3Flags, snmp_V3Auth, state_expr, value_expr, value_int_expr, allow_chart, value_pt, error_int_value, check_interval, max_retry_count, timeout, signaled_error, stable_timeout, exp_vars_desc, exp_vars_def)
                                             VALUES (:device_model, :sensor_class, :description, :snmp_version, :snmp_oid, :snmp_V3Flags, :snmp_V3Auth, :state_expr, :value_expr, :value_int_expr, :allow_chart, :value_pt, :error_int_value, :check_interval, :max_retry_count, :timeout, :signaled_error, :stable_timeout, :exp_vars_desc, :exp_vars_def)');
        }
        $st->bindValue(':device_model', $_POST['device_model'], PDO::PARAM_STR);
        $st->bindValue(':sensor_class', $_POST['sensor_class'], PDO::PARAM_STR);
        $st->bindValue(':description', $_POST['description'], PDO::PARAM_STR);
        $st->bindValue(':snmp_version', $_POST['snmp_version'], PDO::PARAM_INT);
        $st->bindValue(':snmp_oid', $_POST['snmp_oid'], PDO::PARAM_STR);
        $st->bindValue(':snmp_V3Flags', $_POST['snmp_V3Flags'], PDO::PARAM_INT);
        $st->bindValue(':snmp_V3Auth', $_POST['snmp_V3Auth'], PDO::PARAM_INT);
        //$st->bindValue(':snmp_datatype', $_POST['snmp_datatype'], PDO::PARAM_INT);
        $st->bindValue(':state_expr', $_POST['state_expr'], PDO::PARAM_STR);
        $st->bindValue(':value_expr', $_POST['value_expr'], PDO::PARAM_STR);
        $st->bindValue(':value_int_expr', $_POST['value_int_expr'], PDO::PARAM_STR);
        $st->bindValue(':allow_chart', isset($_POST['allow_chart']), PDO::PARAM_BOOL);
        $st->bindValue(':value_pt', $_POST['value_pt'], PDO::PARAM_STR);
        $st->bindValue(':error_int_value', $_POST['error_int_value'], PDO::PARAM_INT);
        $st->bindValue(':check_interval', $_POST['check_interval'], PDO::PARAM_INT);
        $st->bindValue(':max_retry_count', $_POST['max_retry_count'], PDO::PARAM_INT);
        $st->bindValue(':timeout', $_POST['timeout'], PDO::PARAM_INT);
        $st->bindValue(':signaled_error', $_POST['signaled_error'], PDO::PARAM_STR);
        $st->bindValue(':stable_timeout', $_POST['stable_timeout'], PDO::PARAM_INT);
        $st->bindValue(':exp_vars_desc', $_POST['exp_vars_desc'], PDO::PARAM_STR);
        $st->bindValue(':exp_vars_def', $_POST['exp_vars_def'], PDO::PARAM_STR);
        if($st->execute()){
            if(!isset($class_id)) {
                $class_id = $db->lastInsertId();
            }
            header('Location: class_editor.php?class_id='.$class_id);
        } else {
            print_r($st->errorInfo());
            $st->debugDumpParams();
        }
    }

    //$slidebar = "mainslidebar.php";
    $content = basename($_SERVER['PHP_SELF']);
}
if($rout) exit; 
include_once "htmlstart.php";
if($snrapp->CheckRole('admin')) {
    
    $snmp_ver = '<option value="0" ' . ($class['snmp_version'] == 0 ? 'selected' : '') . '>SNMPv1</option>' .
            '<option value="1" ' . ($class['snmp_version'] == 1 ? 'selected' : '') . '>SNMPv2c</option>' .
            '<option value="2" ' . ($class['snmp_version'] == 1 ? 'selected' : '') . '>SNMPv3</option>';

    $snmp_Flags = '<option value="0" ' . ($class['snmp_V3Flags'] == 0 ? 'selected' : '') . '>NoAuthNoPriv</option>' .
              '<option value="1" ' . ($class['snmp_V3Flags'] == 1 ? 'selected' : '') . '>AuthNoPriv</option>' .
              '<option value="2" ' . ($class['snmp_V3Flags'] == 1 ? 'selected' : '') . '>AuthPriv</option>';

    $snmp_Auth = '<option value="0" ' . ($class['snmp_V3Auth'] == 0 ? 'selected' : '') . '>MD5</option>' .
             '<option value="1" ' . ($class['snmp_V3Auth'] == 1 ? 'selected' : '') . '>SHA1</option>';
    
    $st = $db->prepare('SELECT 1 FROM sensors WHERE class_id=:class_id LIMIT 1');
    $st->bindValue(':class_id', $class_id, PDO::PARAM_INT);
    if($st->execute()) {
            $class_used = $st->fetchColumn() == 1; 
    } else {
        $class_used = false;
    }
?>

<?php if(isset($class_id)){ 
    echo '<h2><a>Редактирование класса сенсоров "'.$class['sensor_class'].'".</a></h2>';
} else {
    echo '<h2><a>Добавление нового класса сенсоров.</a></h2>';
}
?>    
<a href="classes_list.php"><img src="images/cog.png">Список классов</a>

    
<div class="entry">
    
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
        <?php if(isset($class_id)){
           echo '<input type="hidden" name="class_id" value="'.$class_id.'">';
        } ?>
        <table class="blanktable">
        <caption>Параметры класса:</caption>
        <?php if(!isset($class_id) or !$class_used){ // Добавление ?>            
                <tr><td class="blanktable">Модель устройства</td><td class="blanktable"><input style ="width: 350px" name="device_model" value="<?php echo htmlspecialchars($class['device_model']);?>"></td></tr>
                <tr><td class="blanktable">Имя классса сенсоров</td><td class="blanktable"><input style ="width: 350px" name="sensor_class" value="<?php echo htmlspecialchars($class['sensor_class']);?>"></td></tr>                
        <?php } else { ?>
                <tr><td class="blanktable">Модель устройства</td><td class="blanktable"><input type="hidden" name="device_model" value="<?php echo $class['device_model'];?>"><?php echo $class['device_model'];?></td></tr>
                <tr><td class="blanktable">Имя классса сенсоров</td><td class="blanktable"><input type="hidden" name="sensor_class" value="<?php echo $class['sensor_class'];?>"><?php echo $class['sensor_class'];?></td></tr>                
        <?php } ?>    
        <tr><td class="blanktable">Описание</td><td class="blanktable"><input style ="width: 350px" name="description" value="<?php echo $class['description'];?>"></td></tr>
        <tr><td class="blanktable">SNMP OID значения</td><td class="blanktable"><input style ="width: 350px" name="snmp_oid" value="<?php echo htmlspecialchars($class['snmp_oid']);?>"></td></tr>
        <tr><td class="blanktable">Версия протокола SNMP</td><td class="blanktable"><select style ="width: 350px" name="snmp_version"><?php echo $snmp_ver;?>"></select></td></tr>
        <tr><td class="blanktable">Флаги для протокола SNMPv3</td><td class="blanktable"><select style ="width: 350px" name="snmp_V3Flags"><?php echo $snmp_Flags;?>"></select></td></tr>
        <tr><td class="blanktable">Тип аутентификации для протокола SNMPv3</td><td class="blanktable"><select style ="width: 350px" name="snmp_V3Auth"><?php echo $snmp_Auth;?>"></select></td></tr>
        <!--<tr><td class="blanktable">Тип SNMP данных для значения</td><td class="blanktable"><input style ="width: 350px" name="snmp_datatype" value="<?php //echo htmlspecialchars($class['snmp_datatype']);?>"></td></tr> -->        
        <tr><td class="blanktable">Минимальный интервал обновления значения в секундах</td><td class="blanktable"><input style ="width: 350px" name="check_interval" value="<?php echo htmlspecialchars($class['check_interval']);?>"></td></tr>
        <tr><td class="blanktable">Максимальное число попыток запроса данных</td><td class="blanktable"><input style ="width: 350px" name="max_retry_count" value="<?php echo htmlspecialchars($class['max_retry_count']);?>"></td></tr>
        <tr><td class="blanktable">Таймаут получения значения (милисекунд)</td><td class="blanktable"><input style ="width: 350px" name="timeout" value="<?php echo htmlspecialchars($class['timeout']);?>"></td></tr>
        <tr><td class="blanktable">Описание "сигнального" состояния</td><td class="blanktable"><input style ="width: 350px" name="signaled_error" value="<?php echo htmlspecialchars($class['signaled_error']);?>"></td></tr>
        <tr><td class="blanktable">Тамаут стабилизации значения (для изменения состояния)</td><td class="blanktable"><input style ="width: 350px" name="stable_timeout" value="<?php echo htmlspecialchars($class['stable_timeout']);?>"></td></tr>        
        <tr><td class="blanktable">Единица измерения значения</td><td class="blanktable"><input style ="width: 350px" name="value_pt" value="<?php echo htmlspecialchars($class['value_pt']);?>"></td></tr>
        <tr><td class="blanktable">Для значения возможно построение графика</td><td class="blanktable"><input type="checkbox" name="allow_chart" <?php echo ($class['allow_chart'] ? 'checked' : ''); ?>></td></tr>
        <tr><td class="blanktable">Числовое значение для состояния ошибки, неизвестного, не в сети</td><td class="blanktable"><input style ="width: 350px" name="error_int_value" value="<?php echo htmlspecialchars($class['error_int_value']);?>"></td></tr>
        <tr><td class="blanktable">Выражение для определения состояния сигнализировано/нет</td><td class="blanktable"><textarea style ="width: 350px" name="state_expr"><?php echo htmlspecialchars($class['state_expr']);?></textarea></td></tr>
        <tr><td class="blanktable">Выражение для определения значения</td><td class="blanktable"><textarea style ="width: 350px" name="value_expr"><?php echo htmlspecialchars($class['value_expr']);?></textarea></td></tr>        
        <tr><td class="blanktable">Выражение для определения числового значения</td><td class="blanktable"><textarea style ="width: 350px" name="value_int_expr"><?php echo htmlspecialchars($class['value_int_expr']);?></textarea></td></tr>
        <tr><td class="blanktable">Переменные для вычисления значения - описание</td><td class="blanktable"><textarea  style ="width: 350px; height: 100px" name="exp_vars_desc"><?php echo htmlspecialchars($class['exp_vars_desc']);?></textarea></td></tr>
        <tr><td class="blanktable">Значения по умолчанию для переменных</td><td class="blanktable"><textarea style ="width: 350px; height: 100px" name="exp_vars_def"><?php echo htmlspecialchars($class['exp_vars_def']);?></textarea></td></tr>
        
        <?php if(isset($class_id) and !$class_used){ ?>
        <tr>
            <td class="blanktable" colspan="2">
                <a href="class_editor.php?deleted_id=<?php echo $class_id; ?>" onclick="return confirm('Вы действительно хотите удалить этот класс?') ? true : false;">
                    <img src="images/delete.png"> Удалить устройство <b> <?php echo $class['sensor_class']; ?> </b>
                </a>
            </td>
        </tr>
        <?php } else { ?>
        <tr>
            <?php if(isset($class_id)){ ?>
            <td class="blanktable" colspan="2">
                Вы не можете удалить этот класс сенсоров, так как он используется.
            </td>
             <?php } ?>
        </tr>
        <?php }?>
        </td></tr></table>
        <br><input id='save_btn' type="submit" value="Сохранить"></form><br>

</div>
<?php } else {
    echo "<h2><a>Доступ к данному разделу запрещен</a></h2>";
    echo "<div class='entry'>";
    echo "</div>";
}