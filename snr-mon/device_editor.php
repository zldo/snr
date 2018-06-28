<?php
include_once './snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;
$pagetitle = 'Редактор устройства';
//$slidebar = "admin_slidebar.php";
$db = $snrapp->db;
if($snrapp->CheckRole('admin')) {
    if(isset($_GET['deleted_id']) and ($_GET['deleted_id'] != '')) {
        $st = $db->prepare('DELETE FROM devices WHERE device_id=:device_id');
        $st->bindParam(':device_id', $_GET['deleted_id'], PDO::PARAM_INT);
        if(!$st->execute()) {
            print_r($st->errorInfo());
        } else {
            header('Location: devices.php');
            exit;
        }
    }

    if(isset($_GET['device_id'])) { // Идентификатор редактируемого устройства
        $device_id = $_GET['device_id'];
        $pagetitle = 'Редактирование параметров устройства';
        $st = $db->prepare('SELECT * FROM devices WHERE device_id=:device_id');
        $st->bindParam(':device_id', $device_id, PDO::PARAM_INT);
        if($st->execute()) {
                $device = $st->fetch(PDO::FETCH_ASSOC); 
        } else {
            $device = null;
            $page_error = "Устройство не найдено.";
        }
    } else {
        $device_id = null;
        $pagetitle = 'Добавление устройства';
        $device = null;
    }

    if(!isset($device)) {
        $device['display_name'] = 'Новое устройство';
        $device['description'] = '';
        $device['organization'] = '';
        $device['location'] = '';
        $device['snmp_username'] = '';
        $device['snmp_password'] = 'public';
        $device['snmp_comunity'] = 'public';
        $device['snmp_host'] = '';
        $device['snmp_port'] = 161;
    }

    if(isset($_POST['display_name'])) { // Обработка сохранения
        $device_id = $_POST['device_id'];
        $sensors = isset($_POST['sensors_class_ids'])?implode(',', $_POST['sensors_class_ids']):'-1'; // Отмеченные сенсоры
        //echo 'DELETE FROM sensors WHERE device_id = :device_id and not (class_id in ('.$sensors.'))';
        //exit;
        if(isset($device_id)) { // Обновление существующего устройства
            //Удаление "неотмеченных" сенсоров
            $st = $db->prepare('DELETE FROM sensors WHERE device_id = :device_id and not (class_id in ('.$sensors.'))');
            $st->bindParam(':device_id', $device_id, PDO::PARAM_INT);
            if(!$st->execute()) {
                print_r($st->errorInfo());
            }
            $st = $db->prepare('UPDATE devices SET '
                    . ' display_name = :display_name, '
                    . ' description = :description, '
                    . ' organization = :organization, '
                    . ' location = :location, '
                    . ' snmp_username = :snmp_username, '
                    . ' snmp_password = :snmp_password, '
                    . ' snmp_comunity = :snmp_comunity, '
                    . ' snmp_host = :snmp_host, '
                    . ' snmp_port = :snmp_port, '
                    . ' contact_id = :contact_id '
                    . ' WHERE device_id = :device_id');
            $st->bindParam(':device_id', $device_id, PDO::PARAM_INT);
        } else { // Добавление нового устройства
            $st = $db->prepare('INSERT INTO devices ( device_model,  display_name,  description,  organization,  location,  snmp_username,  snmp_password,  snmp_comunity,  snmp_host,  snmp_port,  contact_id)
                                             VALUES (:device_model, :display_name, :description, :organization, :location, :snmp_username, :snmp_password, :snmp_comunity, :snmp_host, :snmp_port, :contact_id)');
        }
        if(!isset($device_id)) {
            $st->bindParam(':device_model', $_POST['device_model'], PDO::PARAM_STR);
        }
        $st->bindParam(':display_name', $_POST['display_name'], PDO::PARAM_STR);
        $st->bindParam(':description', $_POST['description'], PDO::PARAM_STR);
        $st->bindParam(':organization', $_POST['organization'], PDO::PARAM_STR);
        $st->bindParam(':location', $_POST['location'], PDO::PARAM_STR);
        $st->bindParam(':snmp_username', $_POST['snmp_username'], PDO::PARAM_STR);
        $st->bindParam(':snmp_password', $_POST['snmp_password'], PDO::PARAM_STR);
        $st->bindParam(':snmp_comunity', $_POST['snmp_comunity'], PDO::PARAM_STR);
        $st->bindParam(':snmp_host', $_POST['snmp_host'], PDO::PARAM_STR);
        $st->bindParam(':snmp_port', $_POST['snmp_port'], PDO::PARAM_INT);
        $st->bindParam(':contact_id', $_POST['contact_id'], PDO::PARAM_INT);
        if($st->execute()){
            if(!isset($device_id)) {
                $device_id = $db->lastInsertId();
            }
        } else {
            print_r($st->errorInfo());
            //$st->debugDumpParams();
        }

        //exit;
        // Добавление сенсоров к устройству
        $st = $db->prepare('INSERT IGNORE INTO sensors 
                            (device_id, force_update, class_id, allow_chart, check_interval, max_retry_count, timeout) 
                            SELECT '.$device_id.', 1, class_id, allow_chart, check_interval, max_retry_count, timeout FROM sensors_classes WHERE class_id IN (' . $sensors . ');');
        if(!$st->execute()){
            print_r($st->errorInfo());
        } else {
            header('Location: device_editor.php?device_id='.$device_id);
            exit;
        }   
        $device[':device_model'] = $_POST['device_model'];
        $device['display_name'] = $_POST['display_name'];
        $device['description'] = $_POST['description'];
        $device['organization'] = $_POST['organization'];
        $device['location'] = $_POST['location'];
        $device['snmp_username'] = $_POST['snmp_username'];
        $device['snmp_password'] = $_POST['snmp_password'];
        $device['snmp_comunity'] = $_POST['snmp_comunity'];
        $device['snmp_host'] = $_POST['snmp_host'];
        $device['snmp_port'] = $_POST['snmp_port'];
        $device['contact_id'] = $_POST['contact_id'];
    }

    //$slidebar = "mainslidebar.php";
    
}
$content = basename($_SERVER['PHP_SELF']);
if($rout) exit; 
include_once "htmlstart.php";
if($snrapp->CheckRole('admin')) {

    // Получение списка моделей устройств
    $st = $db->prepare('SELECT DISTINCT device_model FROM sensors_classes');
    $selectfirst = true;
    $models = '';
    $selected_model = '';
    if($st->execute()){
        while($row = $st->fetch(PDO::FETCH_ASSOC)) {
            if(($selectfirst and !isset($device_id)) or (isset($device_id) and $device['device_model'] == $row['device_model'])) {
                $models .= '<option value="'.$row['device_model'].'" selected>'.$row['device_model'].'</option>';
                $selectfirst = false;
                $selected_model = $row['device_model'];
            } else {
                    $models .= '<option value="'.$row['device_model'].'">'.$row['device_model'].'</option>';
            }
        }
    } else {
        print_r($st->errorInfo());
    }
    
    // Получение списка доступных сенсоров
    $enablied_sensors = array(); // Используемые сенсоры
    if(isset($device_id)) {
        $st = $db->prepare('SELECT sensor_id, class_id, display_name FROM sensors WHERE device_id = :device_id');
        $st->bindParam(':device_id', $device_id, PDO::PARAM_INT);
        if($st->execute()){
            while($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $enablied_sensors[$row['class_id']]['sensor_id'] = $row['sensor_id'];
                $enablied_sensors[$row['class_id']]['display_name'] = $row['display_name'];
            }
        } else {
            print_r($st->errorInfo());
        }
    }
    
    $st = $db->prepare('SELECT sensor_class, class_id FROM sensors_classes WHERE device_model = :device_model');
    $st->bindParam(':device_model', $selected_model, PDO::PARAM_STR);
    $sensors = '';
    if($st->execute()){
        while($row = $st->fetch(PDO::FETCH_ASSOC)) {
            if(isset($enablied_sensors[$row['class_id']])) {
                $sens_title = $enablied_sensors[$row['class_id']]['display_name'] != '' ? $enablied_sensors[$row['class_id']]['display_name'].' ('.$row['sensor_class'] . ')' : $row['sensor_class'];
                $sensors .= '<a href="sensor_editor.php?sensor_id='.$enablied_sensors[$row['class_id']]['sensor_id'].'"><input type="checkbox" name="sensors_class_ids[]" value="'.$row['class_id'].'" checked>'.$sens_title.'</a><br>';
            } else {
                $sensors .= '<input type="checkbox" name="sensors_class_ids[]" value="'.$row['class_id'].'">'.$row['sensor_class'].'<br>';
            }           
        }
    } else {
        print_r($st->errorInfo());
    }

?>

<?php if(isset($device_id)){ 
    echo '<h2><a>Редактирование устройства "'.$device['display_name'].'".</a></h2>';
} else {
    echo '<h2><a>Добавление нового устройства.</a></h2>';
}
?>    
<a href="devices.php"><img src="images/cog.png">Список устройств</a>
<script language="javascript" type="text/javascript">
<!-- 
//Browser Support Code
function sensors_upd(){
	var ajaxRequest;  // The variable that makes Ajax possible!
	
	try{
		// Opera 8.0+, Firefox, Safari
		ajaxRequest = new XMLHttpRequest();
	} catch (e){
		// Internet Explorer Browsers
		try{
			ajaxRequest = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try{
				ajaxRequest = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e){
				// Something went wrong
				alert("Your browser broke!");
				return false;
			}
		}
	}
	// Create a function that will receive data sent from the server
        
	ajaxRequest.onreadystatechange = function(){
		if(ajaxRequest.readyState == 4){
			var ajaxDisplay = document.getElementById('sensors');
			ajaxDisplay.innerHTML = ajaxRequest.responseText;
                        document.getElementById('save_btn').enabled = false;
		}
	}
        var val = document.getElementById('sel').value;
        document.getElementById('save_btn').enabled = false; 
	ajaxRequest.open("GET", "sensors_list.php?selected_model=" + val, true);
	ajaxRequest.send(null); 
}
//-->
</script>
    
<div class="entry">
    
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
        <?php if(isset($device_id)){
           echo '<input type="hidden" name="device_id" value="'.$device_id.'">';
        } ?>
        <table class="blanktable">
        <caption>Параметры устройства:</caption>
        <?php if(isset($device_id)){ // Редактирование ?> 
            <tr>
                <td class="blanktable">Модель устройства</td><td class="blanktable"><?php echo $device['device_model']; ?></td>
                <td class="blanktable" rowspan="40" valign="top"><h3>Отслеживаемые сенсоры:</h3><div id="sensors"><?php echo $sensors?></div></td>
            </tr>
        <?php } else { ?>
            <tr>
                <td class="blanktable">Модель устройства</td><td class="blanktable"><select id="sel" onchange="sensors_upd()" style ="width: 250px" name="device_model"><?php echo $models; ?></select></td>
                <td class="blanktable" rowspan="40" valign="top"><h3>Отслеживаемые сенсоры:</h3><div id="sensors"><?php echo $sensors?></div></td>
            </tr>
        <?php } ?>               
        <tr><td class="blanktable">Отображаемое имя объекта</td><td class="blanktable"><input style ="width: 250px" name="display_name" value="<?php echo $device['display_name'];?>"></td></tr>
        <tr><td class="blanktable">Организация</td><td class="blanktable"><input style ="width: 250px" name="organization" value="<?php echo $device['organization'];?>"></td></tr>
        <tr><td class="blanktable">Размещение</td><td class="blanktable"><input style ="width: 250px" name="location" value="<?php echo $device['location'];?>"></td></tr>
        <tr><td class="blanktable">Описание</td><td class="blanktable"><input style ="width: 250px" name="description" value="<?php echo $device['description'];?>"></td></tr>        
        <tr><td class="blanktable">Хост</td><td class="blanktable"><input style ="width: 250px" name="snmp_host" value="<?php echo $device['snmp_host'];?>"></td></tr>
        <tr><td class="blanktable">Порт</td><td class="blanktable"><input style ="width: 250px" name="snmp_port" value="<?php echo $device['snmp_port'];?>"></td></tr>
        <tr><td class="blanktable">Имя пользователя (SNMP)</td><td class="blanktable"><input style ="width: 250px" name="snmp_username" value="<?php echo $device['snmp_username'];?>"></td></tr>
        <tr><td class="blanktable">Пароль (SNMP)</td><td class="blanktable"><input type='password' style ="width: 250px" name="snmp_password" value="<?php echo $device['snmp_password'];?>"></td></tr>
        <tr><td class="blanktable">Comunity SNMP</td><td class="blanktable"><input style ="width: 250px" name="snmp_comunity" value="<?php echo $device['snmp_comunity'];?>"></td></tr>
           
        <?php if(isset($device_id)){ ?>
        <tr>
            <td class="blanktable" colspan="2">
                <a href="device_editor.php?deleted_id=<?php echo $device_id; ?>" onclick="return confirm('Вы действительно хотите удалить это устройство?') ? true : false;">
                    <img src="images/delete.png"> Удалить устройство <b> <?php echo $device['display_name']; ?> </b>
                </a>
            </td>
        </tr>
        <?php } ?>
        </td></tr></table>
        <br><input id='save_btn' type="submit" value="Сохранить"></form><br>
        <script language="javascript" type="text/javascript">
        <!-- 
        sensors_upd();
        //-->
        </script>

</div>
<?php } else {
    echo "<h2><a>Доступ к данному разделу запрещен</a></h2>";
    echo "<div class='entry'>";
    echo "</div>";
}