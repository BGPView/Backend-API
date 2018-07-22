package main

import (
	"bufio"
	"context"
	"database/sql"
	"flag"
	"log"
	"math/big"
	"net"
	"os"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"time"

	_ "github.com/Go-SQL-Driver/MySQL"
	"github.com/olivere/elastic"
)

var tierOneASNs map[string]bool
var wg sync.WaitGroup
var bgpDataWg sync.WaitGroup

var seenv4Peers map[string]bool
var seenv6Peers map[string]bool
var v4PeersToEnter map[string]bool
var v6PeersToEnter map[string]bool

var seenv4Prefixes map[string]bool
var seenv6Prefixes map[string]bool
var v4PrefixesToEnter map[string]bool
var v6PrefixesToEnter map[string]bool

var seenBgpEntry map[string]bool
var es_index_name string

var db_name = flag.String("db_name", "api", "Database Name")
var db_user = flag.String("db_user", "root", "Database User")
var db_pass = flag.String("db_pass", "bananas", "Database Password")
var bgp_file = flag.String("bgp_file", "/Users/yswery/code/Backend-API/storage/bgp_lines.txt", "The BGP dump text file")
var bulkInsertAmount = flag.Int("bulk_insert", 10000, "Number of entries to insert in one query")
var bulkEsInsertAmount = flag.Int("bulk_insert_es", 100000, "Number of entries to insert in one query to ES")

type bgpData struct {
	prefix        string
	ip            string
	cidr          string
	source        string
	asn           string
	proto         string
	upstream_asn  string
	path_string   string
	path_array    []string
	peer_sets     [][]string
	original_line string
}

var ipv4cidrCount = []string{
	0:  "4294967296",
	1:  "2147483648",
	2:  "1073741824",
	3:  "536870912",
	4:  "268435456",
	5:  "134217728",
	6:  "67108864",
	7:  "33554432",
	8:  "16777216",
	9:  "8388608",
	10: "4194304",
	11: "2097152",
	12: "1048576",
	13: "524288",
	14: "262144",
	15: "131072",
	16: "65536",
	17: "32768",
	18: "16384",
	19: "8192",
	20: "4096",
	21: "2048",
	22: "1024",
	23: "512",
	24: "256",
	25: "128",
	26: "64",
	27: "32",
	28: "16",
	29: "8",
	30: "4",
	31: "2",
	32: "1",
}

var ipv6cidrCount = []string{
	128: "1",
	127: "2",
	126: "4",
	125: "8",
	124: "16",
	123: "32",
	122: "64",
	121: "128",
	120: "256",
	119: "512",
	118: "1024",
	117: "2048",
	116: "4096",
	115: "8192",
	114: "16384",
	113: "32768",
	112: "65536",
	111: "131072",
	110: "262144",
	109: "524288",
	108: "1048576",
	107: "2097152",
	106: "4194304",
	105: "8388608",
	104: "16777216",
	103: "33554432",
	102: "67108864",
	101: "134217728",
	100: "268435456",
	99:  "536870912",
	98:  "1073741824",
	97:  "2147483648",
	96:  "4294967296",
	95:  "8589934592",
	94:  "17179869184",
	93:  "34359738368",
	92:  "68719476736",
	91:  "137438953472",
	90:  "274877906944",
	89:  "549755813888",
	88:  "1099511627776",
	87:  "2199023255552",
	86:  "4398046511104",
	85:  "8796093022208",
	84:  "17592186044416",
	83:  "35184372088832",
	82:  "70368744177664",
	81:  "140737488355328",
	80:  "281474976710656",
	79:  "562949953421312",
	78:  "1125899906842624",
	77:  "2251799813685248",
	76:  "4503599627370496",
	75:  "9007199254740992",
	74:  "18014398509481985",
	73:  "36028797018963968",
	72:  "72057594037927936",
	71:  "144115188075855872",
	70:  "288230376151711744",
	69:  "576460752303423488",
	68:  "1152921504606846976",
	67:  "2305843009213693952",
	66:  "4611686018427387904",
	65:  "9223372036854775808",
	64:  "18446744073709551616",
	63:  "36893488147419103232",
	62:  "73786976294838206464",
	61:  "147573952589676412928",
	60:  "295147905179352825856",
	59:  "590295810358705651712",
	58:  "1180591620717411303424",
	57:  "2361183241434822606848",
	56:  "4722366482869645213696",
	55:  "9444732965739290427392",
	54:  "18889465931478580854784",
	53:  "37778931862957161709568",
	52:  "75557863725914323419136",
	51:  "151115727451828646838272",
	50:  "302231454903657293676544",
	49:  "604462909807314587353088",
	48:  "1208925819614629174706176",
	47:  "2417851639229258349412352",
	46:  "4835703278458516698824704",
	45:  "9671406556917033397649408",
	44:  "19342813113834066795298816",
	43:  "38685626227668133590597632",
	42:  "77371252455336267181195264",
	41:  "154742504910672534362390528",
	40:  "309485009821345068724781056",
	39:  "618970019642690137449562112",
	38:  "1237940039285380274899124224",
	37:  "2475880078570760549798248448",
	36:  "4951760157141521099596496896",
	35:  "9903520314283042199192993792",
	34:  "19807040628566084398385987584",
	33:  "39614081257132168796771975168",
	32:  "79228162514264337593543950336",
	31:  "158456325028528675187087900672",
	30:  "316912650057057350374175801344",
	29:  "633825300114114700748351602688",
	28:  "1267650600228229401496703205376",
	27:  "2535301200456458802993406410752",
	26:  "5070602400912917605986812821504",
	25:  "10141204801825835211973625643008",
	24:  "20282409603651670423947251286016",
	23:  "40564819207303340847894502572032",
	22:  "81129638414606681695789005144064",
	21:  "162259276829213363391578010288128",
	20:  "324518553658426726783156020576256",
	19:  "649037107316853453566312041152512",
	18:  "1298074214633706907132624082305024",
	17:  "2596148429267413814265248164610048",
	16:  "5192296858534827628530496329220096",
	15:  "10384593717069655257060992658440192",
	14:  "20769187434139310514121985316880384",
	13:  "41538374868278621028243970633760768",
	12:  "83076749736557242056487941267521536",
	11:  "166153499473114484112975882535043072",
	10:  "332306998946228968225951765070086144",
	9:   "664613997892457936451903530140172288",
	8:   "1329227995784915872903807060280344576",
	7:   "2658455991569831745807614120560689152",
	6:   "5316911983139663491615228241121378304",
	5:   "10633823966279326983230456482242756608",
	4:   "21267647932558653966460912964485513216",
	3:   "42535295865117307932921825928921026432",
	2:   "85070591730234615865843651857942052864",
	1:   "170141183460469231731687303715884105728",
	0:   "340282366920938463463374607431768211456",
}

const es_mapping = `
{
	"mappings":{
		"full_table":{
			"properties":{
				"ip_version":{
					"type":"integer"
				},
				"ip":{
					"type":"keyword"
                },
                "cidr":{
					"type":"integer"
                },
                "asn":{
					"type":"integer"
                },
                "upstream_asn":{
					"type":"integer"
                },
                "bgp_path":{
					"type":"keyword"
				}
			}
		}
	}
}`

func main() {
	start := time.Now()

	flag.Parse()

	prepDbTables()

	//Initilise Global Maps
	seenv4Peers = make(map[string]bool)
	seenv6Peers = make(map[string]bool)
	v4PeersToEnter = make(map[string]bool)
	v6PeersToEnter = make(map[string]bool)

	seenv4Prefixes = make(map[string]bool)
	seenv6Prefixes = make(map[string]bool)
	v4PrefixesToEnter = make(map[string]bool)
	v6PrefixesToEnter = make(map[string]bool)

	seenBgpEntry = make(map[string]bool)

	// Assign the Tier 1 ASNs
	tierOneASNs = map[string]bool{
		"7018":  true, // AT&T
		"174":   true, // CogentCo
		"209":   true, // CenturyLink (Qwest)
		"3320":  true, // Deutsche Telekom
		"3257":  true, // GTT (Tinet)
		"3356":  true, // Level3
		"3549":  true, // Level3
		"1":     true, // Level3
		"2914":  true, // NTT (Verio)
		"5511":  true, // Orange
		"6453":  true, // Tata
		"6762":  true, //Sparkle
		"12956": true, // Telefonica
		"1299":  true, // TeliaSonera
		"701":   true, // Verizon
		"702":   true, // Verizon
		"703":   true, // Verizon
		"2828":  true, // XO
		"6461":  true, // Zayo (AboveNet)
		"6963":  true, // HE
		"3491":  true, // PCCW
		"1273":  true, // Vodafone (UK)
		"1239":  true, // Sprint
		"2497":  true, // Internet Initiative Japan
	}

	bgpLinesChannel := make(chan string)
	bgpDataChannel := make(chan bgpData)
	bgpDataChannelES := make(chan bgpData)

	wg.Add(11)

	go loadBgpLines(bgpLinesChannel)
	go saveBgpData(bgpDataChannel)
	go saveBgpDataES(bgpDataChannelES)

	bgpDataWg.Add(8)
	go parseLine(bgpLinesChannel, bgpDataChannel, bgpDataChannelES)
	go parseLine(bgpLinesChannel, bgpDataChannel, bgpDataChannelES)
	go parseLine(bgpLinesChannel, bgpDataChannel, bgpDataChannelES)
	go parseLine(bgpLinesChannel, bgpDataChannel, bgpDataChannelES)
	go parseLine(bgpLinesChannel, bgpDataChannel, bgpDataChannelES)
	go parseLine(bgpLinesChannel, bgpDataChannel, bgpDataChannelES)
	go parseLine(bgpLinesChannel, bgpDataChannel, bgpDataChannelES)
	go parseLine(bgpLinesChannel, bgpDataChannel, bgpDataChannelES)
	bgpDataWg.Wait()
	close(bgpDataChannel)
	close(bgpDataChannelES)

	wg.Wait()

	hotSwapTables()

	elapsed := time.Since(start)
	log.Printf("Script took %s", elapsed)
}

func hotSwapTables() {
	db, err := sql.Open("mysql", *db_user+":"+*db_pass+"@/"+*db_name+"?charset=utf8")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Swapping v4 TEMP table with production table")
	_, err = db.Query("RENAME TABLE ipv4_bgp_prefixes TO backup_ipv4_bgp_prefixes, ipv4_bgp_prefixes_temp TO ipv4_bgp_prefixes;")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("RENAME TABLE ipv4_peers TO backup_ipv4_peers, ipv4_peers_temp TO ipv4_peers;")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Swapping v6 TEMP table with production table")
	_, err = db.Query("RENAME TABLE ipv6_bgp_prefixes TO backup_ipv6_bgp_prefixes, ipv6_bgp_prefixes_temp TO ipv6_bgp_prefixes;")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("RENAME TABLE ipv6_peers TO backup_ipv6_peers, ipv6_peers_temp TO ipv6_peers;")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Removing old production 4 prefix table")
	_, err = db.Query("DROP TABLE backup_ipv4_bgp_prefixes")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("DROP TABLE backup_ipv4_peers")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Removing old production 6 prefix table")
	_, err = db.Query("DROP TABLE backup_ipv6_bgp_prefixes")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("DROP TABLE backup_ipv6_peers")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Hotswapping Elastic Search Index")
	esClient, err := elastic.NewSimpleClient()
	if err != nil {
		panic(err)
	}

	ctx := context.Background()
	res, err := esClient.Aliases().Index("_all").Do(ctx)
	if err != nil {
		panic(err)
	}
	previous_index_names := res.IndicesByAlias("bgp_data")

	for _, previous_index_name := range previous_index_names {
		esClient.DeleteIndex(previous_index_name).Do(ctx)
	}

	esClient.Alias().Add(es_index_name, "bgp_data").Do(ctx)
}

func prepDbTables() {
	log.Print(*db_user + ":" + *db_pass + "@/" + *db_name + "?charset=utf8")
	db, err := sql.Open("mysql", *db_user+":"+*db_pass+"@/"+*db_name+"?charset=utf8")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Setting up max_allowed_packet to be 1G")
	_, err = db.Query("SET GLOBAL max_allowed_packet=1073741824")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Drop old v4 TEMP table")
	_, err = db.Query("DROP TABLE IF EXISTS ipv4_bgp_prefixes_temp")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("DROP TABLE IF EXISTS backup_ipv4_bgp_prefixes")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("DROP TABLE IF EXISTS ipv4_peers_temp")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("DROP TABLE IF EXISTS backup_ipv4_peers")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Drop old v6 TEMP table")
	_, err = db.Query("DROP TABLE IF EXISTS ipv6_bgp_prefixes_temp")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("DROP TABLE IF EXISTS backup_ipv6_bgp_prefixes")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("DROP TABLE IF EXISTS ipv6_peers_temp")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("DROP TABLE IF EXISTS backup_ipv6_peers")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Cloning ipv4_bgp_prefixes table schema")
	_, err = db.Query("CREATE TABLE ipv4_bgp_prefixes_temp LIKE ipv4_bgp_prefixes")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("CREATE TABLE ipv4_peers_temp LIKE ipv4_peers")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Cloning ipv6_bgp_prefixes table schema")
	_, err = db.Query("CREATE TABLE ipv6_bgp_prefixes_temp LIKE ipv6_bgp_prefixes")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("CREATE TABLE ipv6_peers_temp LIKE ipv6_peers")
	if err != nil {
		panic(err.Error())
	}

	log.Print("Setting Default dates")
	_, err = db.Query("ALTER TABLE `ipv6_peers_temp` CHANGE COLUMN `created_at` `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("ALTER TABLE `ipv6_peers_temp` CHANGE COLUMN `updated_at` `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("ALTER TABLE `ipv4_peers_temp` CHANGE COLUMN `created_at` `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("ALTER TABLE `ipv4_peers_temp` CHANGE COLUMN `updated_at` `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP")
	if err != nil {
		panic(err.Error())
	}

	_, err = db.Query("ALTER TABLE `ipv4_bgp_prefixes_temp` CHANGE COLUMN `created_at` `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("ALTER TABLE `ipv4_bgp_prefixes_temp` CHANGE COLUMN `updated_at` `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP")
	if err != nil {
		panic(err.Error())
	}

	_, err = db.Query("ALTER TABLE `ipv6_bgp_prefixes_temp` CHANGE COLUMN `created_at` `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP")
	if err != nil {
		panic(err.Error())
	}
	_, err = db.Query("ALTER TABLE `ipv6_bgp_prefixes_temp` CHANGE COLUMN `updated_at` `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP")
	if err != nil {
		panic(err.Error())
	}

	ctx := context.Background()
	esClient, err := elastic.NewSimpleClient()
	if err != nil {
		panic(err)
	}

	es_index_name = "bgp_table_" + strconv.FormatInt(time.Now().Unix(), 10)
	log.Print("Creating a new ES Index: " + es_index_name)

	_, err = esClient.CreateIndex(es_index_name).BodyString(es_mapping).Do(ctx)
	if err != nil {
		panic(err)
	}

}

func parseLine(bgpLinesChannel chan string, bgpDataChannel chan bgpData, bgpDataChannelES chan bgpData) {
	defer wg.Done()
	defer bgpDataWg.Done()

	var removeAsSetsRegex = regexp.MustCompile("s*{[^)]*}")
	var removeRemoveNonNumeric = regexp.MustCompile("[^0-9 ]")

	for bgpLine := range bgpLinesChannel {
		stringParts := strings.Split(bgpLine, "|")

		// Make sure its a valid line
		if len(stringParts) != 15 {
			continue
		}

		if stringParts[7] != "IGP" && stringParts[7] != "EGP" {
			continue
		}

		asPath := removeAsSetsRegex.ReplaceAllString(stringParts[6], "")
		asPath = removeRemoveNonNumeric.ReplaceAllString(asPath, "")
		asPathArray := removeDuplicates(strings.Split(asPath, " "))

		// Make sure we actually have a real path
		if len(asPathArray) < 1 {
			continue
		}

		asn := asPathArray[len(asPathArray)-1]

		// Make sure ASN is not bigger than storage amount
		if len(asn) > 19 {
			continue
		}

		peers := getPeers(asPathArray)
		prefixParts := strings.Split(stringParts[5], "/")

		// Make sure its a valid CIDR
		if len(prefixParts) != 2 {
			continue
		}
		asTransitPath := getTransitPath(asPathArray)

		upstream_asn := ""
		if len(asTransitPath) > 1 {
			upstream_asn = asTransitPath[1]
		}

		proto := "6"
		if IsIPv4(stringParts[5]) {
			proto = "4"
		}

		singleBgpData := bgpData{
			prefix:        stringParts[5],
			ip:            prefixParts[0],
			cidr:          prefixParts[1],
			source:        stringParts[8],
			asn:           asn,
			proto:         proto,
			upstream_asn:  upstream_asn,
			path_string:   strings.Join(asTransitPath, " "),
			path_array:    asTransitPath,
			peer_sets:     peers,
			original_line: bgpLine,
		}

		bgpDataChannel <- singleBgpData
		bgpDataChannelES <- singleBgpData
	}
}

// ARRAY_UNIQUE
func removeDuplicates(elements []string) []string {
	seen := make(map[string]struct{}, len(elements))
	j := 0
	for _, v := range elements {
		if _, ok := seen[v]; ok {
			continue
		}
		if v == "" {
			continue
		}

		seen[v] = struct{}{}
		elements[j] = v
		j++
	}
	return elements[:j]
}

func getTransitPath(asPathArray []string) []string {
	for _, element := range asPathArray {

		// Since we are dealing with ONLY publicly seen prefixes
		// This means that we will need to make sure there is at least
		// a single teir one carrier in the prefix
		// else... we will disregard the BGP entry completely
		if _, ok := tierOneASNs[element]; ok {
			break
		}

		// Delete index from slice
		_, asPathArray = asPathArray[0], asPathArray[1:]
	}

	// Reverse the order of the array
	for i := len(asPathArray)/2 - 1; i >= 0; i-- {
		opp := len(asPathArray) - 1 - i
		asPathArray[i], asPathArray[opp] = asPathArray[opp], asPathArray[i]
	}

	return asPathArray
}

func getPeers(asPathArray []string) [][]string {
	peers := [][]string{}

	for index, asn := range asPathArray {
		// Check if the next item in Slice exists
		if index+1 < len(asPathArray) {
			// make sure we can deal with the ASN length
			if len(asn) < 20 && len(asPathArray[index+1]) < 20 {
				peers = append(peers, []string{asn, asPathArray[index+1]})
			}
		}
	}

	return peers
}

func saveBgpData(bgpDataChannel chan bgpData) {
	defer wg.Done()

	var peerKey string
	var prefixKey string

	numberOfIps := big.NewInt(0)
	numberOfIpsOffset := big.NewInt(0)
	numberOfIpsOffset.SetString("1", 10)

	db, err := sql.Open("mysql", *db_user+":"+*db_pass+"@/"+*db_name+"?charset=utf8")
	if err != nil {
		log.Fatal(err)
	}

	log.Print("Doing saving")

	for bgpData := range bgpDataChannel {
		prefixKey = bgpData.prefix + "|" + bgpData.asn

		// Go through all the peers and create their memory table
		if bgpData.proto == "4" {
			if _, ok := seenv4Prefixes[prefixKey]; ok == false {
				seenv4Prefixes[prefixKey] = true
				cidr, _ := strconv.Atoi(bgpData.cidr)

				if cidr > 24 || cidr == 0 {
					continue
				}

				//Insert Prefixe
				ipDecStart := IP4toDec(bgpData.ip)
				numberOfIps.SetString(ipv4cidrCount[cidr], 10)
				ipDecEnd := big.NewInt(0).Add(ipDecStart, big.NewInt(0).Sub(numberOfIps, numberOfIpsOffset))

				v4PrefixesToEnter["('"+bgpData.ip+"',"+bgpData.cidr+","+ipDecStart.String()+", "+ipDecEnd.String()+", "+bgpData.asn+", 0)"] = true
				if len(v4PrefixesToEnter) >= *bulkInsertAmount {
					log.Print("Inserting " + strconv.Itoa(*bulkInsertAmount) + " IPv4 Prefixes [BULK]")
					dbout, err := db.Query(strings.TrimRight("INSERT INTO ipv4_bgp_prefixes_temp (ip,cidr,ip_dec_start,ip_dec_end,asn,roa_status) VALUES "+KeysString(v4PrefixesToEnter), "\x00"))
					if err != nil {
						panic(err.Error())
					}
					dbout.Close()
					v4PrefixesToEnter = make(map[string]bool)
				}
			}

			// Go through peers
			for _, peerSet := range bgpData.peer_sets {
				// Get smallest ASN first
				if peerSet[0] < peerSet[1] {
					peerKey = "(" + peerSet[0] + "," + peerSet[1] + ")"
				} else {
					peerKey = "(" + peerSet[1] + "," + peerSet[0] + ")"
				}

				// Make sure its a new
				if _, ok := seenv4Peers[peerKey]; ok == false {
					seenv4Peers[peerKey] = true
					v4PeersToEnter[peerKey] = true
				}

				if len(v4PeersToEnter) >= *bulkInsertAmount {
					log.Print("Inserting " + strconv.Itoa(*bulkInsertAmount) + " IPv4 Peers [BULK]")
					dbout, err := db.Query(strings.TrimRight("INSERT INTO ipv4_peers_temp (asn_1,asn_2) VALUES "+KeysString(v4PeersToEnter), "\x00"))
					if err != nil {
						panic(err.Error())
					}
					dbout.Close()
					v4PeersToEnter = make(map[string]bool)
				}
			}
		} else {

			if _, ok := seenv6Prefixes[prefixKey]; ok == false {
				seenv6Prefixes[prefixKey] = true
				cidr, _ := strconv.Atoi(bgpData.cidr)

				if cidr > 48 || cidr == 0 {
					continue
				}

				//Insert Prefixe
				ipDecStart := IP6toDec(bgpData.ip)
				numberOfIps.SetString(ipv6cidrCount[cidr], 10)
				ipDecEnd := big.NewInt(0).Add(ipDecStart, big.NewInt(0).Sub(numberOfIps, numberOfIpsOffset))

				v6PrefixesToEnter["('"+bgpData.ip+"',"+bgpData.cidr+","+ipDecStart.String()+", "+ipDecEnd.String()+", "+bgpData.asn+", 0)"] = true
				if len(v6PrefixesToEnter) >= *bulkInsertAmount {
					log.Print("Inserting " + strconv.Itoa(*bulkInsertAmount) + " IPv6 Prefixes [BULK]")
					dbout, err := db.Query(strings.TrimRight("INSERT INTO ipv6_bgp_prefixes_temp (ip,cidr,ip_dec_start,ip_dec_end,asn,roa_status) VALUES "+KeysString(v6PrefixesToEnter), "\x00"))
					if err != nil {
						panic(err.Error())
					}
					dbout.Close()
					v6PrefixesToEnter = make(map[string]bool)
				}
			}

			// Go through peers
			for _, peerSet := range bgpData.peer_sets {
				// Get smallest ASN first
				if peerSet[0] < peerSet[1] {
					peerKey = "(" + peerSet[0] + "," + peerSet[1] + ")"
				} else {
					peerKey = "(" + peerSet[1] + "," + peerSet[0] + ")"
				}

				// Make sure its a new
				if _, ok := seenv6Peers[peerKey]; ok == false {
					seenv6Peers[peerKey] = true
					v6PeersToEnter[peerKey] = true
				}

				if len(v6PeersToEnter) >= *bulkInsertAmount {
					log.Print("Inserting " + strconv.Itoa(*bulkInsertAmount) + " IPv6 Peers [BULK]")
					dbout, err := db.Query(strings.TrimRight("INSERT INTO ipv6_peers_temp (asn_1,asn_2) VALUES "+KeysString(v6PeersToEnter), "\x00"))
					if err != nil {
						panic(err.Error())
					}
					dbout.Close()
					v6PeersToEnter = make(map[string]bool)
				}
			}
		}
	}

	// Insert all remaining IPv6 Peers
	log.Print("Inserting REMAINING IPv4 Peers [BULK]")
	_, err = db.Query(strings.TrimRight("INSERT INTO ipv4_peers_temp (asn_1,asn_2) VALUES "+KeysString(v4PeersToEnter), "\x00"))

	if err != nil {
		panic(err.Error())
	}

	log.Print("Inserting REMAINING IPv6 Peers [BULK]")
	_, err = db.Query(strings.TrimRight("INSERT INTO ipv6_peers_temp (asn_1,asn_2) VALUES "+KeysString(v6PeersToEnter), "\x00"))
	if err != nil {
		panic(err.Error())
	}

	log.Print("Inserting REMAINING IPv4 Prefixes [BULK]")
	_, err = db.Query(strings.TrimRight("INSERT INTO ipv4_bgp_prefixes_temp (ip,cidr,ip_dec_start,ip_dec_end,asn,roa_status) VALUES "+KeysString(v4PrefixesToEnter), "\x00"))
	if err != nil {
		panic(err.Error())
	}

	log.Print("Inserting REMAINING IPv6 Prefixes [BULK]")
	_, err = db.Query(strings.TrimRight("INSERT INTO ipv6_bgp_prefixes_temp (ip,cidr,ip_dec_start,ip_dec_end,asn,roa_status) VALUES "+KeysString(v6PrefixesToEnter), "\x00"))
	if err != nil {
		panic(err.Error())
	}

}

func saveBgpDataES(bgpDataChannelES chan bgpData) {
	defer wg.Done()
	ctx := context.Background()
	esClient, err := elastic.NewSimpleClient()
	if err != nil {
		panic(err)
	}

	bulkRequest := esClient.Bulk()

	var bgpEntryKey string

	for bgpData := range bgpDataChannelES {
		bgpEntryKey = bgpData.prefix + bgpData.path_string

		// Make sure its a new entry totally
		if _, ok := seenBgpEntry[bgpEntryKey]; ok == false {
			seenBgpEntry[bgpEntryKey] = true

			bgpEsEntry := `{"ip_version" : ` + bgpData.proto + `, "ip" : "` + bgpData.ip + `", "cidr": ` + bgpData.cidr + `, "asn": ` + bgpData.asn + `, "upstream_asn": ` + bgpData.upstream_asn + `, "bgp_path": "` + bgpData.path_string + `"}`
			indexReq := elastic.NewBulkIndexRequest().Index(es_index_name).Type("full_table").Doc(bgpEsEntry)
			bulkRequest = bulkRequest.Add(indexReq)

			if bulkRequest.NumberOfActions() == *bulkEsInsertAmount {
				log.Print("Inserting " + strconv.Itoa(*bulkEsInsertAmount) + " BGP Entries to ElasticSearch")
				_, err := bulkRequest.Do(ctx)
				if err != nil {
					panic(err.Error())
				}
			}
		}
	}

	// Insert all remaining ES entries
	if bulkRequest.NumberOfActions() > 0 {
		log.Print("Inserting Remaining" + strconv.Itoa(bulkRequest.NumberOfActions()) + " BGP Entries to ElasticSearch")
		_, err := bulkRequest.Do(ctx)
		if err != nil {
			panic(err.Error())
		}
	}

	// Hotswap the index

}

func loadBgpLines(bgpLinesChannel chan string) {
	defer wg.Done()
	file, err := os.Open(*bgp_file)
	if err != nil {
		log.Fatal(err)
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		bgpLinesChannel <- scanner.Text()
	}

	close(bgpLinesChannel)

	if err := scanner.Err(); err != nil {
		log.Fatal(err)
	}

}

func IsIPv4(str string) bool {
	ipAddr, _, _ := net.ParseCIDR(str)
	return ipAddr != nil && ipAddr.To4() != nil
}

func KeysString(m map[string]bool) string {
	n := 2 * len(m) // (len-1) commas (", "), and one each of "[" and "]".
	for k := range m {
		n += len(k)
	}
	b := make([]byte, n)
	bp := copy(b, "")
	first := true
	for k := range m {
		if !first {
			bp += copy(b[bp:], ",")
		}
		bp += copy(b[bp:], k)
		first = false
	}
	return string(b)
}

func IP6toDec(v6Address string) *big.Int {
	IPv6Address := net.ParseIP(v6Address)
	IPv6Int := big.NewInt(0)
	IPv6Int.SetBytes(IPv6Address)
	return IPv6Int
}

func IP4toDec(v4Address string) *big.Int {
	IPv4Address := net.ParseIP(v4Address)
	IPv4Int := big.NewInt(0)
	IPv4Int.SetBytes(IPv4Address.To4())
	return IPv4Int
}
