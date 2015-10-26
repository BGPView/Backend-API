<?php

for($i=0;$i<501;$i++){
    $cmd = "ip -6 addr add 2a06:1282:".$i."::deed/32 dev eth0";
    exec($cmd);
}
