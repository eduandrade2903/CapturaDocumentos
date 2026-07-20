<?php
return [
    'host'  =>  $_ENV['CONTAINER_MYSQL_HOST'],
    'port'  =>  $_ENV['CONTAINER_MYSQL_PORT'],
    'name'  =>  $_ENV['CONTAINER_MYSQL_DATABASE_PERMISSION'],
    'user'  =>  $_ENV['CONTAINER_MYSQL_USER'],
    'pass'  =>  $_ENV['CONTAINER_MYSQL_PASSWORD'],
    'type'  =>  "mysql",
    'prep'  =>  "1"
];
