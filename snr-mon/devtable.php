<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

include_once './snrapp.php';

global $snrapp;

function SecondToStr($time){
    if($time >= 60*60*24) return gmdate('d д. H ч. i мин.', $time);
    else if ($time >= 60*60) return gmdate('H ч. i мин.', $time);
    else return gmdate('i мин.', $time);
}

$st = $snrapp->db->prepare('SELECT * FROM (SELECT tbl1.*, sensors_classes.sensor_class FROM (SELECT
                    devices.device_model,
                    devices.display_name,
                    devices.description,
                    devices.location,
                    devices.snmp_host,
                    devices.organization, 
                    sensors.stable_state,
                    sensors.value,
                    sensors.class_id,
                    sensors.last_error,
                    sensors.sensor_id, 
                    devices.device_id,
                    TIME_TO_SEC(TIMEDIFF(NOW(),sensors.state_changed))  state_duration
                  FROM sensors
                    LEFT JOIN devices USING (device_id)     
                    ) AS tbl1 LEFT JOIN sensors_classes USING (class_id)) AS tbl2 LEFT JOIN 
                (SELECT sensor_id, old_state, new_state, old_value, new_value, error  FROM (SELECT * FROM state_log ORDER BY `changed` DESC) AS tbl GROUP BY sensor_id
                  ) AS tbl3 USING (sensor_id) ORDER BY organization, location, display_name, tbl2.sensor_class LIMIT 0,55');

if($st->execute()){
// Построение таблици
    $table = array();
    $cols = array('sensor_class', 'value', 'last_error', 'state_duration');
    while($row = $st->fetch(PDO::FETCH_ASSOC)){
        $table[$row['organization']][$row['location']][$row['display_name']][] = $row; 
    }
    echo '<table class="table table-striped table-hover" >';
    foreach ($table as $organization => $locations) {
        echo '<tr><td colspan="'. (count($cols) + 2) .'">'.($organization==''?'&nbsp':$organization).'</td></tr>';
        foreach ($locations as $location => $display_names) {
            $echolocation = true;
            foreach ($display_names as $display_name => $rows) {
                $echodisplay_name = true;
                foreach ($rows as $row) {
                   switch ($row['stable_state']){
			case 1:
                            echo '<tr class="danger">';
                        break;

         		case 0:
                            echo '<tr>';
                        break;
			
			case -1:
                            echo '<tr class="info">';
                        break;

			case -2:
                            echo '<tr class="warning">';
                        break;

                        default:
                               echo '<tr>';
                        break;
                    } 
                    if($echolocation) {
                        $i = 0;
                        foreach ($display_names as $tmp) {
                            $i += count($tmp);
                        }
                        echo '<td rowspan="'.$i.'">'.$location.'</td>';
                        $echolocation = false;
                    }
                    if($echodisplay_name) {
                        echo '<td rowspan="'.count($rows).'">'.$display_name.'</td>';
                        $echodisplay_name = false;
                    }
                    foreach ($cols as $key) {
                        switch ($key) {
                            case 'state_duration':
                                    echo '<td>'.SecondToStr($row[$key]).'</td>';
                                break;

                            default:
                                    echo '<td>' . ($row[$key]==""?'&nbsp':$row[$key]) . '</td>';
                                break;
                        }
                    }
                    echo '</tr>';
                }
            }
        }
    } 
    echo '</table>';
}
