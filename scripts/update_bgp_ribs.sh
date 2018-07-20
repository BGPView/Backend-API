#!/bin/bash

# Needs AXEL and unix system

rm -f ./storage/bgp_lines.txt;
touch ./storage/bgp_lines.txt;
rm -f ./storage/temp_rib_*;

function process_rib {
    response=$(curl --write-out %{http_code} --silent --output /dev/null -I $1)
    echo "Curl Response: $response"
    if [ "200" == $response ]; then
	random_name=$RANDOM
        filename=$1
        extension="${filename##*.}"
        $2 ./storage/temp_rib_$random_name.$extension $1;
        ./scripts/bgpdump -m ./storage/temp_rib_${random_name}.${extension} >> ./storage/${random_name}_bgp_out.txt
        rm -f ./storage/temp_rib_$random_name.$extension;
    fi
}

###############################################################################################

# IPv4 RouteViews
BASE_URL="archive.routeviews.org/bgpdata";
RIB_FOLDER=`curl ftp://$BASE_URL/ | tail -1 | awk '{print $(NF)}'`
RIB_FILE=`curl ftp://$BASE_URL/$RIB_FOLDER/RIBS/ | tail -1 | awk '{print $(NF)}'`
if [ -z "$RIB_FILE" ]
then
	RIB_FOLDER=`curl ftp://$BASE_URL/ | tail -2 | head -1 | awk '{print $(NF)}'`
	RIB_FILE=`curl ftp://$BASE_URL/$RIB_FOLDER/RIBS/ | tail -1 | awk '{print $(NF)}'`
fi
process_rib $BASE_URL/$RIB_FOLDER/RIBS/$RIB_FILE "wget -6 -O" &

# IPv6 RouteViews
BASE_URL="archive.routeviews.org/route-views6/bgpdata";
RIB_FOLDER=`curl ftp://$BASE_URL/ | tail -1 | awk '{print $(NF)}'`
RIB_FILE=`curl ftp://$BASE_URL/$RIB_FOLDER/RIBS/ | tail -1 | awk '{print $(NF)}'`
if [ -z "$RIB_FILE" ]
then
        RIB_FOLDER=`curl ftp://$BASE_URL/ | tail -2 | head -1 | awk '{print $(NF)}'`
        RIB_FILE=`curl ftp://$BASE_URL/$RIB_FOLDER/RIBS/ | tail -1 | awk '{print $(NF)}'`
fi
process_rib $BASE_URL/$RIB_FOLDER/RIBS/$RIB_FILE "wget -6 -O" &



###############################################################################################

process_rib http://data.ris.ripe.net/rrc00/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc01/latest-bview.gz "axel -o" &
# process_rib http://data.ris.ripe.net/rrc02/latest-bview.gz # Outdated and not used anymore
process_rib http://data.ris.ripe.net/rrc03/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc04/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc05/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc06/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc07/latest-bview.gz "axel -o" &
# process_rib http://data.ris.ripe.net/rrc08/latest-bview.gz # Outdated and not used anymore
# process_rib http://data.ris.ripe.net/rrc09/latest-bview.gz # Outdated and not used anymore
process_rib http://data.ris.ripe.net/rrc10/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc11/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc12/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc13/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc14/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc15/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc16/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc18/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc19/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc20/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc21/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc22/latest-bview.gz "axel -o" &
process_rib http://data.ris.ripe.net/rrc23/latest-bview.gz "axel -o" &
###############################################################################################

sleep 5

while pgrep -x "wget|axel|bgpdump" > /dev/null;
do
    sleep 3
done

# Combine all output RIB files into the single file
echo "Combining all the output BGP files into one..."
cat ./storage/*_bgp_out.txt > ./storage/bgp_lines.txt
rm -f ./storage/*_bgp_out.txt

exit 0
