#!/bin/bash

# Needs AXEL and unix system

rm -f ./storage/bgp_lines.txt;
touch ./storage/bgp_lines.txt;
function process_rib {
    response=$(curl --write-out %{http_code} --silent --output /dev/null -I $1)
    echo "Curl Response: $response"
    if [ "200" == $response ]; then
        rm -f ./storage/temp_rib.*;
        filename=$1
        extension="${filename##*.}"
        axel -o ./storage/temp_rib.$extension $1;
        ./scripts/bgpdump -m ./storage/temp_rib.$extension >> ./storage/bgp_lines.txt;
        rm -f ./storage/temp_rib.*;
    fi
}

###############################################################################################

# IPv4 RouteViews
BASE_URL="archive.routeviews.org/bgpdata";
RIB_FOLDER=`curl ftp://$BASE_URL/ | tail -1 | awk '{print $(NF)}'`
RIB_FILE=`curl ftp://$BASE_URL/$RIB_FOLDER/RIBS/ | tail -1 | awk '{print $(NF)}'`
process_rib $BASE_URL/$RIB_FOLDER/RIBS/$RIB_FILE

# IPv6 RouteViews
BASE_URL="archive.routeviews.org/route-views6/bgpdata";
RIB_FOLDER=`curl ftp://$BASE_URL/ | tail -1 | awk '{print $(NF)}'`
RIB_FILE=`curl ftp://$BASE_URL/$RIB_FOLDER/RIBS/ | tail -1 | awk '{print $(NF)}'`
process_rib $BASE_URL/$RIB_FOLDER/RIBS/$RIB_FILE

###############################################################################################

process_rib http://data.ris.ripe.net/rrc00/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc01/latest-bview.gz
# process_rib http://data.ris.ripe.net/rrc02/latest-bview.gz #Outdated and not used anymore
process_rib http://data.ris.ripe.net/rrc03/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc04/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc05/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc06/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc07/latest-bview.gz
# process_rib http://data.ris.ripe.net/rrc08/latest-bview.gz #Outdated and not used anymore
# process_rib http://data.ris.ripe.net/rrc09/latest-bview.gz #Outdated and not used anymore
process_rib http://data.ris.ripe.net/rrc10/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc11/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc12/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc13/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc14/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc15/latest-bview.gz
process_rib http://data.ris.ripe.net/rrc16/latest-bview.gz

###############################################################################################
