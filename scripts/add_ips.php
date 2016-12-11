<?php

for($i=0;$i<10000;$i++){
    $cmd = "ip -6 addr add 2a06:9f82:" . $i . ":f001:e141:f435:1010:deed/32 dev vmbr0";
    exec($cmd);
}
