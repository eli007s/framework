<?php

    require_once 'jinxup.php';
    $RootName = $_SERVER['REQUEST_URI'];
    $jinxup->app('sample')
           
        ->root($RootName)
        //->route('/')->to('new_index', 'test')
        ->init();