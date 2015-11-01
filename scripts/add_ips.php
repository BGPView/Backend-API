<?php

for($i=0;$i<501;$i++){
    $cmd = "ip -6 addr add 2a06:1282:" . $i . ":f001:e141:f435:1010:deed/32 dev eth0";
    exec($cmd);
}
