<?php
include_once './snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;

$db = $snrapp->db;

if($snrapp->CheckRole('admin')) {

    if(isset($_GET['deleted_id']) and ($_GET['deleted_id'] != '')) {
        $st = $db->prepare('DELETE FROM alerts WHERE alert_id=:alert_id');
        $st->bindValue(':alert_id', $_GET['deleted_id'], PDO::PARAM_INT);
        if(!$st->execute()) {
            print_r($st->errorInfo());
        } else {
            header('Location: alerts_list.php');
            exit;
        }
    }

    if(isset($_GET['alert_id'])) { // Идентификатор редактируемого устройства
        $alert_id = $_GET['alert_id'];
        $pagetitle = 'Редактирование параметров класса сенсоров';
        $st = $db->prepare('SELECT * FROM alerts WHERE alert_id=:alert_id');
        $st->bindValue(':alert_id', $alert_id, PDO::PARAM_INT);
        if($st->execute()) {
                $alert = $st->fetch(PDO::FETCH_ASSOC); 
        } else {
            $alert = null;
            $page_error = "Событие не найдено.";
        }
    } else {
        $alert_id = null;
        $pagetitle = 'Добавление события';
        $alert = null;
    }

    if(!isset($alert)) {
        $alert['display_name'] = 'Новое событие';
        $alert['wanted_state'] = 0;
        $alert['description'] = 'Описание';
        $alert['wanted_state_delay'] = 1;
        $alert['wanted_state_change_count'] = 5;
        $alert['wanted_state_change_perion'] = 5;
        $alert['action'] = 'mail';
        $alert['action_params'] = '';
        $alert['alert_title'] = '';
        $alert['alert_body'] = '';
        $alert['repeat_interval'] = 0;
        $alert['alert_type'] = 0;
    }

    if(isset($_POST['wanted_state_change_count'])) { // Обработка сохранения
        $alert_id = isset($_POST['alert_id']) ? $_POST['alert_id'] : null;
        if(isset($alert_id)) { // Обновление существующего класса
            $st = $db->prepare('UPDATE alerts SET 
                                display_name               = :display_name,
                                description                = :description,
                                wanted_state               = :wanted_state,
                                wanted_state_delay         = :wanted_state_delay,
                                wanted_state_change_count  = :wanted_state_change_count,
                                wanted_state_change_perion = :wanted_state_change_perion,
                                action                     = :action,
                                action_params              = :action_params,
                                alert_title                = :alert_title,
                                alert_body                 = :alert_body,
                                alert_type                 = :alert_type,
                                repeat_interval            = :repeat_interval                            
                        WHERE alert_id = :alert_id');
            $st->bindValue(':alert_id', $alert_id, PDO::PARAM_INT);
        } else { // Добавление нового устройства
            $st = $db->prepare('INSERT INTO alerts (display_name, description, wanted_state, wanted_state_delay, wanted_state_change_count, wanted_state_change_perion, action, action_params, alert_title, alert_body, repeat_interval, alert_type)
                                             VALUES (:display_name, :description, :wanted_state, :wanted_state_delay, :wanted_state_change_count, :wanted_state_change_perion, :action, :action_params, :alert_title, :alert_body, :repeat_interval,alert_type)');
        }
        $st->bindValue(':display_name', $_POST['display_name'], PDO::PARAM_STR);
        $st->bindValue(':description', $_POST['description'], PDO::PARAM_STR);
        $st->bindValue(':wanted_state', $_POST['wanted_state'], PDO::PARAM_INT);
        $st->bindValue(':wanted_state_delay', $_POST['wanted_state_delay'], PDO::PARAM_INT);
        $st->bindValue(':wanted_state_change_count', $_POST['wanted_state_change_count'], PDO::PARAM_INT);
        $st->bindValue(':wanted_state_change_perion', $_POST['wanted_state_change_perion'], PDO::PARAM_INT);
        $st->bindValue(':action', $_POST['action'], PDO::PARAM_STR);
        $st->bindValue(':action_params', $_POST['action_params'], PDO::PARAM_STR);
        $st->bindValue(':alert_title', $_POST['alert_title'], PDO::PARAM_STR);
        $st->bindValue(':alert_body', $_POST['alert_body'], PDO::PARAM_STR);
        $st->bindValue(':repeat_interval', $_POST['repeat_interval'], PDO::PARAM_INT);
        $st->bindValue(':alert_type', $_POST['alert_type'], PDO::PARAM_INT);
        if($st->execute()){
            if(!isset($alert_id)) {
                $alert_id = $db->lastInsertId();
            }
            header('Location: alert_editor.php?alert_id='.$alert_id);
            exit();
        } else {
            print_r($st->errorInfo());
            $st->debugDumpParams();
            exit();
        }
    }
}

$pagetitle = 'Редактор события';
//$slidebar = "admin_slidebar.php";
$content = basename($_SERVER['PHP_SELF']);
if($rout) exit; 
include_once "htmlstart.php";
if($snrapp->CheckRole('admin')) {    
        $wanted_state_sel = 
                '<option value="-6" ' . ($alert['wanted_state'] == -6 ? 'selected' : '') . '>Нестабильное подключение</option>' .
                '<option value="-5" ' . ($alert['wanted_state'] == -5 ? 'selected' : '') . '>Показание нестабильно</option>' .
                '<option value="-4" ' . ($alert['wanted_state'] == -4 ? 'selected' : '') . '>Ошибка</option>' .
                '<option value="-2" ' . ($alert['wanted_state'] == -2 ? 'selected' : '') . '>Не в сети</option>' .
                '<option value="-1" ' . ($alert['wanted_state'] == -1 ? 'selected' : '') . '>Неизвестно</option>' .
                '<option value="0"  ' . ($alert['wanted_state'] ==  0 ? 'selected' : '') . '>В сети</option>' .
                '<option value="1"  ' . ($alert['wanted_state'] ==  1 ? 'selected' : '') . '>Тревога</option>';

        $action_sel = 
                '<option value="INFO" ' . ($alert['action'] == "INFO" ? 'selected' : '') . '>Только добавить в журнал</option>' .
                '<option value="MAIL" ' . ($alert['action'] == "MAIL" ? 'selected' : '') . '>Отправить электронную почту</option>' .
                '<option value="EXEC" ' . ($alert['action'] == "EXEC" ? 'selected' : '') . '>Выполнить команду на сервере</option>';

        $alert_type_sel = 
                '<option value="0" ' . ($alert['alert_type'] == 0 ? 'selected' : '') . '>Оповещение</option>' .
                '<option value="1" ' . ($alert['alert_type'] == 1 ? 'selected' : '') . '>Предупреждение</option>' .
                '<option value="2" ' . ($alert['alert_type'] == 2 ? 'selected' : '') . '>Тревога</option>';

        $st = $db->prepare('SELECT 1 FROM sensors_alerts WHERE alert_id=:alert_id LIMIT 1');
        $st->bindValue(':alert_id', $alert_id, PDO::PARAM_INT);
        if($st->execute()) {
                $alert_used = $st->fetchColumn() == 1; 
        } else {
            $alert_used = false;
        }
    ?>

    <?php if(isset($alert_id)){ 
        echo '<h2><a>Редактирование события "'.$alert['display_name'].'".</a></h2>';
    } else {
        echo '<h2><a>Добавление нового типа событий.</a></h2>';
    }
    ?>    
    <a href="alerts_list.php"><img src="images/cog.png">Список типов событий</a>


    <div class="entry">

        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
            <?php if(isset($alert_id)){
               echo '<input type="hidden" name="alert_id" value="'.$alert_id.'">';
            } ?>
            <table class="blanktable">
            <caption>Параметры события:</caption>
            <tr><td class="blanktable">Имя события</td><td class="blanktable"><input style ="width: 350px" name="display_name" value="<?php echo htmlspecialchars($alert['display_name']);?>"></td></tr>          
            <tr><td class="blanktable">Описание</td><td class="blanktable"><textarea style ="width: 350px; height: 100px" name="description"><?php echo htmlspecialchars($alert['description']);?></textarea></td></tr>        
            <tr><td class="blanktable">Тип события</td><td class="blanktable"><select style ="width: 350px" name="alert_type"><?php echo $alert_type_sel;?>"></select></td></tr>
            <tr><td class="blanktable">Ожидаемое состояние</td><td class="blanktable"><select style ="width: 350px" name="wanted_state"><?php echo $wanted_state_sel;?>"></select></td></tr>
            <tr><td class="blanktable">Продолжительность нахождения в ожидаемом состоянии (мин)</td><td class="blanktable"><input style ="width: 350px" name="wanted_state_delay" value="<?php echo htmlspecialchars($alert['wanted_state_delay']);?>"></td></tr>
            <tr><td class="blanktable">Число изменений состояния для определения "нестабильности"</td><td class="blanktable"><input style ="width: 350px" name="wanted_state_change_count" value="<?php echo htmlspecialchars($alert['wanted_state_change_count']);?>"></td></tr>
            <tr><td class="blanktable">Интервал времени для определения "нестабильности" (мин)</td><td class="blanktable"><input style ="width: 350px" name="wanted_state_change_perion" value="<?php echo htmlspecialchars($alert['wanted_state_change_perion']);?>"></td></tr>
            <tr><td class="blanktable">Повтор не раньше чем через (мин) 0 - не повторять</td><td class="blanktable"><input style ="width: 350px" name="repeat_interval" value="<?php echo htmlspecialchars($alert['repeat_interval']);?>"></td></tr>
            <tr><td class="blanktable">Выполняемое действие</td><td class="blanktable"><select style ="width: 350px" name="action"><?php echo $action_sel;?>"></select></td></tr>                                  
            <tr><td class="blanktable">Заголовок информационного сообщения</td><td class="blanktable"><textarea style ="width: 350px; height: 100px" name="alert_title"><?php echo htmlspecialchars($alert['alert_title']);?></textarea></td></tr>
            <tr><td class="blanktable">Содержание информационного сообщения</td><td class="blanktable"><textarea style ="width: 350px; height: 100px" name="alert_body"><?php echo htmlspecialchars($alert['alert_body']);?></textarea></td></tr>
            <tr><td class="blanktable">Параметры выполняемого действия</td><td class="blanktable"><textarea style ="width: 350px; height: 100px" name="action_params"><?php echo htmlspecialchars($alert['action_params']);?></textarea></td></tr>       


            <?php if(isset($alert_id) and !$alert_used){ ?>
            <tr>
                <td class="blanktable" colspan="2">
                    <a href="alert_editor.php?deleted_id=<?php echo $alert_id; ?>" onclick="return confirm('Вы действительно хотите удалить это событие?') ? true : false;">
                        <img src="images/delete.png"> Удалить событие <b> <?php echo $alert['display_name']; ?> </b>
                    </a>
                </td>
            </tr>
            <?php } else { ?>
            <tr>
                <?php if(isset($alert_id)){ ?>
                <td class="blanktable" colspan="2">
                    Вы не можете удалить этот тип событий, так как он используется.
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