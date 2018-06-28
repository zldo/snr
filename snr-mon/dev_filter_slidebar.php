<?php 
include_once './snrapp.php';
global $snrapp;
global $gfilters; // Глобальные фильтры
global $filter_fields_order; // Список полей доступных для глобальной фильтрации в порядке иерархии
global $filter_fields_order;
$filter_fields_order = array('organization' => 'Организация', 'location' => 'Размещение', 'display_name' => 'Устройство');

function FilterBar($field){
  global $snrapp;
  global $filter_fields_order;
  global $gfilters; // Глобальные фильтры
  $filter_fields_order_keys = array_keys($filter_fields_order);
  $result = array();
  $topfilter_sql = '(true';
  $topfilter = array();
  $i = array_search($field, $filter_fields_order_keys) - 1;
  for ($index = $i; $index >= 0; $index--) {
      if(isset($gfilters[$filter_fields_order_keys[$index]])){
        $topfilter_sql .= ' AND ('. $filter_fields_order_keys[$index] . ' = :' . $filter_fields_order_keys[$index] . ')';
        $topfilter[$filter_fields_order_keys[$index]] = $gfilters[$filter_fields_order_keys[$index]]; 
      }
  }
  $topfilter_sql .= ')';
  $st = $snrapp->db->prepare('SELECT DISTINCT
              IF(ISNULL('.$field.'), "", '.$field.') AS '.$field.'
            FROM
              devices WHERE '.$topfilter_sql.' ORDER BY '.$field);

  if($st->execute(array_merge($topfilter))){
        while($row = $st->fetch(PDO::FETCH_ASSOC)){
            if($row[$field] === "") $disp = '"___?___"';
            else $disp = trim($row[$field]);
            if(IsSet($gfilters[$field]) and ($row[$field] == $gfilters[$field]) or ($st->rowCount() == 1)) $result[] = "<a class='menu'><strong>".$disp."</a></strong>" ;
            else $result[] = "<a class='menu' href='".$_SERVER['PHP_SELF']."?filter_".$field."=".$row[$field]."'>".$disp."</a>";
        }
  } else {

  }
  return $result;
}


function EchoFilterBar(){
    global $snrapp;
    global $filter_fields_order;
    global $gfilters; // Глобальные фильтры
    foreach ($filter_fields_order as $key => $value) {
        $lines = FilterBar($key);
        if(count($lines) > 0) { // Более одного элемента
            //print_r($lines);
            echo '<br><br><strong>' . $value . '</strong><br>';
            if(isset($gfilters[$key])){
                echo "<a class='menu' href='" . $_SERVER['PHP_SELF']. "?null_filter_organization=1'>Все</a><br />";
                echo implode('<br>', $lines);
            } else {
                if(count($lines) > 1) {
                    echo "<strong><a class='menu' href='" . $_SERVER['PHP_SELF']. "?null_filter_organization=1'>Все</a><br /></strong>";
                    echo implode('<br>', $lines);
                    break;
                } else {
                    echo implode('<br>', $lines);
                }                                
            }            
        }
        if($key == 'display_name') { // Вывод выбора датчика
            $filter = array('sql' => array('true'), 'params' => array());
            foreach ($filter_fields_order as $field => $display) {
                if(isset($gfilters[$field])){
                    $filter['sql'][] = $field . ' = :' . $field;
                    $filter['params'][$field] =  $gfilters[$field];
                }
            }
            $st = $snrapp->db->prepare('SELECT sensors.sensor_id, 
                                               if(ISNULL(sensors.display_name) OR TRIM(sensors.display_name) = "", sensors_classes.sensor_class, sensors.display_name) AS display_name
                                        FROM sensors LEFT JOIN sensors_classes USING (class_id) 
                                        WHERE device_id = (SELECT device_id FROM devices WHERE '.implode(' AND ', $filter['sql']).' LIMIT 1)');
            
            if($st->execute($filter['params']) and ($st->rowCount() > 0)){
                echo '<br><br><strong>Датчики</strong><br>';
                $r = array();
                while($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $checked = (!isset($gfilters['sensors_ids']) or !(array_search($row['sensor_id'], $gfilters['sensors_ids']) === false) )?'checked':'';                    
                    $r[] = '<input type="checkbox" name="sensors_ids[]" value="'.$row['sensor_id'].'" '.$checked.'>' . $row['display_name'];                    
                }
                echo implode('<br>', $r);
            }
            break;
        }
    }
    
}


    if(isset($_SESSION['gfilters'])) {
        $gfilters = $_SESSION['gfilters'];
    } else {
        $gfilters = array();
    }

    foreach ($filter_fields_order as $field => $display) {         
        if(isset($_GET['filter_'.$field])) { // Фильтр изменен            
            $f = false;
            foreach ($filter_fields_order as $key => $value) { // Сброс нижележащих фильтров
                if($f or ($key == $field)){
                    unset($gfilters[$key]);
                    $f = true;
                }
            }
            $gfilters[$field] = $_GET['filter_'.$field];
            unset($gfilters['sensors_ids']);
        }
        if(isset($_GET['null_filter_'.$field])){ // Сброс фильтра с текущего уровня 
            $f = false;
            foreach ($filter_fields_order as $key => $value) { // Сброс нижележащих фильтров
                if($f or ($key == $field)){
                    unset($gfilters[$key]);
                    $f = true;
                }
            }  
            unset($gfilters['sensors_ids']);
        }               
    }
    
    // Предварительная выборка идентификаторов устройств из фильтра

    $r = array('true');
    $params = array();
    foreach ($filter_fields_order as $key => $value) {
        if(isset($gfilters[$key])){
            $r[] = $key . ' = :' . $key;
            $params[$key] = $gfilters[$key];
        }
    }
    
    $st = $snrapp->db->prepare('SELECT GROUP_CONCAT(device_id SEPARATOR ",") FROM devices WHERE '.implode(' AND ', $r));

    if($st->execute($params)){
        $gfilters['device_ids'] = $st->fetchColumn();
    } else {
        unset($gfilters['device_ids']);
    }  
      
function DDate($s){
    $d = explode('.', $s);
    if(count($d) == 3) {
        return $d[2] . "-" . $d[1] . "-" . $d[0];
    } else {
        return date('Y-m-d');
    }
}

if(isset($_POST['data-start'])){ // Обновление значений
    $gfilters['data-start'] = $_POST['data-start'];
    $gfilters['data-end'] = $_POST['data-end'];        
    $gfilters['SQLWhere'] = '(changed BETWEEN "' . DDate($gfilters['data-start']) . '" AND "' . DDate($gfilters['data-end']) . ' 23:59:59")';
    $_SESSION['gfilters'] = $gfilters;
} elseif(!isset($_SESSION['gfilters'])){ // Возвращение значений из сессии    
    $gfilters['data-start'] = date('d.m.Y', time() - (3600*24*30));
    $gfilters['data-end'] = date('d.m.Y');        
    $gfilters['SQLWhere'] = '(date BETWEEN "' . DDate($gfilters['data-start']) . '" AND "' . DDate($gfilters['data-end']) . ' 23:59:59")';
}

if(isset($_POST['sensors_ids'])){ // Фильтр датчиков
    $gfilters['sensors_ids'] = $_POST['sensors_ids']; 
} elseif(isset($_POST['alert_type'])) {
    $gfilters['sensors_ids'] = array();
}

if(isset($_POST['alert_type'])){ // Фильтр датчиков
    $gfilters['alert_type'] = $_POST['alert_type']; 
} else {
    if(!isset($gfilters['alert_type'])) $gfilters['alert_type'] = '-1'; 
}


$alert_type_sel = 
                '<option value="-1" '. ($gfilters['alert_type'] ==-1 ? 'selected' : '') . '>Все события</option>' .
                '<option value="0" ' . ($gfilters['alert_type'] == 0  ? 'selected' : '') . '>Оповещение</option>' .
                '<option value="1" ' . ($gfilters['alert_type'] == 1  ? 'selected' : '') . '>Предупреждение</option>' .
                '<option value="2" ' . ($gfilters['alert_type'] == 2  ? 'selected' : '') . '>Тревога</option>';
   
$_SESSION['gfilters'] = $gfilters;

?>

<!-- ### Sidebar Begin ###-->	
    <div id="sidebar">
    <ul>

    <li><h2>Фильтры</h2>
        <form id="searchform" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
             <input type="hidden" value="1" name="auto_stats_filters_update">
             Начальная дата<br>             
             <input style="width: 170px;" name="data-start" value="<?php echo $gfilters['data-start']; ?>"> 
             <input type="button" style="background: url('images/calendar.png') no-repeat; width: 16px; border: 0px;" onclick="displayDatePicker('data-start', false, 'dmy', '.');"><br>
             <br>Конечная дата<br> 
             
             <input style="width: 170px;" name="data-end" value="<?php echo $gfilters['data-end']; ?>"> 
             <input type="button" style="background: url('images/calendar.png') no-repeat; width: 16px; border: 0px;" onclick="displayDatePicker('data-end', false, 'dmy', '.');">
             <br><br>Тип события:<br><select style ="width: 186px" name="alert_type"><?php echo $alert_type_sel;?>"></select>             
             <?php EchoFilterBar(); ?>
             <br><br><input id='save_btn' type="submit" value="Применить фильтр">
        </form>
    </li>

    </ul>	

    </div>

    <!-- ### Sidebar End ### -->
