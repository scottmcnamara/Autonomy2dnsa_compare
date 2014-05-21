<?php

//call example: php autn2dnsa_compare.php autn2dnsa.queries.05-May-14 10
/*
Pulls in and executes Autonomy and DNSA (MarkLogic) search queries
from a tab-delimeted files as produced by the autn2dnsa.php script
Format of input file:

1	http://stg-search1.production.bigcharts.com:9000/?action=query&text=Rice&mindate=04/05/2013&sort=dociddecreasing&fieldtext=MATCH{us}:MKTW-ISSUE&maxresults=15&databasematch=53,90,91,92,103,105,107,110,281,461,485,495,496&minscore=60&print=fields&printfields=guid&totalresults=true	http://sbkj2ksrchsvc1.wsjqa.dowjones.net:19080/wuss/dnsa/v1/rest/search/ComparisonTest?language=en&query=(Rice) AND (mktw-issue-value:="us")&min-date=2013/05/04&database=mw/53,mw/90,mw/91,mw/92,mw/103,mw/105,mw/107,mw/110,mw/281,mw/461,mw/485,mw/495,mw/496&sort=date-desc&count=15&return-fields=guid

Outputs numbered files containing search results for each input query set
Generated files:
1.dnsa.results (query set 1 DNSA result)
1.autn.results (query set 1 Autonomy result)
- these files can then be compared to assess the differences in results
- we would expect to see significant overalap in result
*/

$autn_query_log = StringNotEmpty($argv[1]) ? $argv[1] : "";
$count_to_process = intval($argv[2])!=0 ? $argv[2] : 0;

$get_autn_results = true;
$get_dnsa_results = true;
$compare = true;

function StringNotEmpty($str_var){
    return !(!isset($str_var) || trim($str_var)==='');
}

function get_query_results($url) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$curl_scraped_page = curl_exec($ch);
	curl_close($ch);
	return $curl_scraped_page;
}

function format_response_xml($xml_string) {
	$dom = new DOMDocument;
	$dom->preserveWhiteSpace = false;
	$dom->loadXML($xml_string);
	$dom->formatOutput = true;
	$xmlStr = $dom->saveXML();
	return $xmlStr;
}

function parse_autn_results($autn_results_xml) {
//autnresponse/response
//autnresponse/responsedata/autn:totalhits

	$xml = new SimpleXMLElement($autn_results_xml);

	$response_status = $xml->xpath('/autnresponse/response');
	$response_totalhits = $xml->xpath('/autnresponse/responsedata/autn:totalhits');
	echo "/autnresponse/response: " . $response_status[0] . "\n";
	echo "/autnresponse/responsedata/autn:totalhits: " . $response_totalhits[0] . "\n";
//	while(list( , $node) = each($result)) {
//		echo '/a/b/c: ',$node,"\n";
//	}
//	return $autn_id_array;
}
function parse_dnsa_results($dnsa_results_xml) {
//search-response/info/hits/matching-docs-estimate

	$xml = new SimpleXMLElement($dnsa_results_xml);

	//$response_status = $xml->xpath('/autnresponse/response');
	$response_totalhits = $xml->xpath('//search-response');
	//echo "/autnresponse/response: " . $response_status[0] . "\n";
	echo "/search-response/info/hits/matching-docs-estimate: " . $response_totalhits[0] . "\n";
//	while(list( , $node) = each($result)) {
//		echo '/a/b/c: ',$node,"\n";
//	}
//	return $autn_id_array;
}

$row = 0;
if (($handle = fopen($argv[1], "r")) !== FALSE) {
    while ($row < $count_to_process && ($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
		$num = count($data);
		$row_num = $data[0];
		$autn_url = str_replace(' ','+',$data[1]);
		$dnsa_url = str_replace(' ','+',$data[2]);
        $row++;
        for ($c=0; $c < $num; $c++) {
            //echo $data[$c] . "\t";
        }
		echo "\n";
		$query_results = "";
		if ($get_autn_results) {
			echo $autn_url."\n";
			$query_results = get_query_results($autn_url);
			parse_autn_results($query_results);
			//echo $query_results;
			$file = $row_num . '.autn.results';
			file_put_contents($file, format_response_xml($query_results . "\n<!--".$autn_url."-->\n"), LOCK_EX);	
		}

		$query_results = "";
		if ($get_dnsa_results) {
			echo $dnsa_url."\n";
			$query_results = get_query_results($dnsa_url);
			parse_dnsa_results($query_results);
			//echo $query_results;
			$file = $row_num . '.dnsa.results';
			file_put_contents($file, format_response_xml($query_results . "\n<!--".$dnsa_url."-->\n"), LOCK_EX);	
		}
	}
    fclose($handle);
}
?>