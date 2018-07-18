<?php

for($i=0;$i<10000;$i++){
    $cmd = "ip -6 addr add 2a06:1280:ae01:" . $i . ":e141:f435:1010:deed/48 dev eth0";
    exec($cmd);
}
