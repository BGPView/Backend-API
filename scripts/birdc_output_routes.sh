 show route all | awk -vts=$(date +%s) '
    BEGIN {
        OFS = "|";
        ORS = "\n";
        route = "";
        peer = "";
        path = "";
        split(path, path_hops, " ");
        origin = "";
        next_hop = "";
        community = "";
    }
    function printroute() {
        if (route != "" && peer != "" && path != "" && origin != "" && next_hop != "") {
            print "BGP4MP", ts, "A", peer, path_hops[1], route, path, origin, next_hop, "0", "0", community, "NAG", "", "";
            peer = "";
            path = "";
            split(path, path_hops, " ");
            origin = "";
            next_hop = "";
            community = "";
        }
    }
    {
        if ($2 == "unreachable") {
            printroute();
            route = $1;
            peer = $(NF-3);
            gsub("\\]", "", peer);
        } else if ($2 == "via") {
            printroute();
            route = $1;
            peer = $3;
        } else if ($1 == "unreachable") {
            printroute();
            peer = $(NF-2);
            gsub("\\]", "", peer);
        } else if ($1 == "via") {
            printroute();
            peer = $2;
        } else if ($1 == "BGP.as_path:") {
            path = $0;
            gsub("^.*BGP\\.as_path: ", "", path);
            split(path, path_hops, " ");
        } else if ($1 == "BGP.origin:") {
            origin = $2;
        } else if ($1 == "BGP.next_hop:") {
            next_hop = $2;
        } else if ($1 == "BGP.community:") {
            community = $0;
            gsub("^.*BGP\\.community: ", "", community);
            gsub("\\(", "", community);
            gsub("\\)", "", community);
            gsub(",", ":", community);
        }
    }
    END {
        printroute();
    }
'

