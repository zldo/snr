<?php

if(isset($_GET['selected_model'])) {
    include_once './snrapp.php';
    global $snrapp;
    $db = $snrapp->db;
    $st = $db->prepare('SELECT sensor_class, class_id FROM sensors_classes WHERE device_model = :device_model');
    $st->bindParam(':device_model', $_GET['selected_model'], PDO::PARAM_STR);
    $sensors = '';
    if($st->execute()){
        while($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $sensors .= '<input type="checkbox" name="sensors_ids[]" value="'.$row['class_id'].'">'.$row['sensor_class'].'</a><br>';           
        }
        echo $sensors;
    } else {
        print_r($st->errorInfo());
    }
}