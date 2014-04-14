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

if (isset($_GET["storecookie"])) { setcookie("u", $_GET["u"], time()+86400*365); $u = $_GET["u"];}
else { if (isset($_COOKIE["u"])) { setcookie("u", $_COOKIE["u"], time()+86400*365); $u = $_COOKIE["u"]; } }

if (!isset($link)) { $link = pg_pconnect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost"); }

$titleprepend = "我的状态 - ";

if ((isset($_GET["cmd"])) && (strlen($_GET["cmd"]) > 0)) {
	$cmd = $_GET["cmd"];
} else {
	$cmd = "menu";
}

if ($cmd == "logout") {
	setcookie ("u", "", time() - 3600);
	unset($_COOKIE["u"]);
	unset($_GET["u"]);
}


print_stats_top();

print "<H2>矿池控制面板</H2><HR><BR>";

if ($cmd == "logout") {
	print "注销成功.<BR>";
}

?>

<?php
$nouser = 0;
$reason = "";
if (((!isset($_COOKIE["u"])) && (!isset($_GET["u"]))) || ( (isset($_GET["u"])) && (strlen($_GET["u"]) == 0) ) ) {
	$nouser = 1;
} else {
	$u = "";
	if (isset($_GET["u"])) { $u = $_GET["u"]; } else { if (isset($_COOKIE["u"])) { $u = $_COOKIE["u"]; } }

	$user_id = get_user_id_from_address($link, $u);

	if (!$user_id) {
		$nouser = 1;
		$reason = "$u 没有在数据库中找到! <BR><BR>";
	}
}


if ($cmd == "switchaddr") {
	$nouser = 1;
}


if ($nouser == 1) {

	if ($cmd != "switchaddr") {
	?>

	<H2>没有地址发送到 <I>矿工状态</I> 页面</H2><BR>
	<?php
	}
	?>
	<?php echo $reason; ?>
	想查看 <I>矿工状态</I> 你必须指定你在本矿池挖矿用的比特币地址.<BR>
	<BR>

	<FORM METHOD="GET">挖矿用的比特币地址: <INPUT TYPE="text" name="u" size=40 maxlength=512><BR>
	<input type="checkbox" name="storecookie" CHECKED> 保存挖矿用的比特币地址在浏览器的Cookie里吗? (保存请打勾)<BR>
	<input type="submit" value="去查看我的状态!">
	</FORM>

	<?php
	print_stats_bottom(); exit();
}


# ok, valid user in $u
print "欢迎你, $u!<BR>";


if ($cmd) {

	if ($cmd == "menu") {
		print "<A HREF=\"userstats.php/$u\">我的矿工状态统计页面</A><BR>\n";
		print "<A HREF=\"?cmd=options\">选项配置</A><BR>\n";
		print "<A HREF=\"?cmd=switchaddr\">更换挖矿比特币地址</A><BR>\n";
	}

	if ($cmd == "submitsig") {
		$sig = $_GET["sig"];
		$msg = $_GET["msg"];

		# msg format
		# My Eligius - 2013-03-27 00:11:22 - minimumpayout=1.23456789&nickname=wizkid057&generationpayout=1

		$msghead = "My ".$poolname." - ";

		$validate = 1;

		if (substr($msg,0,strlen($msghead)) != $msghead) {
			print "Invalid Message! ";
			$validate = 0;
		}

		$msgdate = substr($msg,strlen($msghead),19);

		$msgdateunix = strtotime ($msgdate." UTC");
		if ($msgdateunix > time()+86400) {
			print "Invalid timestamp! ";
			$validate = 0;
		}
		if ($msgdateunix < time()-86400) {
			print "Invalid timestamp! ";
			$validate = 0;
		}

		$msgvars = substr($msg,strlen($msghead)+26,10000);
		$msgvars = str_replace(" ","&",$msgvars);
		parse_str($msgvars, $msgvars_array);

		if (count($msgvars_array) == 0) {
			print "No variables set! Set at least one variable! ";
			$validate = 0;
		}

		$donatesum = $msgvars_array["Donate_Pool"]+$msgvars_array["Donate_Stats"]+$msgvars_array["Donate_Hosting"];

		if ($donatesum > 100) {
			$validate = 0;
			print "Donations total more than 100%! (While we appreciate the thought, this is invalid...) ";
		}
		if ($donatesum < 0) {
			$validate = 0;
			print "Donations total less than 0%! (Nice try...) ";
		}
		if ($msgvars_array["Donate_Pool"] < 0) {
			$validate = 0;
			print "Donations to pool invalid. ";
		}
		if ($msgvars_array["Donate_Stats"] < 0) {
			$validate = 0;
			print "Donations to stats invalid. ";
		}
		if ($msgvars_array["Donate_Hosting"] < 0) {
			$validate = 0;
			print "Donations to hosting invalid. ";
		}

		if ($donatesum > 100) { $donatesum = 100; }
		if ($donatesum < 0) { $donatesum = 0; }
		$donatesum = "<I>$donatesum%</I>";

		if (($validate) && isset($msgvars_array["Minimum_Work_Diff"]) && ( (filter_var($msgvars_array["Minimum_Work_Diff"], FILTER_VALIDATE_INT) === FALSE) ||
			($msgvars_array["Minimum_Work_Diff"] < 1) ||
			($msgvars_array["Minimum_Work_Diff"] > 65536) ||
			(($msgvars_array["Minimum_Work_Diff"] & ($msgvars_array["Minimum_Work_Diff"]-1)) != 0))) {
			$validate = 0;
			print "Invalid minimum difficulty! (Valid values are powers of two: 1,2,4,8,16,32,etc) ";
		}

		if ((isset($msgvars_array["Minimum_Payout_BTC"])) && ($msgvars_array["Minimum_Payout_BTC"] < 0.01048576)) {
			$validate = 0;
			print "Invalid minimum payout. (Must be 10 TBC (0.01048576 BTC) or greater)";
		}


		if ($validate == 1) {

			$sql = "select date_part('epoch', time)::integer as etime from $psqlschema.stats_mystats where server=$serverid and user_id=$user_id order by id desc limit 1";
			$result = pg_exec($link, $sql);
			$numrows = pg_numrows($result);
			if ($numrows > 0) {
				$row = pg_fetch_array($result, 0);
				$etime = $row["etime"];
				if (($msgdateunix - $etime) < 5) {
					$validate = 0;
					print "Newly signed options must have a timestamp at least 5 seconds newer than previously signed options. ";
				}
			}

		}

		if ($validate == 1) {

			$sigok = 0;
			if ((strlen($sig) > 35) && (strlen($msg) > 0)) {
				$sigok = verifymessage($u, $sig, $msg);
			}
			if ($sigok) {
				print "Signature passes!";
				$signedoptions = $msg;
				$signature = $sig;
				$sql = pg_prepare($link, "mystats_insert", "insert into $psqlschema.stats_mystats (server, user_id, time, signed_options, signature) VALUES ($serverid, $user_id, to_timestamp($msgdateunix), $1, $2)");
				$result = pg_execute($link, "mystats_insert", array($signedoptions, $signature));
				#print "SQL: $sql";
			}
			else {
				print "Signature fails!";
			}
		}


		$cmd = "options"; # fall through

	}

	if ($cmd == "options") {

		if (!isset($msgvars_array)) {
			$sql = "select * from $psqlschema.stats_mystats where server=$serverid and user_id=$user_id order by id desc limit 1";
			$result = pg_exec($link, $sql);
			$numrows = pg_numrows($result);
			if ($numrows > 0) {
				$row = pg_fetch_array($result, 0);
				$msg = $row["signed_options"];
				$msghead = "My ".$poolname." - ";
				$msgdate = substr($msg,strlen($msghead),19);
				$msgdateunix = strtotime ($msgdate." UTC");
				$msgvars = substr($msg,strlen($msghead)+26,10000);
				$msgvars = str_replace(" ","&",$msgvars);
				parse_str($msgvars, $msgvars_array);
				$sig = $row["signature"];
			} else {
				$msg = '';
				$sig = '';
				$msgvars_array=array(
					'Nickname' => '',
					'Minimum_Payout_BTC' => '0.001',
					'Donate_Pool' => '',
					'Donate_Stats' => '',
					'Donate_Hosting' => '',
					'NMC_Address' => '',
				);
			}
			$donatesum = 0;
		} else {
			$msg = $_GET["msg"];
			$sig = $_GET["sig"];
		}


		?>

		<SCRIPT language="javascript">
		<!--

			function js_yyyy_mm_dd_hh_mm_ss () {
				now = new Date();
				now = new Date(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(),  now.getUTCHours(), now.getUTCMinutes(), now.getUTCSeconds());
				year = "" + now.getFullYear();
				month = "" + (now.getMonth() + 1); if (month.length == 1) { month = "0" + month; }
				day = "" + now.getDate(); if (day.length == 1) { day = "0" + day; }
				hour = "" + now.getHours(); if (hour.length == 1) { hour = "0" + hour; }
				minute = "" + now.getMinutes(); if (minute.length == 1) { minute = "0" + minute; }
				second = "" + now.getSeconds(); if (second.length == 1) { second = "0" + second; }
				return year + "-" + month + "-" + day + " " + hour + ":" + minute + ":" + second + " UTC";
			}

			function updateOptionsMessage() {
				//alert(document.optionsform.nickname.value);
				var str = "My <?php echo $poolname; ?> - " + js_yyyy_mm_dd_hh_mm_ss() + " - ";
				//if (document.optionsform.nickname.value.length > 0) { str = str + "nickname=" + encodeURIComponent(document.optionsform.nickname.value) + "&"; }
				//if (document.optionsform.minimumpayout.value.length > 0) { str = str + "minimumpayout=" + encodeURIComponent(document.optionsform.minimumpayout.value) + "&"; }
				var elem = document.optionsform.elements;
				for(var i = 0; i < elem.length; i++) {
					if (elem[i].value.length > 0) { str = str + elem[i].name + "=" + encodeURIComponent(elem[i].value) + " "; }
				}

				str = str.substring(0, str.length - 1);
				document.sigform.msg.value = str + "";
				document.getElementById("msgdiv").innerHTML= "<I>" + str + "</I>";
				var d1 = parseFloat(document.optionsform.Donate_Pool.value);
				var d2 = parseFloat(document.optionsform.Donate_Stats.value);
				var d3 = parseFloat(document.optionsform.Donate_Hosting.value)
				var donatetotal = 0;
				if (d1 > 0) { donatetotal += d1; }
				if (d2 > 0) { donatetotal += d2; }
				if (d3 > 0) { donatetotal += d3; }
				if (donatetotal > 100) { donatetotal = 100; }
				document.getElementById("totaldonate").innerHTML = "<I>" + donatetotal + "%</I>";
			}

		-->
		</SCRIPT>

		<BR><B>你必须使用一个支持给挖矿地址使用标准签名的比特币客户端来签名你的各个选项(如果你不能确定, 仅仅只想看看统计数据, 建议不要修改这里的配置)</b><BR>
		比特币客户端 Bitcoin-qt 使用指南这里有一个: <A HREF="http://eligius.st/~capa66/utl/my_eligius/index.html">http://eligius.st/~capa66/utl/my_eligius/index.html</A><BR>
		<BR><H3><U>选项表单</U></H3><SMALL>所有的填写项目都是可选的. 如果不填写, 将会使用矿池默认设置.</SMALL><BR><BR>
		<FORM name="optionsform" onsubmit="return false;">
		<TABLE BORDER=0>
		<TR><TD><B>昵称</B>:</TD><TD><INPUT TYPE="TEXT" name="Nickname" size=32 maxlength=32 value="<?php echo htmlspecialchars($msgvars_array["Nickname"]); ?>" onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()"> (默认值: <?php echo $u; ?>)</TD></TR>
		<TR><TD><B>最小支付金额</B>:</TD><TD><INPUT TYPE="TEXT" name="Minimum_Payout_BTC" size=12 value="<?php echo  htmlspecialchars($msgvars_array["Minimum_Payout_BTC"]); ?>" maxlength=32 onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()"> BTC (默认值: 0.04194304, 最小值: 0.01048576 [10 TBC])</TD></TR>
		<TR><TD><B>可选赞助 %s</B>:</TD><TD></TD></TR>
		<TR><TD style="text-align:right;"><SMALL>赞助小费给矿池管理维护人员:</SMALL></TD><TD><INPUT TYPE="TEXT" name="Donate_Pool" size=6 value="<?php echo htmlspecialchars($msgvars_array["Donate_Pool"]); ?>" maxlength=32 onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()">% (默认值: 0.00%)</TD></TR>
		<TR><TD style="text-align:right;"><SMALL>赞助小费给矿池统计状态开发人员:</SMALL></TD><TD><INPUT TYPE="TEXT" name="Donate_Stats" size=6 value="<?php echo htmlspecialchars($msgvars_array["Donate_Stats"]); ?>" maxlength=32 onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()">% (默认值: 0.00%)</TD></TR>
		<TR><TD style="text-align:right;"><SMALL>赞助小费给矿池服务器费用:</SMALL></TD><TD><INPUT TYPE="TEXT" name="Donate_Hosting" size=6 value="<?php echo htmlspecialchars($msgvars_array["Donate_Hosting"]); ?>" maxlength=32 onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()">% (默认值: 0.00%)</TD></TR>
		<TR><TD style="text-align:right;"><SMALL><B>总计</B></SMALL></TD><TD id="totaldonate"><?php echo $donatesum; ?></TD></TR>
		<TR><TD><B>NMC 合并挖矿地址</B>:</TD><TD><INPUT TYPE="TEXT" name="NMC_Address" size=35 maxlength=35 value="<?php echo htmlspecialchars($msgvars_array["NMC_Address"]); ?>" onChange="updateOptionsMessage()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange()"> (默认值: 空白)</TD></TR>
		</TABLE></FORM>

		<HR><BR>

		<FORM METHOD="GET" name="sigform">
		<B> <?php echo $u; ?> 需要签名的消息:<BR></B>
		<div id="msgdiv"><?php echo htmlspecialchars($msg); ?></div><BR>
		<input type="text" name="msg" size="128" value="<?php echo htmlspecialchars($msg); ?>"><BR>
		<B>签名</B>:<BR><INPUT TYPE="text" size="128" name="sig" value="<?php echo htmlspecialchars($sig); ?>"><BR>
		<input type="submit" value="Submit Changes!">
		<input type="hidden" name="cmd" value="submitsig">
		<input type="hidden" name="u" value="<?php echo $u; ?>">
		</FORM>

		<BR><H3><U><FONT COLOR="RED">警告 - 提交表单前必须阅读</FONT></U></H3>
		<B>快速条款: 通过提交有效签名给你的比特币地址签名, 你已经默认以下条款:
		<BR>通过有效签名对选项做的修改将会立即保存在矿池服务器上.<BR>
		如果需要撤销修改, 你需要提交新的修改内容并使用新的签名对新内容进行签名.<BR>
		矿池将 *不会* 对经过有效签名确认提交的任何错误的/不需要的内容负责.<BR></B>
		<BR>一些修改将会需要一个小时左右生效.
		所有的修改将会在最慢生效的内容起作用后生效.
		No changes are retroactive and all only apply from the time they are updated.  The pool reserves the right to remove any nickname it feels is inappropriate, at it's sole discretion.  This page is part of the public pool stats and anyone is able to view the settings here, along with the most recent signed message and signature for open verification.  However, settings can not be changed without a new valid signature.<BR><BR>
		其他特定说明:<BR>
		* 昵称 - 请保持清爽, 不要夹杂 URLs, 广告等推广内容. 这个将会显示在你的状态页面, 在地址的下方.<BR><BR>
		* 最小工作困难度 - This option will have the pool use a best-effort attempt at serving work at or above this difficulty to your workers.  Due to the way Stratum handles authentication and work processing, you may still receive some difficulty 1 work at first because work is given before the pool knows who you are.  The pool reserves the right to reset this value to the pool default for any miner at any time at it's sole discretion.<BR><BR>
		* 最小支付金额 - 这个选项并不是及时生效的. 提交修改后24小时内, 你可能仍然在使用修改前的配置内容(或者默认值).<BR><BR>
		* 可选赞助 %s - The various donation &quot;buckets&quot; each have their own bitcoin address which will be paid by the pool based on the standard payout queue.<BR><BR>
		* NMC 合并挖矿地址 - 你对 Namecoin 的挖矿所得(Namecoin)将会支付到这个地址.
		<HR>
		<?php
	}


}



?>

<A HREF="mystats.php?cmd=menu">我的状态菜单</A><BR>
<A HREF="mystats.php?cmd=logout">注销</A><BR>

<?php print_stats_bottom(); ?>

