<?php
header( "Pragma", "no-cache" );
header( "Cache-Control", "no-cache" );
header( "Expires", 0 );
include_once './snrapp.php';
global $snrapp;
if($snrapp->auth_ok) {
    $ids = explode(',', $_GET['ids']);
    $res = array();
    $st = $snrapp->db->prepare('SELECT sensor_id,       
                                    TRIM(CONCAT(sensors.value, " ", sensors_classes.value_pt)) as value,        
                                    sensors_classes.value_pt,
                                    sensors.state
                                FROM sensors LEFT JOIN sensors_classes USING (class_id)
                                WHERE sensor_id in ('.  implode(',', $ids).')');
     if($st->execute()) {
         while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $result = '';
            if($row['state'] < 0) {
                $result .= '<font color="orange">'.SNRStateToStr($row['state']).'</font>';
            } else {
                if($row['state'] == 0) {
                    $result .= $row['value'];
                } else {
                    $result .= '<font color="red">'.SNRStateToStr($row['state']) . ' - ' . $row['value'] . '</font>';
                }
            }            
            $res[] = array('name' => 'sensor_mon_' . $row['sensor_id'], 'text' => $result);
         }
         
     }
     echo json_encode($res);  
}

