<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of snrapp
 *
 * @author zldo
 */

global $snrapp;

function SNRStateToStr($state){
        switch ($state) {
            case -4:
                return "Ошибка";
                break;
            
            case -2:
                return "Не в сети";
                break;
            
            case -1:
                return "Неизвестно";
                break;
            
            case 0:
                return "Ok";
                break;
            
            case 1:
                return "Тревога";
                break;

            default:
                break;
        }
    }

class snrapp {
    private $db_user = 'snr';
    private $db_pass = 'snrpwd';
    public $user = null;
    public $db = null;
    public $auth_ok = false; 
    
    public function CheckRole($role){
        $roles = explode(',', $this->user->roles);
        return !(array_search($role, $roles) === false);
    }
    
    public function __construct($params = array()) {
        try {
            $this->db = new PDO('mysql:host=localhost;dbname=snr-mondb', $this->db_user, 
                $this->db_pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8", 
                                      PDO::ATTR_PERSISTENT => true));
        }
        catch( PDOException $Exception ) {
            echo $Exception->getMessage();
        }
        session_start();
        if (isset($_SESSION['curuser_session'])){
            $this->user = unserialize($_SESSION['curuser_session']); // Сессия открыта
            $this->auth_ok = true;
        } else {
            $this->auth_ok = false;
        }
    }
    
    public function Login($params) {
        if(!isset($params['login'])){
            $params = $_COOKIE['auth'];
        }
        if(isset($params['login']) and isset($params['pass'])) {
            unset($_SESSION['curuser_session']);
            $st = $this->db->prepare('SELECT *, GROUP_CONCAT(user_roles.role) AS roles  
                                      FROM users LEFT JOIN user_roles USING (user_id) 
                                      WHERE login=:login AND ((pass = :pass) or (false))');
            if($st->execute(array('login' => $params['login'], 'pass' => $params['pass']))){
                $this->user = $st->fetchObject();
                $this->auth_ok = true;
                if($this->auth_ok) { 
                    $_SESSION['curuser_session'] = serialize($this->user);
                }
            } else {
                $this->auth_ok = false; 
            }
        } else {
            $this->auth_ok = false; 
        }
        if($this->auth_ok and isset($params['remember'])){
            $s = serialize($params);
            setcookie('auth', $s);
        }
        return $this->auth_ok;  
    }
    
    public function Logout(){
        foreach ($_SESSION as $key => $value) {
            unset($_SESSION[$key]);
        }
        session_commit();
        session_write_close();
    }
    
    
}

$snrapp = new snrapp();  
