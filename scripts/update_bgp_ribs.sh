#!/bin/bash

# RIS + RouteViews + OpenBMP
bgpreader -m -w $(expr $(date +%s) - 28800),$(date +%s) -t ribs > ./storage/bgp_lines.txt

# PCH - AS42
php parse_as42.php >> ./storage/bgp_lines.txt

exit 0;
