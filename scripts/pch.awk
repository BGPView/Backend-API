BEGIN{
	prefix = "";
}
{
	# prefixregex="^[0-9a-f:\\./]+(:\.)[0-9a-f:\\./]*$"
	prefixregex = "^([0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3})(/[0-9]{1,3})?$"
	# we have a prefix, let's see if we have an as path
	aspath = "";
	if (NF == 0 || $0 ~ "^Total number of prefixes ") {
		# skip
	} else if ($2 ~ prefixregex && $3 == "") {
		prefix = $2;
	} else if ($2 ~ prefixregex && $3 ~ prefixregex) {
		prefix = $2;
		for (pos = 6; pos < NF; pos++) {
			aspath = aspath" "$pos;
		}
	} else if ($2 ~ prefixregex && $3 !~ prefixregex) {
		for (pos = 5; pos < NF; pos++) {
			aspath = aspath" "$pos;
		}
	} else if ($1 ~ prefixregex && $2 !~ prefixregex) {
		prefix = $1
		for (pos = 4; pos < NF; pos++) {
			aspath = aspath" "$pos
		}
	} else {
		if (prefix != "") {
			print "UNKNOWN", NR, prefix;
			print $0;
			exit;
		}
	}
	if (prefix != "" && prefix !~ "/") {
		if (prefix ~ ":") {
			prefix = prefix"/128";
		} else {
			prefix = prefix"/32";
		}
	}
	if (prefix != "" && aspath != "") {
		print "|||||"prefix"|"aspath"||||||||";
	}
}
