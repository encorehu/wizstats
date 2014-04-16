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


function print_stats_top() {

	$localtitleprepend = ""; $localtitleappend = ""; $localheadextras = ""; $localbodytags = "";
	if (isset($GLOBALS["titleprepend"])) { $localtitleprepend = $GLOBALS["titleprepend"]; }
	if (isset($GLOBALS["titleappend"])) { $localtitleappend = $GLOBALS["titleappend"]; }
	if (isset($GLOBALS["headextras"])) { $localheadextras = $GLOBALS["headextras"]; }
	if (isset($GLOBALS["bodytags"])) { $localbodytags = $GLOBALS["bodytags"]; }
	if (isset($GLOBALS["ldmain"])) { $ldmain = $GLOBALS["ldmain"]; }


	include("instant_livedata.php");

	$GLOBALS["netdiff"] = $netdiff;
	$GLOBALS["phash"] = $phash;
	$GLOBALS["sharesperunit"] = $sharesperunit;

	$roundduration = format_time($roundduration);
	if($roundshares > 0){
	    $liveluck = round(($netdiff/$roundshares)*100);
	} else {
	    $liveluck = 0;
	}
	if ($liveluck > 9999) { $liveluck = ">9999%"; }

	if (!isset($ldmain)) { $ldmain = ""; }

	$rnd = (rand() * rand() + rand());

print("<HTML>
<HEAD>
<meta http-equiv=\"Content-type\" content=\"text/html; charset=utf-8\" />
<TITLE>".$localtitleprepend.$GLOBALS["poolname"]." 矿池状态统计".$localtitleappend."</TITLE>
<meta http-equiv=\"X-UA-Compatible\" content=\"IE=Edge,chrome=1\">
<!--[if lt IE 9]><script src=\"".$GLOBALS["urlprefix"]."IE9.js\"></script><![endif]-->
<script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."dygraph-combined.js\"></script>
<script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."jquery.js\"></script>
<script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."sortable.js\"></script>
<script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."instantscripts.php/livedata$ldmain.js?rand=$rnd\"></script>
<!--[if IE]><script type=\"text/javascript\" src=\"".$GLOBALS["urlprefix"]."excanvas.js\"></script><![endif]-->
<link rel=\"stylesheet\" type=\"text/css\" href=\"".$GLOBALS["urlprefix"]."stats-style.css\">
".$localheadextras."
</HEAD>
<BODY BGCOLOR=\"#FFFFFF\" TEXT=\"#000000\" LINK=\"#0000FF\" VLINK=\"#0000FF\" ALINK=\"#D3643B\" onLoad=\"initShares();\" ".$localbodytags.">
<div id=\"wrapper\">
<div id=\"Eligius-Title\">
	<H2><A HREF=\"".$GLOBALS["urlprefix"]."\">".$GLOBALS["poolname"]." 矿池状态统计</A></H2><!--[if IE]><BR><![endif]-->
	<h4>请用比特币向此地址捐助矿池统计状态的开发工作:<BR><B>12Xx9</B>WhredZ29o7LUGEdj4J5f4tXwLD9QQ</h4>
</div>
<div id=\"luck\">
<TABLE class=\"lucktable\" width=\"100%\">
<TR>
<TD width=\"30%\" style=\"text-align: left\">算力:</TD><TD width=\"25%\" style=\"text-align: right; border-right:1px dotted #CCCCCC; padding-right: 3px; white-space: nowrap;\" id=\"livehashrate\">$phash</TD>
<TD width=\"25%\" style=\"text-align: left\">本轮时间:</TD><TD width=\"20%\" style=\"text-align: right\" id=\"roundtime\">$roundduration</TD>
</TR>
</TABLE>
</div>
<br>
<br>
<br>
<br>
<div id=\"line\"></div>
<center>
<ul id=\"menu\">
    <li><a href=\"".$GLOBALS["urlprefix"]."\">首页</a></li>
    <li><a href=\"".$GLOBALS["urlprefix"]."mystats.php\">我的状态</a></li>
    <li><a href=\"".$GLOBALS["urlprefix"]."blocks.php\">块</a></li>
    <li><a href=\"".$GLOBALS["urlprefix"]."topcontributors.php\">贡献者</a></li>
</ul>
</center>
<br>
<br>
<br>
<!--[if IE]><H4>当前页面在 <A HREF=\"http://www.google.com/chrome\">Google Chrome</A> 表现最好, IE浏览器不能完整的显示页面.  使用IE浏览器, 你将不会得到最好的体验.</H4><![endif]-->
");

if (apc_fetch('cppsrb_ok') == -1) {
	###print "<TABLE BORDER=1><TR><TD><FONT SIZE=\"+2\" COLOR=\"RED\"><B>AUTO-NOTICE</B>: The CPPSRB reward system appears to be in fail-safe mode.</FONT></TD></TR><TR><TD><FONT COLOR=\"RED\">Some stats are likely not updating as they should right now (128/256 second hash rates, balances, balance graph, payout queue).  These items will correct themselves soon when CPPSRB is out of fail safe mode.  This can take several hours.  <B>No earnings are lost as long as your shares are accepted!</B>  Sorry for the inconvenience!</FONT></TD></TR></TABLE><BR>";
}

}


function print_stats_bottom() {

	$localafterbodyextras = "";
	if (isset($GLOBALS["afterbodyextras"])) { $localafterbodyextras = $GLOBALS["afterbodyextras"]; }

	print("<BR><div id=\"line\"></div>");
	print('&copy;&nbsp;<script>document.write(new Date().getFullYear())</script>&nbsp;'.$GLOBALS["poolname"]);

	print("</BODY>".$localafterbodyextras."</HTML>");

}
