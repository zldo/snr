<?php
include_once './snrapp.php';
global $snrapp;
global $rout;
global $pagetitle;
global $slidebar;
global $content;

if ( isset($_GET['logout']) and ($_GET['logout'] == 1) ) {
    $snrapp->Logout();
    header('Location: http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']);
}
if ( !$snrapp->auth_ok )
{
	if ( isset($_POST['uname']) && isset($_POST['pwd'])){
          $remember = isset($_POST['remember'])?$_POST['remember']:null;
	  if ( !$snrapp->Login(array('login' => $_POST['uname'], 'pass' => $_POST['pwd'], 'remember' => $remember))){
              
	    $autherror = 'Неверные имя пользователя или пароль.';
            
	  }else{
	    header('Location: http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']);
	  }
	}
	
        // Форма авторизации
        ?>
        <!DOCTYPE XHTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//RU">
        <html>
          <head>
          <link href="auth-style.css" rel="stylesheet" type="text/css">
          <meta http-equiv="content-type" content="text/html; charset=utf-8">
          <title>Авторизация</title>
          <body>
          <div id="content"> 
              <h1><img src="images/key.png" alt="" title="" align="absmiddle" hspace="5" vspace="5">&nbsp;Авторизация</h1>
          
          <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" />
          <table class="authtable">
            <?php
              if(isset($autherror)){
                 ?>
                 <tr class="authttr">
                    <td colspan="2" class="authtd-error"><?php echo $autherror;?></td>
                 </tr>
                 <?php
              }
            ?>  
            <tr class="authttr">
                <td class="authtd">Имя&nbsp;пользователя:</td><td class="authtd"><input type="text" name="uname" /></td>
            </tr>
            <tr class="authttr">
              <td class="authtd">Пароль:</td><td class="authtd"><input type="password" name="pwd" /></td>
            </tr>
            <tr class="authttr">
                <td colspan="2" class="authtd-"><input type="checkbox" name="remember" value="1" />Запомнить меня.</td>
            </tr>
            <tr class="authttr">
              <td colspan="2" class="authtd-submit"><input type="submit" value="Вход" /></td>
            </tr>
          </table>
          </form>
          </div>
          </body>
        </html>
        <?php
        exit();
}else {
    
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="content-type" content="application/xhtml+xml; charset=utf-8" />
<title><?php echo $pagetitle;  ?></title>
<link rel="stylesheet" type="text/css" href="style.css" />
<link rel="stylesheet" type="text/css" href="datepicker.css" />
<link rel="shortcut icon" href="favicon.ico">
<script src="datepicker.js" type="text/javascript" charset="UTF-8" language="javascript"></script>
</head>
<body>

<div id="container">
	
	<!-- ### Header ### -->
	
	<div id="header">
            <div style="height: 90px;overflow: hidden;">
                <h1 style="color: #484833; text-shadow: white 0em 0em 0.4em"><a href="index.php"><img src="images/logo-big.png" alt="" title="" align="absmiddle"></a>Мониторинг</h1>
		<p><font color="white"><?php echo $snrapp->user->dname . ', ' . $snrapp->user->title;?></font><br>
                    
                </p>		
            </div>    
            <div id="topmenu" style="height: 30px;overflow: hidden;">
                    <ul>
                        <li><a href="monitor.php">Состояние устройств</a></li>
                        <li><a href="action_log.php">Журнал событий</a></li>
                        <?php
                        if($snrapp->CheckRole('admin')){
                        ?>
                        <li><a href="devices.php">Параметры устройств</a></li>        
                        <li><a href="alerts_list.php">Настройка событий</a></li>
                        <li><a href="classes_list.php">Классы сенсоров</a></li>                        
                        <li><a href="users_editor.php">Пользователи</a></li>
                        <?php } ?>
			<li><a href="<?php echo $_SERVER['PHP_SELF'] . "?logout=1"?>">Выход</a></li>	
		</ul>	
		</div>

	</div>
		
        <div id="contentcontainer" >
              <table class="blanktable" width="100%">
                  <tr class="blanktable">
                <?php if(isset($slidebar) and file_exists($slidebar)) { echo '<td class="blanktable" valign="top" width="240px">'; include $slidebar; echo '</td>';}?>
		<td class="blanktable">
		<div id="content" <?php if(!file_exists($slidebar)){ echo 'style="margin-left: 0px; width: 100%;"'; }?>>
                
                   <?php include $content; 
                      $rout = false;
                   ?>
                    
		
		</div>
                </td>
                </tr>
                </table>  
	</div>
</div>
    
<?php   
if(!$rout) {
    include_once "footer.php";
    exit();
}
       
}

