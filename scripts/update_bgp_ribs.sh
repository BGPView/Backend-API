#!/bin/bash

# Need to have axel and bgpdump2 installed on machine to parse

BASE_URL="archive.routeviews.org/bgpdata";
RIB_FOLDER=`curl ftp://$BASE_URL/ | tail -1 | awk '{print $(NF)}'`
RIB_FILE=`curl ftp://$BASE_URL/$RIB_FOLDER/RIBS/ | tail -1 | awk '{print $(NF)}'`


#IPv4 RIBs
echo "Getting IPv4 RIB files"
IPV4_RIB_URL="http://$BASE_URL/$RIB_FOLDER/RIBS/$RIB_FILE"
echo "IPv4 RIB URL: $IPV4_RIB_URL"
response=$(curl --write-out %{http_code} --silent --output /dev/null -I $IPV4_RIB_URL)
echo "Curl Response: $response"
if [ "200" == $response ]; then
	echo "Downloading RIB file"
	rm -f /tmp/temp_rib_ipv4.bz2;
	axel -o /tmp/temp_rib_ipv4.bz2 $IPV4_RIB_URL;
	echo "Doing BGPDump on file: /var/www/html/rib_ipv4.txt"
	bgpdump2 /tmp/temp_rib_ipv4.bz2 > /var/www/html/rib_ipv4.txt;
	echo "Clean up"
	rm -f /tmp/temp_rib_ipv4.bz2;
fi

BASE_URL="archive.routeviews.org/route-views6/bgpdata";
RIB_FOLDER=`curl ftp://$BASE_URL/ | tail -1 | awk '{print $(NF)}'`
RIB_FILE=`curl ftp://$BASE_URL/$RIB_FOLDER/RIBS/ | tail -1 | awk '{print $(NF)}'`


#IPv6 RIBs
echo "Getting IPv6 RIB files"
IPV6_RIB_URL="http://$BASE_URL/$RIB_FOLDER/RIBS/$RIB_FILE"
echo "IPv6 RIB URL: $IPV6_RIB_URL"
response=$(curl --write-out %{http_code} --silent --output /dev/null -I $IPV6_RIB_URL)
echo "Curl Response: $response"
if [ "200" == $response ]; then
        echo "Downloading RIB file"
        rm -f /tmp/temp_rib_ipv6.bz2;
        axel -o /tmp/temp_rib_ipv6.bz2 $IPV6_RIB_URL;
        echo "Doing BGPDump on file: /var/www/html/rib_ipv6.txt"
        bgpdump2 /tmp/temp_rib_ipv6.bz2 -6 > /var/www/html/rib_ipv6.txt;
        echo "Clean up"
        rm -f /tmp/temp_rib_ipv6.bz2;
fi
