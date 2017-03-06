<?php

    require_once 'jinxup.php';

    #
    # show errors
    //$jinxup->error->show();
    #
    # hide errors
    # $jinxup->error->hide();

    #
    # In case you choose to have the DOCUMENT ROOT at the root of the installation
    # you can specify the the name of the application you want to load by default
    # or entirely have the logic for the app you always want loaded instead of having
    # them in the app specific index file
    #
    # $jinxup->app('back');

    $jinxup->run();