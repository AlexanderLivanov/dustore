<?php

function use_pack($server_type){
    if($server_type == "PRODUCTION"){
        return ['localhost', 'dustore', 'Здесь было изменение))', 'И ещё этот пароль тоже был изменён :)'];
    } else if ($server_type == "LOCAL") {
        return ['localhost', 'dustore', 'root', ''];
    }
}