<?php

//$ php autn2dnsa.php query.log.05-May-14 -tsv -id_only >autn2dnsa.queries.05-May-14
/*
Takes and Autonomy Query Log file and translates autonomy 
queries into a call to the new search service (DNSA).

ex. 
From autonomy query log:
04/05/2014 19:41:30 [105] a=query&text=Rice&mindate=04/05/2013&sort=dociddecreasing&fieldtext=MATCH%7Bus%7D:MKTW-ISSUE&maxresults=15&databasematch=53,90,91,92,103,105,107,110,281,461,485,495,496&minscore=60&print=fields&printfields=DOCTYPE,TITLE,AUTHOR,COLUMN,CREATED,LASTUPDATED,CHANNEL,SUBCHANNEL,PROVIDER,ABSTRACT,FILE-LOCATION,CLIP-DURATION,CLIP-DATEPATH&summary=context&sentences=2&highlight=terms+summaryterms&starttag=%3Cspan%20class='highlightterm'%3E&endtag=%3C/span%3E&querysummary=true (127.0.0.1)

Generates translated query into new DNSA search service call::
http://sbkj2ksrchsvc1.wsjqa.dowjones.net:19080/wuss/dnsa/v1/rest/search/ComparisonTest?language=en&query=(Rice) AND (mktw-issue-value:="us")&min-date=2013/05/04&database=mw/53,mw/90,mw/91,mw/92,mw/103,mw/105,mw/107,mw/110,mw/281,mw/461,mw/485,mw/495,mw/496&sort=date-desc&count=15&return-fields=guid

Produces a tab-delimetted file old and new queries so they can be executed and compared
Output format:
1	http://stg-search1.production.bigcharts.com:9000/?action=query&text=Rice&mindate=04/05/2013&sort=dociddecreasing&fieldtext=MATCH{us}:MKTW-ISSUE&maxresults=15&databasematch=53,90,91,92,103,105,107,110,281,461,485,495,496&minscore=60&print=fields&printfields=guid&totalresults=true	http://sbkj2ksrchsvc1.wsjqa.dowjones.net:19080/wuss/dnsa/v1/rest/search/ComparisonTest?language=en&query=(Rice) AND (mktw-issue-value:="us")&min-date=2013/05/04&database=mw/53,mw/90,mw/91,mw/92,mw/103,mw/105,mw/107,mw/110,mw/281,mw/461,mw/485,mw/495,mw/496&sort=date-desc&count=15&return-fields=guid

*/

ini_set( "display_warnings", 0);
$mw_queries=file($argv[1]);
$output_format = $argv[2];
$id_only = $argv[3];

$dnsa_service_url = "http://sbkj2ksrchsvc1.wsjqa.dowjones.net:19080/wuss/dnsa/v1/rest/search/ComparisonTest?";
$autn_service_url = "http://stg-search1.production.bigcharts.com:9000/?";

$arrlength=count($mw_queries);

$feild_map = [
	"text" => "dnsa_query",
	"fieldtext" => "dnsa_match",
	"mindate" => "min-date",
	"maxdate" => "max-date",
	"databasematch" => "database",
	"sort" => "sort",
	"start" => "start",
	"maxresults" => "count",
	"printfields" => "return-fields",
];
$feild_value_map = [
	"text" => "",
	"fieldtext" => "",
	"mindate" => "",
	"maxdate" => "",
	"databasematch" => "",
	"sort" => "",
	"start" => "",
	"maxresults" => "",
	"printfields" => "",
];



function convert_date($autn_param_date) {
	$tmp_date = explode("/", $autn_param_date);
	$dnsa_date = $tmp_date[2]."/".$tmp_date[1]."/".$tmp_date[0];
	return $dnsa_date;
}

function convert_sort($autn_param_sort) {
/*
DNSA values
none
date-asc
date-desc
default:relevance
relevance-date
*/
	$dnsa_sort = "";
	if ($autn_param_sort=="date") {
		$dnsa_sort = "date-desc";
	} elseif ($autn_param_sort=="dociddecreasing") {
		$dnsa_sort = "date-desc";
	} elseif ($autn_param_sort=="relevance") {
		$dnsa_sort = "relevance";
	} else {
		$dnsa_sort = "relevance";
	}
	return $dnsa_sort;
}

function convert_printfields($autn_printfields) {
	$return_field_map = [
		"abstract" => "summary-body",
		"author" => "author-name",
		"clip-datepath" => "clip-datepath-value",
		"clip-duration" => "clip-duration-value",
		"cliptype" => "cliptype-value",
		"column" => "type",
		"created" => "date",
		"docid" => "",
		"doctype" => "doctype-value",
		"file-location" => "",
		"lastupdated" => "last-pub-date",
		"guid" => "guid",
		"provider" => "",
//		"symb" => "co-symbol",
		"sid" => "",
//		"symbol" => "co-symbol",
		"title" => "title",
		"url" => "url",
	];
	$dnsa_return_fields="id";
	$autn_printfields_array = explode(",", $autn_printfields);
	for($x=0;$x<count($autn_printfields_array);$x++) {
		//echo "-------". $autn_printfields_array[$x];
		if (array_key_exists($autn_printfields_array[$x], $return_field_map)) {
			//echo "-----------". $return_field_map[$autn_printfields_array[$x]];
			if (StringNotEmpty($return_field_map[$autn_printfields_array[$x]])) {
				$dnsa_return_fields .= (StringNotEmpty($dnsa_return_fields) ? ",": "").trim($return_field_map[$autn_printfields_array[$x]]);
			}
		}
	}
	return $dnsa_return_fields;
}


function IsNullOrEmptyString($str_var){
    return (!isset($str_var) || trim($str_var)==='');
}

function StringNotEmpty($str_var){
    return !(!isset($str_var) || trim($str_var)==='');
}

function convert_fieldtext($autn_fieldtext) {

	$match_field_map = [
	"marketwatch field" => "dnsa field",
	"author" => "author-name",
//	"channel" => "** not supported **",
	"column" => "type",
//	"docid" => "** not supported **",
	"doctype" => "doctype-value",
	"factiva-com--industrycode" => "in-value-strong",
	"factiva-com--regioncode" => "re-value-strong",
	"factiva-com--subjectcode" => "ns-value-strong",
	"mktw-industry" => "mktw-industry-value",
	"mktw-issue" => "mktw-issue-value",
	"mktw-location" => "mktw-region-value",
	"person" => "pe-name-strong",
//	"sid " => "** not supported **",
//	"subchannel2" => "** not supported **",
	"symb" => "co-symbol-strong",
	];

	$dnsa_fieldtext = "";

	if (strpos($autn_fieldtext,' OR ') !== false) {
		// OR condition
		$autn_fieldtext_arr_tmp = explode(" OR ", $autn_fieldtext);
		$match1 = $autn_fieldtext_arr_tmp[0];
		$match2 = $autn_fieldtext_arr_tmp[1];
		
		$match1 = str_ireplace("match{","",$match1);
		$match1_fieldtext_arr = explode("}:", $match1);
		if (array_key_exists(strtolower($match1_fieldtext_arr[1]), $match_field_map)) {
			$dnsa_fieldtext = $match_field_map[strtolower($match1_fieldtext_arr[1])].":=\"".$match1_fieldtext_arr[0]."\"";
		}

		$match2 = str_ireplace("match{","",$match2);
		$match2_fieldtext_arr = explode("}:", $match2);
		if (array_key_exists(strtolower($match2_fieldtext_arr[1]), $match_field_map)) {
			$dnsa_fieldtext .= " OR " . $match_field_map[strtolower($match2_fieldtext_arr[1])].":=\"".$match2_fieldtext_arr[0]."\"";
		}
	} elseif (stripos($autn_fieldtext,'match') !== false) {  //match{auto review}:column
		$autn_fieldtext = str_ireplace("match{","",$autn_fieldtext);
		if (strpos($autn_fieldtext,'}:') !== false) {
			$autn_fieldtext_arr = explode("}:", $autn_fieldtext);
			if (StringNotEmpty($autn_fieldtext_arr[1]) && array_key_exists(strtolower($autn_fieldtext_arr[1]), $match_field_map)) {
				$dnsa_fieldtext = $match_field_map[strtolower($autn_fieldtext_arr[1])].":=\"".$autn_fieldtext_arr[0]."\"";
			}
		}
	}
	return $dnsa_fieldtext;
}

function convert_text_fieldtext($text_value, $fieldtext_value) {
//echo "+++=====text_feildtext=".$text_value . "--". $fieldtext_value."\n";
	$dnsa_text_fieldtext = 	(StringNotEmpty($text_value) ? "(".$text_value.")" : "") . (StringNotEmpty($fieldtext_value) ? (StringNotEmpty($text_value) ? " AND " : "") . "(" . convert_fieldtext($fieldtext_value) . ")" : "");
	return $dnsa_text_fieldtext;
}

function convert_databasematch($databasematch) {
	$database_mw2wsj_map = [
	"424" => "blog",
	"425" => "wsjie",
	"426" => "barrons",
	"446" => "barrons",
	];

	$database_mw_depricated_map = [
	"106" => "",
	"109" => "",
	"111" => "",
	"124" => "",
	"127" => "",
	"249" => "",
	"319" => "",
	"462" => "",
	"30033" => "",
	];

	$dnsa_database_str = "";
	$dnsa_database_arr = explode(",",$databasematch);
	for($y=0;$y<count($dnsa_database_arr);$y++) {
		if (array_key_exists($dnsa_database_arr[$y], $database_mw2wsj_map)) {
			if (StringNotEmpty($database_mw2wsj_map[$dnsa_database_arr[$y]])) {
				//add with no mw/ prefix
				$dnsa_database_str .= (StringNotEmpty($dnsa_database_str) ? "," : "")."". $database_mw2wsj_map[$dnsa_database_arr[$y]];
			} 
		} elseif (array_key_exists($dnsa_database_arr[$y], $database_mw_depricated_map)) {
			// don't add it to the query.
			$dnsa_database_str .= "";
		} else {
			$dnsa_database_str .= (StringNotEmpty($dnsa_database_str) ? "," : "")."mw/".$dnsa_database_arr[$y];
		}
	}
	return $dnsa_database_str;
}

//echo "\nid_only=". $id_only."\n";
//echo "row\tAutonomy\tDNSA\n";
$row_count = 0;
for($x=0; $x<$arrlength; $x++) {
	//echo "\n---------------------\n\nAutonomy:\n";//.$mw_queries[$x]."";
	//$mw_autn_query = $mw_queries[$x];
	if (strpos($mw_queries[$x], "action=Query") !== false) {
		$mw_param_str = (explode("] action=Query&", $mw_queries[$x])[1]);
	} elseif (strpos($mw_queries[$x], "action=query") !== false) {
		$mw_param_str = (explode("] action=query&", $mw_queries[$x])[1]);
	} elseif (strpos($mw_queries[$x], "a=Query") !== false) {
		$mw_param_str = (explode("] a=Query&", $mw_queries[$x])[1]);
	} elseif (strpos($mw_queries[$x], "a=query") !== false) {
		$mw_param_str = (explode("] a=query&", $mw_queries[$x])[1]);
	} else { continue; }
	$mw_param_str = urldecode($mw_param_str);
	$mw_param_str = str_replace(" (127.0.0.1)", "", $mw_param_str);
	$mw_param_str = preg_replace("/\\n/", "", $mw_param_str);
	
	if (StringNotEmpty($id_only) && $id_only=="-id_only" ) {
		$mw_param_str = preg_replace("/printfields=.*&/i","printfields=guid&",$mw_param_str);
		if (stripos($mw_param_str, "printfields=ID") === false) {
			$mw_param_str = preg_replace("/printfields=.*/i","printfields=guid",$mw_param_str);
		}
		if (stripos($mw_param_str, "totalresults=true") === false) {
			$mw_param_str .= "&totalresults=true";
		}
	} 
	$row_count++;
	echo $row_count."\t".$autn_service_url."action=query&".$mw_param_str."\t";

	$mw_param_str = str_replace("[*20]", "", $mw_param_str);
	$mw_param_str = str_replace("[*10]", "", $mw_param_str);
//	$mw_param_str = strtolower($mw_param_str);
	foreach ($feild_value_map as $key => $value) {
		$feild_value_map[$key] = "";
	}

	$param_name_val=explode("&", $mw_param_str);
	for($y=0;$y<count($param_name_val);$y++) {
		$param_name=explode("=", $param_name_val[$y]);
		if(array_key_exists(strtolower($param_name[0]), $feild_value_map)) {
			$feild_value_map[strtolower($param_name[0])] = $param_name[1];
			//echo "param name=".$param_name[0]. " value=". $feild_value_map[$param_name[0]] ."\n";
		}
	}

	//query=obama&database=wsjie&language=en&return-fields=id,title,language,last-pub-date

	$dnsa_query = "language=en";
	if (StringNotEmpty($feild_value_map["text"]) || StringNotEmpty($feild_value_map["fieldtext"])) {
		$dnsa_query .= "&query=". convert_text_fieldtext($feild_value_map["text"], $feild_value_map["fieldtext"]) . ""; 
	}
	if (StringNotEmpty($feild_value_map["mindate"])) {
		$dnsa_query .= "&" . $feild_map["mindate"] . "=". convert_date($feild_value_map["mindate"]) . ""; 
	}
	if (StringNotEmpty($feild_value_map["maxdate"]) && StringNotEmpty($feild_map["maxdate"])) {
		$dnsa_query .= "&" . $feild_map["maxdate"] . "=". convert_date($feild_value_map["maxdate"]) . ""; 
	}
	if (StringNotEmpty($feild_value_map["databasematch"])) {
		$dnsa_query .= "&" . $feild_map["databasematch"] . "="  .  convert_databasematch($feild_value_map["databasematch"]) . ""; 
	}
	if (StringNotEmpty($feild_value_map["sort"])) {
		$dnsa_query .= "&" . $feild_map["sort"] . "=" . convert_sort($feild_value_map["sort"]) . ""; 
	}
	if (StringNotEmpty($feild_value_map["start"])) {
		$dnsa_query .= "&" . $feild_map["start"] . "=" . $feild_value_map["start"] . ""; 
	}
	if (StringNotEmpty($feild_value_map["maxresults"])) {
		$dnsa_query .= "&" . $feild_map["maxresults"] . "=" . $feild_value_map["maxresults"] . ""; 
	}
	if (StringNotEmpty($feild_value_map["printfields"])) {
		if (StringNotEmpty($id_only) && $id_only=="-id_only" ) {
			$dnsa_query .= "&" . $feild_map["printfields"] . "=" . "guid"; 
		} else {
			$dnsa_query .= "&" . $feild_map["printfields"] . "=" . convert_printfields($feild_value_map["printfields"]) . ""; 
		}
	}
	echo $dnsa_service_url . $dnsa_query . "\n";

	for($y=0;$y<count($param_name_val);$y++) {
//		$param_name=explode("=", $param_name_val[$y]);
	}

	if($row_count>=10){
		break;
	}

}
/*

a=query&text=%22Brett+Arends%22%5b*20%5d+OR+(Brett+Arends)%5b*10%5d&mindate=05/12/2012&sort=dociddecreasing&fieldtext=MATCH%7Bcomputer+hardware%7D:MKTW-INDUSTRY&maxresults=15&databasematch=53,90,91,92,103,105,107,110,281,461,485,495,496&minscore=60&print=fields&printfields=DOCTYPE,TILE,AUTHOR,COLUMN,CREATED,LASTUPDATED,CHANNEL,SUBCHANNEL,PROVIDER,ABSTRACT,FILE-LOCATION,CLIP-DURATION,CLIP-DATEPATH&summary=context&sentences=&highlight=terms+summaryterms&starttag=%3Cspan%20class='highlightterm'%3E&endtag=%3C/span%3E&querysummary=true

a=query&text="Brett Arends"[*20] OR (Brett Arends)[*10]&mindate=05/12/2012&sort=dociddecreasing&fieldtext=MATCH{computer hardware}:MKTW-INDUSTRY&maxresults=15&databasematch=53,90,91,92,103,105,107,110,281,461,485,495,496&minscore=60&print=fields&printfields=DOCTYPE,TILE,AUTHOR,COLUMN,CREATED,LASTUPDATED,CHANNEL,SUBCHANNEL,PROVIDER,ABSTRACT,FILE-LOCATION,CLIP-DURATION,CLIP-DATEPATH&summary=context&sentences=&highlight=terms summaryterms&starttag=<span class='highlightterm'>&endtag=</span>&querysummary=true
*/

?>