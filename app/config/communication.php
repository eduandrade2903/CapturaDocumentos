<?php
return [
    'host'  =>  $_ENV['DB_HOST_RDS'],
    'port'  =>  $_ENV['DB_PORT_RDS'],
    'name'  =>  $_ENV['DB_DATABASE_COMMUNICATION'],
    'user'  =>  $_ENV['DB_USER'],
    'pass'  =>  $_ENV['DB_PASS'],
    'type'  =>  "mysql",
    'prep'  =>  "1"
];
