<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
include_once './snrapp.php';

global $snrapp;


$st = $snrapp->db->prepare('SELECT state_log.changed, state_log.old_value, state_log.new_value, state_log.old_state, state_log.new_state, state_log.error, devices.organization, devices.location, devices.display_name, sensor_class FROM state_log LEFT JOIN devices USING (device_id) LEFT JOIN sensors USING (sensor_id) LEFT JOIN sensors_classes USING (class_id) ORDER BY state_log.changed DESC LIMIT 500');

if($st->execute()){
    while($row = $st->fetch(PDO::FETCH_ASSOC)){
        $row['changed'] = date("Y.m.d H:i:s", strtotime($row["changed"]));
        echo $row['changed'] . ': <b>' . $row['organization'] . ' / '.$row['location'].' / '.$row['display_name'].'</b> / '.$row['sensor_class'].' - ';
        switch ($row['new_state']) {
            case -4:
                  echo '<font color=red>Произошла непредвиденная ошибка: "'.$row['error'].'.<br></font>';
                break;
            
            case -3:
                  echo '<font color=red>нестабилен. '.$row['new_value'].'.<br></font>';
                break;
            
            case -2:
                  echo '<font color=blue>недоступен ('.$row['error'].').<br></font>';
                break;
            
            case -1:
                  echo '<font color=blue>Информация о состоянии недоступна (служба мониторинга не функционирует)<br></font>';
                break;
            
            case 0:
                  echo '<font color=green>в норме. '.$row['new_value'].'.<br></font>';
                break;
            
            case 1:
                  echo '<font color=red>'.$row['error'].'.<br></font>';
                break;

            default:
                break;
        }
        echo '';
    }
}
