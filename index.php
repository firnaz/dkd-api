<?php
include_once 'class/Epiphany/Epi.php';
include_once 'controller/App.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

Epi::setSetting('exceptions', true);
Epi::init('api','database');
EpiDatabase::employ('mysql', 'dkd', '127.0.0.1', '', '');

getRoute()->get('/', array('AppController', 'index'));
getRoute()->get('/tahun', array('AppController', 'tahun'));
getRoute()->get('/indikator', array('AppController', 'indikator'));
getRoute()->get('/region/(\S+)', array('AppController', 'region'));
getRoute()->get('/grafik/(\S+)', array('AppController','grafik'));
getRoute()->get('/displaychart', array('AppController','displaychart'));
getRoute()->get('/displaytable', array('AppController','displaytable'));
getRoute()->get('/getchartimage', array('AppController','getchartimage'));
getRoute()->get('/gettableimage', array('AppController','gettableimage'));
getRoute()->run();
?>
