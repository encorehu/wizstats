<?php
#    wizstats - bitcoin pool web statistics - 1StatsQytc7UEZ9sHJ9BGX2csmkj8XZr2
#    Copyright (C) 2012  Jason Hughes <wizkid057@gmail.com>
#
#    This program is free software: you can redistribute it and/or modify
#    it under the terms of the GNU Affero General Public License as published by
#    the Free Software Foundation, either version 3 of the License, or
#    (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU Affero General Public License for more details.
#
#    You should have received a copy of the GNU Affero General Public License
#    along with this program.  If not, see <http://www.gnu.org/licenses/>.


require_once 'includes.php';

require_once 'hashrate.php';

if (!isset($_SERVER['PATH_INFO'])) {
	print_stats_top();
	print "<BR><FONT COLOR=\"RED\"><B>错误:</B> URL中未指定用户名(你的比特币地址), 请设定比特币地址后再试.</FONT><BR>";
	print_stats_bottom();
	exit;
}


$givenuser = substr($_SERVER['PATH_INFO'],1,strlen($_SERVER['PATH_INFO'])-1);

if ($givenuser == "") {
	print_stats_top();
	print "<BR><FONT COLOR=\"RED\"><B>错误:</B> URL中未指定用户名(你的比特币地址), 请设定比特币地址后再试.</FONT><BR>";
	print_stats_bottom();
	exit;
}


if (array_key_exists($givenuser,$specialaddrs)) {
	print_stats_top();
	$desc = $specialaddrs[$givenuser];
	print "<BR><B>$givenuser</B> is a special address labeled <I>$desc</I> and has no easily compiled stats.<BR>\n";
	print_stats_bottom();
	exit;
}


$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");

if (pg_connection_status($link) != PGSQL_CONNECTION_OK) {
	pg_close($link);
	$link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost");
	if (pg_connection_status($link) != PGSQL_CONNECTION_OK) {
		print_stats_top();
		print "<BR><FONT COLOR=\"RED\"><B>错误:</B> Unable to establish a connection to the stats database.  Please try again later. If this issue persists, please report it to the pool operator.</FONT><BR>";
		print_stats_bottom();
		exit;
	}
}

$user_id = get_user_id_from_address($link, $givenuser);

if (!$user_id) {
	print_stats_top();
	print "<BR><FONT COLOR=\"RED\"><B>错误:</B> 用户名(您的比特币地址) <I>$givenuser</I> 没有在数据库中找到. 请稍候再试. 可能的原因是1.你的用户名是错误的; 2.服务器超负荷运转导致暂时停止服务, 如果这个问题持续了几个小时, 请和矿池管理员联系.</FONT><BR>";
	print_stats_bottom();
	exit;
}

$worker_data = get_worker_data_from_user_id($link, $user_id);

if (isset($_GET["cmd"])) {
	include("userstats_subcmd.php");
}

$cppsrbloaded = 0;

if($balanacesjsondec = apc_fetch('balance')) {
} else {
	$balance = file_get_contents("$pooldatadir/$serverid/balances.json");
	$balanacesjsondec = json_decode($balance, true);
	// Store Cache for 10 minutes
	apc_store('balance', $balanacesjsondec, 600);
}
if(count($balanacesjsondec) != 0){
	$mybal = $balanacesjsondec[$givenuser];
} else {
	$mybal = null;
}

if ($mybal) {
	if (isset($mybal["balance"])) {
		$bal = $mybal["balance"];
	} else {
		$bal = 0;
	}
	if (isset($mybal["credit"])) {
		$ec = $mybal["credit"];
	} else {
		$ec = 0;
	}
	if (isset($mybal["donated"])) {
		$donated = $mybal["donated"];
	} else {
		$donated = 0;
	}
	$datadate = $mybal["newest"];
	if (isset($mybal["included_balance_estimate"])) {
		$lbal = $bal - $mybal["included_balance_estimate"];
	} else {
		$lbal = $bal;
	}
	if (isset($mybal["included_credit_estimate"])) {
		$lec = $ec - $mybal["included_credit_estimate"];
	} else {
		$lec = $ec;
	}
	if (isset($mybal["everpaid"])) { $everpaid = $mybal["everpaid"]; } else { $everpaid = 0; }
	$balupdate = $mybal["last_balance_update"];
} else {
	# fall back to sql
	$sql = "select * from $psqlschema.stats_balances where server=$serverid and user_id=$user_id order by time desc limit 1";
	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	if (!$numrows) {
		$bal = "N/A"; $cbe = "N/A"; $ec = "N/A"; $datadate = "N/A"; $lbal = "N/A";
		$lec=0;
		$everpaid = 0;
		$donated = 0;
	} else {
		$row = pg_fetch_array($result, 0);
		$bal = $row["balance"];
		$ec = $row["credit"];
		$lec = $ec;
		$lbal = "N/A";
		$datadate = $row["time"];
		$everpaid = $row["everpaid"];
	}
}

if($balanacesjsondecSM = apc_fetch('balance_smpps')) {
} else {
	$balanacesjsonSM = file_get_contents("$pooldatadir/$serverid/smpps_lastblock.json");
	$balanacesjsondecSM = json_decode($balanacesjsonSM,true);
	// Store Cache forever (10 days)
	apc_store('balance_smpps', $balanacesjsondecSM, 864000);
}
if (isset($balanacesjsondecSM[$givenuser])) { $mybalSM = $balanacesjsondecSM[$givenuser]; }

if (isset($mybalSM)) {
	# SMPPS credit needed to be halved for the pool to be statistically viable
	$smppsec = $mybalSM["credit"];
	$smppshalf = $mybalSM["credit"]/2;
	$smppsec -= $smppshalf;
} else {
	$smppsec = 0;
}

$unpaid_balance = $lbal;
$shelved_shares = $lec;
$shelved_shares_estimate = $ec;

$estimated_balance = $bal;
$estimated_change = $estimated_balance - $unpaid_balance;

$total_rewarded = $everpaid + $unpaid_balance + $donated;
$maximum_reward = $everpaid + $estimated_balance + $shelved_shares_estimate + $smppsec + $donated;

$unpaid_balance_print = prettySatoshis($unpaid_balance);
$estimated_change_print = "+".prettySatoshis($estimated_change); # can/should never be negative...
$estimated_balance_print = prettySatoshis($estimated_balance);
if(($total_rewarded + $shelved_shares + $smppsec + $donated)==0){
	$percent_pps = 0;
} else {
	$percent_pps = $total_rewarded/($total_rewarded + $shelved_shares + $smppsec + $donated);
}
if($maximum_reward==0){
	$percent_pps_estimate = 0;
} else {
	$percent_pps_estimate = ($estimated_balance+$everpaid)/$maximum_reward;
}

$percent_pps_estimated_change = $percent_pps_estimate - $percent_pps;

$percent_pps_print = prettyProportion($percent_pps);
$percent_pps_estimate_print = prettyProportion($percent_pps_estimate);
$percent_pps_estimate_change_print = ($percent_pps_estimated_change>0?"+":"").prettyProportion($percent_pps_estimated_change);

$savedbal = $bal;
$bal = prettySatoshis($bal);

$titleprepend = "($bal) $givenuser - ";
print_stats_top();

$nickname = get_nickname($link,$user_id);

if (($nickname != "") && ($nickname != $givenuser)) {
	print "<H2><I>$nickname</I> <small> - $givenuser</small></H2>";
} else {
	print "<h2>$givenuser</h2>";
}


print "<div id=\"userstatsmain\">";
print "<TABLE class=\"userstatsbalance\">";
print "<THEAD><TR><TH></TH><TH>未支付的余额</TH><TH><A HREF=\"http://eligius.st/wiki/index.php/Capped_PPS_with_Recent_Backpay\">股份报酬</A></TH></TR></THEAD>";
print "<TR class=\"userstatsodd\"><TD>As of last block: </TD><TD style=\"text-align: right;\">$unpaid_balance_print</TD><TD style=\"text-align: right; font-size: 80%;\">$percent_pps_print</TD></TR>";
print "<TR class=\"userstatseven\"><TD>Estimated Change: </TD><TD style=\"text-align: right;\">$estimated_change_print</TD><TD style=\"text-align: right; font-size: 80%;\">$percent_pps_estimate_change_print</TD></TR>";
print "<TR class=\"userstatsodd\"><TD>Estimated Total: </TD><TD style=\"text-align: right;\">$estimated_balance_print</TD><TD style=\"text-align: right; font-size: 80%;\">$percent_pps_estimate_print</TD></TR>";
print "</TABLE>";

$query_hash = hash("sha256", "userstats.php hashrate table for $givenuser with id $user_id");
$hashratetable = get_stats_cache($link, 11, $query_hash);
if ($hashratetable != "") {
	print $hashratetable;
	$u16avghash = get_stats_cache($link, 111, $query_hash);
} else {

	$hashrate_info = get_hashrate_stats($link, $givenuser, $user_id);

	$pdata = "<TABLE class=\"userstatshashrate\">";
	$pdata .= "<THEAD><TR><TH WIDTH=\"34%\"></TH><TH WIDTH=\"33%\">算力平均值</TH><TH WIDTH=\"33%\"><span title=\"Weighted 股份是矿池接受的股份数量 乘以 矿工提供的困难度, 基本上是相当于困难度为1的时候, 矿机提交给矿池的股份数量.\" style=\"border-bottom: 1px dashed #888888\">Weighted 股份</span></TH></TR></THEAD>";

	$oev = "even";

	foreach ($hashrate_info["intervals"] as $interval)
	{
		$hashrate_info_for_interval = $hashrate_info[$interval];

		$interval_name = $hashrate_info_for_interval["interval_name"];
		$hashrate = $hashrate_info_for_interval["hashrate"];
		$shares = $hashrate_info_for_interval["shares"];

		$pdata .= "<TR class=\"userstats$oev\"><TD>$interval_name</TD><TD style=\"text-align: right;\">" . prettyHashrate($hashrate) . "</TD><TD style=\"text-align: right;\">" . $shares . "</TD></TR>";

		$oev = $oev=="even"?$oev="odd":$oev="even";
	}

	$pdata .= "</TABLE>";

	print $pdata;

	$u16avghash = $hashrate_info[10800]["hashrate"];

	set_stats_cache($link, 11, $query_hash, $pdata, 30);
	set_stats_cache($link, 111, $query_hash, $u16avghash, 30);
}


if (isset($_GET["wizdebug"])) {
# Reject data
$wherein = get_wherein_list_from_worker_data($worker_data);
$sql = "select reason,count(*) as reject_count from public.shares where server=$serverid and user_id in $wherein and our_result!=true and time > NOW()-'675 seconds'::interval group by reason order by reject_count;";
$query_hash = hash("sha256", $sql);
$rejecttable = get_stats_cache($link, 10, $query_hash);
if ($rejecttable != "") {
	print $rejecttable;
} else {

	$result = pg_exec($link, $sql);
	$numrows = pg_numrows($result);
	$pdata = "<TABLE class=\"userstatsrejects\" id=\"rejectdata\">";
	$pdata .= "<THEAD><TR><TH STYLE=\"font-size: 70%;\" id=\"expandarea\"></TH><TH><SPAN title=\"Rejected share counts here are absolute counts and are not weighted.\" style=\"border-bottom: 1px dashed #888888\">拒绝的股份</span></TH></TR></THEAD>";
	if ($numrows) {
		$t = 0;
		$rejectdetails = "";
		$toggles = "";
		$oev = "odd";
		for($ri = 0; $ri < $numrows; $ri++) {
			$row = pg_fetch_array($result, $ri);
			$count = $row['reject_count'];
			$t += $count;
			$reason = prettyInvalidReason($row['reason']);
			$rejectdetails .= "<TR class=\"userstats$oev\" id=\"rejectitem$ri\"><TD><FONT style=\"border-bottom: 1px dashed #999;\">$reason</FONT></TD><TD class=\"rtnumbers\">$count</TD></TR>";
			$toggles .= "\$('#rejectitem$ri').toggle();\n";
			$oev = $oev=="even"?$oev="odd":$oev="even";
		}
		$pdata .= "<TR class=\"userstatseven\"><TD>675秒总计</TD><TD class=\"rtnumbers\">$t</TD></TR>";
		$pdata .= $rejectdetails;
		$pdata .= "</TABLE>";
		$pdata .= "<script language=\"javascript\">\n<!--\n";
		$pdata .= "\$(document).ready(function() {
				\$('#expandarea').click(function(){
					$toggles
					if (!\$('#rejectitem0').is(':hidden')) {
						\$('#expandarea').text('(Collapse Details)');
					} else {
						\$('#expandarea').text('(Expand Details)');
					}
					return false;
				});
				\$('#expandarea').css('cursor', 'pointer').click();;
			});\n";
		$pdata .= "\n--></script>\n";
	} else {
		$pdata .= "<TR class=\"userstatseven\"><TD>675秒总计</TD><TD class=\"rtnumbers\">0</TD></TR>";
		$pdata .= "</TABLE>";
	}
	print $pdata;
	set_stats_cache($link, 10, $query_hash, $pdata, 300);
	$sql = "select pg_advisory_unlock($ulockid) as l";
	$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
}

}
print "<BR><BR>";


if (isset($_GET["timemachine"])) {
	$secondsback = 5184000;
} else {
	$secondsback = 604800;
}


if (count($worker_data) > 1) {

	$query_hash = hash("sha256", "$user_id $givenuser - worker-data");
	$workerdatatable = get_stats_cache($link, 187, $query_hash);
	if ($workerdatatable != "") {
		print $workerdatatable;
	} else {
		$wherein = get_wherein_list_from_worker_data($worker_data);

		# get hashrates
		$sql = "select (date_part('epoch', (select time from $psqlschema.stats_shareagg where server=$serverid group by server,time order by time desc limit 1)-'12 hours'::interval)::integer / 675::integer) * 675::integer as oldest_time";
		$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
		$oldest_time = $row["oldest_time"];
		$t3 = $oldest_time + (9*3600);
		$t225 = $oldest_time + (12*3600) - (22.5*60);

		$sql = "select user_id,date_part('epoch', time) as ctime,accepted_shares, rejected_shares from $psqlschema.stats_shareagg where server=$serverid and user_id in $wherein and time > to_timestamp($oldest_time) order by time desc";
		$result = pg_exec($link, $sql);
		$numrows = pg_numrows($result);
		$wstat = array();
		for ($i=0;$i<$numrows;$i++) {
			$row = pg_fetch_array($result, $i);
			$wid = $row["user_id"];
			$t = $row["ctime"];
			$as = $row["accepted_shares"];
			$rs = $row["rejected_shares"];
			if (!isset($wstat[$wid])) {
				$wstat[$wid] = array();
				for($x=0;$x<3;$x++) {
					$wstat[$wid][$x] = array();
					$wstat[$wid][$x][1] = 0;
					$wstat[$wid][$x][2] = 0;
				}
			}

			$wstat[$wid][0][1] += $as;
			$wstat[$wid][0][2] += $rs;
			if ($t > $t3) {
				$wstat[$wid][1][1] += $as;
				$wstat[$wid][1][2] += $rs;
				if ($t > $t225) {
					$wstat[$wid][2][1] += $as;
					$wstat[$wid][2][2] += $rs;
				}
			}
		}

		$idleworkers = 0;
		$idlelist = "";
		asort($worker_data,SORT_FLAG_CASE|SORT_NATURAL);
		$table = "";
		$oev = "odd";
		foreach ($worker_data as $wid => $wname) {
			$wname = str_replace(",", ".", $wname);
			$wname = str_replace(" ", "_", $wname);
			if (isset($wstat[$wid])) {
				$wname = htmlspecialchars($wname);
				$table .= "<TR class=\"userstats$oev\"><TD><b>$wname</b></TD><TD></TD><TD></TD></TR>\n";
				if (isset($wstat[$wid][0])) {
					$table .= "<TR class=\"userstats$oev\" style=\"text-align: right;\"><TD><I>12 小时</I></TD><TD>".prettyHashrate(($wstat[$wid][0][1]*4294967296*256)/43200)."</TD><TD>{$wstat[$wid][0][1]}</TD></TR>";
					if ((isset($wstat[$wid][1])) && ($wstat[$wid][1][1])) {
						$table .= "<TR class=\"userstats$oev\" style=\"text-align: right;\"><TD><I>3 小时</I></TD><TD>".prettyHashrate(($wstat[$wid][1][1]*4294967296*256)/10800)."</TD><TD>{$wstat[$wid][1][1]}</TD></TR>";
						if ((isset($wstat[$wid][2])) && ($wstat[$wid][2][1])) {
							$table .= "<TR class=\"userstats$oev\" style=\"text-align: right;\"><TD><I>22.5 分钟</I></TD><TD>".prettyHashrate(($wstat[$wid][2][1]*4294967296*256)/1350)."</TD><TD>{$wstat[$wid][2][1]}</TD></TR>";
						}
					}
				}
				$oev = $oev=="even"?$oev="odd":$oev="even";
			} else {
				$idleworkers++;
				$idlelist .= " $wname,";
			}
		}
		$pdata = "";
		if ($table != "") {

			$pdata .= "<INPUT TYPE=\"BUTTON\" onClick=\"\$('#workeritems').toggle();\" VALUE=\"切换矿工明细\"><BR><BR>";
			$pdata .= "<TABLE id=\"workeritems\" class=\"userstatsworkers\"><THEAD><TH  style=\"text-align: left;\">Worker Name</TH><TH  style=\"text-align: right;\">算力</TH><TH  style=\"text-align: right;\">已接受的股份数</TH></THEAD>$table";
			if ($idleworkers) {
				$idlelist = substr($idlelist,1,strlen($idlelist)-2).".";
				$pdata .= "<TR BGCOLOR=\"#CCCCCC\"><TD COLSPAN=\"3\" style=\"white-space: normal; word-wrap:break-word;\"><B>Note</B>: There ".($idleworkers==1?"is":"are")." $idleworkers idle or no longer used worker".($idleworkers==1?"":"s")." that ".($idleworkers==1?"is":"are")." not shown in the table:<BR>$idlelist</TD></TR>";
			}
			$pdata .= "</TABLE>";
			$pdata .= "<script language=\"javascript\">\n<!--\n";
			$pdata .= "\$(document).ready(\$('#workeritems').toggle());\n";
			$pdata .= "\n--></script>\n";
		}

		print $pdata;
		# save cache
		set_stats_cache($link, 187, $query_hash, $pdata, 90);
	}

}

print "<div id=\"ugraphdiv2\" style=\"width:750px; height:375px;\"></div>";
print "<INPUT TYPE=\"BUTTON\" onClick=\"showmax();\" VALUE=\"切换到最大报酬图\"><BR>";
print "<div id=\"ugraphdiv3\" style=\"width:750px; height:375px;\"></div>";

#if (!isset($_GET["timemachine"])) {
#	print "<A HREF=\"?timemachine=1\">(Click for up to 60 days of hashrate/balance data)</A><BR>";
#}



# script for dygraphs
print "<script type=\"text/javascript\">

	var blockUpdateA = 0;
	var blockUpdateB = 0;

	g2 = new Dygraph(document.getElementById(\"ugraphdiv2\"),\"$givenuser?cmd=hashgraph&start=0&back=$secondsback&res=1\",{
		strokeWidth: 1.5,
		fillGraph: true,
		'675 second': { color: '#408000' },
		'3 hour': { fillGraph: false, strokeWidth: 2.25, color: '#400080' },
		'12 hour': { fillGraph: false, strokeWidth: 2.25, color: '#008080' },
		labelsDivStyles: { border: '1px solid black' },
		title: '算力图 ($givenuser)',
		xlabel: '时间',
		ylabel: 'Mh/sec',
		animatedZooms: true,
		drawCallback: function(dg, is_initial) {
		if (is_initial) {
				var rangeA = g2.xAxisRange();
				g3.updateOptions( { dateWindow: rangeA } );
			} else {
				if (!blockUpdateA) {
					blockUpdateB = 1;
					var rangeA = g2.xAxisRange();
					g3.updateOptions( { dateWindow: rangeA } );
					blockUpdateB = 0;
				}
			}
		}

	});

	var mrindex = 0;
	var mrhidden = 1;
	g3 = new Dygraph(
	document.getElementById(\"ugraphdiv3\"),\"$givenuser?cmd=balancegraph&start=0&back=$secondsback&res=1\",{
		strokeWidth: 2.25,
		fillGraph: true,
		labelsDivStyles: { border: '1px solid black' },
		title: '余额图 ($givenuser)',
		xlabel: '时间',
		ylabel: 'BTC',
		animatedZooms: true,
		drawCallback: function(dg, is_initial) {
			if (is_initial) {
				mrindex = dg.indexFromSetName(\"maximum reward\") - 1;
				dg.setVisibility(mrindex, 0);
				var rangeB = g3.xAxisRange();
				g2.updateOptions( { dateWindow: rangeB } );
			} else {
				if (!blockUpdateB) {
					blockUpdateA = 1;
					var rangeB = g3.xAxisRange();
					g2.updateOptions( { dateWindow: rangeB } );
					blockUpdateA = 0;
				}
			}
		}
	});

	var showmax = function() {
		if (mrhidden) {
			g3.setVisibility(mrindex, 1);
			mrhidden = 0;
		} else {
			g3.setVisibility(mrindex, 0);
			mrhidden = 1;
		}
	}
</script>\n";

print "</div>";

# right side
print "<div id=\"userstatsright\">";


print "<B>最新支付</B>";

if ($everpaid > 0) {

	$query_hash = hash("sha256", "userstats.php latest payouts for $givenuser with id $user_id and latest everpaid of $everpaid v2");
	$latestpayouts = get_stats_cache($link, 12, $query_hash);
	if ($latestpayouts != "") {
		print $latestpayouts;
	} else {

		$sql = "select stats_transactions.time as time, stats_payouts.amount as amount, stats_transactions.hash as txhash, stats_transactions.coinbase as coinbase, stats_blocks.blockhash as blockhash from $psqlschema.stats_payouts left join $psqlschema.stats_transactions on stats_transactions.id=transaction_id left join $psqlschema.stats_blocks on block_id=stats_blocks.id where stats_payouts.user_id=$user_id order by time desc limit 10;";
		$result = pg_exec($link, $sql);
		$numrows = pg_numrows($result);
		$xdata = "";

		for($i=0;$i<$numrows;$i++) {
			$row = pg_fetch_array($result, $i);
			if ($row["coinbase"] == "t") {
				$tid = "<A HREF=\"../blockinfo.php/{$row["blockhash"]}\">G</A>";
			} else {
				$tid = "<A HREF=\"http://blockchain.info/search?search={$row["txhash"]}\">S</A>";
			}

			$xdata .= "<TR><TD>{$row["time"]} ($tid)</TD><TD>".prettySatoshis($row["amount"])."</TD></TR>";
		}

		if ($xdata != "") {
			$pdata = "<table id=\"paymentlist\"><THEAD><TR><TH>Date (<SPAN title=\"G = Payout from coinbase/generation; S = Payout from normal send/sendmany\" style=\"border-bottom: 1px dashed #888888\">Type</SPAN>)</TH><TH>Amount</TH></TR></THEAD>$xdata</table>";
		} else {
			$pdata = "<BR>没有可用的最新支付数据.<BR>";
		}
		print $pdata;
		# cache this data for 24 hours. if the user is paid, the hash will change and invalidate this forcing a rebuild. genius!
		set_stats_cache($link, 12, $query_hash, $pdata, 600); # just cache for 10 minutes so that updates dont cause caching of invalid data
	}
} else {
	print "<BR>没有可用数据.<BR>";
}

print "历史全部支付总量: ".prettySatoshis($everpaid);
if ($donated > 0) {
	print "<BR><FONT SIZE=\"-1\">Total donated Eligius: ".prettySatoshis($donated)." - <I>Thanks!</I></FONT>";
}

print "<BR><BR><HR>";


if ($savedbal) {

	print "<BR><B>在支付队列中预计所处位置</B><BR>";
	if ($payoutqueue = apc_fetch('wizstats_payoutqueuetxt')) {
	} else {
		$payoutqueue = file_get_contents("$pooldatadir/$serverid/payout_queue.txt");
		apc_store('wizstats_payoutqueuetxt', $payoutqueue, 600);
	}
	print "<span style=\"font-size: 0.8em\">";
	if ((strpos($payoutqueue,$givenuser) == false) && (substr($payoutqueue,0,strlen($givenuser)) != $givenuser)) {

		$options = get_options($link, $user_id);
		if (isset($options["Minimum_Payout_BTC"])) {
			$minpay = $options["Minimum_Payout_BTC"]*100000000;
		} else {
			$minpay = 4194304;
		}
		if ($minpay < 1048576) { $minpay = 1048576; }
		if ($minpay > 2147483648) { $minpay = 2147483648; }

		$diff = $minpay - $savedbal;


		if ($diff < 0) { $diff = 0; }
		print "大约还需要 ".prettySatoshis($diff)." 就进入 <A HREF=\"".$GLOBALS["urlprefix"]."payoutqueue.php#$givenuser\">支付队列</a>.";

		if (($u16avghash == 0) && (isset($balupdate))) {
			$timetoqueue = (3600*24*7) - (time() - $balupdate);
			print " If you remain inactive";
			if ((isset($lec)) && ($lec > 0)) {
				print ", and the pool does not pay towards any of your shelved shares,";
			}

			if ($savedbal >= 131072) {
				if ($savedbal >= 1048576) {
					print " then you will enter the payout queue, due to inactivity, in approximately ".prettyDuration($timetoqueue).".";
				} else {
					print " then you will be eligible for a payout of your balance (which is less than the automatic payout threshhold of ".prettySatoshis(1048576).") in a manual payout no sooner than ".prettyDuration($timetoqueue)." from now.";
				}
			} else {
				print " then your less than ".prettySatoshis(131072)." balance will remain unpaid and donated to the pool in approximately ".prettyDuration((3600*24*60) - (time() - $balupdate)).".  If you are concerned about this small balance you should mine until your balance is greater than ".prettySatoshis(131072).".";
			}
		}

		if ($u16avghash > 0) {
			$sql = "select id,(pow(10,((29-$psqlschema.hex_to_int(substr(encode(solution,'hex'),145,2)))::double precision*2.4082399653118495617099111577959::double precision)+log(  (65535::double precision /  $psqlschema.hex_to_int(substr(encode(solution,'hex'),147,6)))::double precision   )::double precision))::double precision as network_difficulty from shares where server=$serverid and our_result=true order by id desc limit 1;";
					$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
					$netdiff = $row["network_difficulty"];

			$shares = $diff / (2500000000/$netdiff);
			$stime = $shares / ($u16avghash / 4294967296);
			$netdiff = round($netdiff,2);
			print " 以你保持目前3小时算力均值来看, 大约需要在 ".prettyDuration($stime). " 之后, 在当前比特币网络的困难度( ".number_format($netdiff,2).") 的条件下.";
		}
		if ($minpay != 4194304) { print "<BR><BR>Note: Your minimum payout was customized to ".prettySatoshis($minpay)." under 'My $poolname'."; }

	} else {
		# add up balances and see where we end up.
		$tb = 0; $bc = 0; $overflow = 0;
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $payoutqueue) as $pquser){
			if ($pquser != $givenuser) {
				$tb += $balanacesjsondec[$pquser]["balance"];
				while ($tb > 2500000000) {
					$tb = $tb - 2500000000;
					$bc++;
				}
			} else {
				if (($tb+$balanacesjsondec[$pquser]["balance"]) > 2500000000) {
					$overflow = 1;
				}
				break;
			}
		}
		if (($bc == 0) && (!$overflow)) {
			$aheadtext = "Less than 25 BTC ahead in queue";
		} else {
			if ($overflow) {
				$aheadtext = prettySatoshis($tb+(2500000000*($bc)))." ".(($tb+(2500000000*($bc+1)))==1?"is":"are")." ahead in queue, but our payout is more than the remaining block reward of ".prettySatoshis((2500000000)-$tb);
				$bc++;
			} else {
				$aheadtext = prettySatoshis($tb+(2500000000*($bc)))." ".(($tb+(2500000000*($bc+1)))==1?"is":"are")." ahead in queue";
			}
		}
		if ($bc == 0) {
			$delay = "in our next block";
		} else {
			$delay = "after a $bc block delay";
		}
		print $aheadtext.", putting this user's payout $delay.<BR><SMALL style=\"font-size: 70%\"><I>Note: This is constantly changing. See <A HREF=\"".$GLOBALS["urlprefix"]."payoutqueue.php#$givenuser\">the payout queue</A>.</I></SMALL>";
	}
	print "</span>";
	print "<BR><BR><HR>";
}


if ($u16avghash > 0) {
	if (!isset($netdiff)) {
		$sql = "select id,(pow(10,((29-$psqlschema.hex_to_int(substr(encode(solution,'hex'),145,2)))::double precision*2.4082399653118495617099111577959::double precision)+log(  (65535::double precision /  $psqlschema.hex_to_int(substr(encode(solution,'hex'),147,6)))::double precision   )::double precision))::double precision as network_difficulty from shares where server=$serverid and our_result=true order by id desc limit 1;";
		$result = pg_exec($link, $sql); $row = pg_fetch_array($result, 0);
		$netdiff = $row["network_difficulty"];
	}
	$satoshiperday = round((($u16avghash*86400) / 4294967296)*(2500000000/$netdiff),0);
	$netdiff = round($netdiff,2);
	print "<BR><B>预计收入</B><BR>";
	print "<span style=\"font-size: 0.8em\">";
	print "在当前比特币网络挖矿困难度( ".number_format($netdiff,2)." )条件下, 以你目前保持的 3小时平均算力 ".prettyHashrate($u16avghash)." 来计算, 你的最大潜在收入是  ".prettySatoshis($satoshiperday)." / 每天.\n";
	print "</span>";
	print "<BR><BR><HR>";
}

print "</div>";

print "<BR><SMALL>(本页面数据是定期更新的缓存数据, 以30秒钟的间隔更新一次你的短期算力数值, 余额, 和被拒绝的股份数目; 以 675秒的时间间隔更新图表, 长期算力数值, 和其他数据.</SMALL><BR>";
print_stats_bottom();

?>
