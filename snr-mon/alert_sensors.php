<?php
include_once './snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;
$pagetitle = 'Назначение обработчика событя датчику';
//$slidebar = "admin_slidebar.php";
$db = $snrapp->db;

if($snrapp->CheckRole('admin')) {
    if(isset($_GET['alert_id'])) { // Идентификатор редактируемого события
        $alert_id = $_GET['alert_id'];
        $st = $db->prepare('SELECT * FROM alerts WHERE alert_id=:alert_id');
        $st->bindValue(':alert_id', $alert_id, PDO::PARAM_INT);
        if($st->execute()) {
                $alert = $st->fetch(PDO::FETCH_ASSOC); 
        } else {
            $alert = null;
            $alert_id = null;
            $page_error = "Событие не найдено.";
        }
    } else {
        $alert_id = null;
        $pagetitle = 'Событие не найдено';
        $alert = null;
    }

    if(isset($_POST['alert_id'])) { // Обработка сохранения
        $alert_id = $_POST['alert_id'];
        $st = $db->prepare('DELETE FROM sensors_alerts WHERE alert_id = :alert_id');
        $st->bindValue(':alert_id', $alert_id, PDO::PARAM_INT);
        $st->execute();

        $st = $db->prepare('INSERT INTO sensors_alerts (sensor_id, alert_id) VALUES (:sensor_id, :alert_id)');
        foreach ($_POST['sensors_ids'] as $sensor_id) {
            $st->bindValue(':alert_id', $alert_id, PDO::PARAM_INT);
            $st->bindValue(':sensor_id', $sensor_id, PDO::PARAM_INT);
            $st->execute();
            //print_r($st->errorInfo());
        }
        //exit;
        header('Location: alert_editor.php?alert_id='.$alert_id);
        exit;   
    }

    //$slidebar = "mainslidebar.php";
    $content = basename($_SERVER['PHP_SELF']);
}
if($rout) exit; 
include_once "htmlstart.php";
if($snrapp->CheckRole('admin')) {
    $selected_sensors = array();
    if(isset($alert_id)) {
        $st = $db->prepare('SELECT sensor_id FROM sensors_alerts WHERE alert_id = :alert_id');
        $st->bindValue(':alert_id', $alert['alert_id'], PDO::PARAM_INT);
        if($st->execute()){
            while($row = $st->fetch(PDO::FETCH_NUM)){
                $selected_sensors[$row[0]] = true;
            }
        }
    }
    
    $alerts = '';
    $curlocation ='';
    $st = $db->prepare('SELECT sensor_id, sensor_display_name, CONCAT(organization, ", ", location, ", ", display_name, ", ", device_model) AS location FROM ' .
                       ' (SELECT device_id, sensor_id, IF(ISNULL(display_name) OR display_name = "", sensor_class, display_name) AS sensor_display_name FROM sensors LEFT JOIN sensors_classes USING (class_id)) tbl ' .
                       ' LEFT JOIN devices USING (device_id) ORDER BY organization, location, display_name ');
    if($st->execute()){
        while($row = $st->fetch(PDO::FETCH_ASSOC)){
            if($curlocation != $row['location']) {
                $alerts .= '<h4>' . $row['location'] . '</h4>';
                $curlocation = $row['location'];
            }
            $checked = isset($selected_sensors[$row['sensor_id']]) ? 'checked' : '';
            $alerts .= '<input type="checkbox" name="sensors_ids[]" value="'.$row['sensor_id'].'" '. $checked .'>'.$row['sensor_display_name'].'</a><br>';
        }
    }
?>

<?php if(!isset($alert_id)){ 
    echo '<h2><a>Событие не найдено.</a></h2>';
} else {
    echo '<h2><a>Редактирование события "'.$alert['display_name'].'".</a></h2>';
?>    

<a href="alerts_list.php"><img src="images/cog.png">Список типов событий</a>

<div class="entry">
    
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
        <?php if(isset($alert_id)){
           echo '<input type="hidden" name="alert_id" value="'.$alert_id.'">';
        } 
        echo $alerts;
        ?>
        <br><input id='save_btn' type="submit" value="Сохранить"></form><br>

</div>
<?php }
} else {
    echo "<h2><a>Доступ к данному разделу запрещен</a></h2>";
    echo "<div class='entry'>";
    echo "</div>";
}