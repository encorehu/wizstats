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

require_once "includes.php";
#$bodytags = "onLoad=\"initShares();\"";
$ldmain = "-main";
print_stats_top();

$announce = file_get_contents("announce.txt");
if (strlen($announce) > 0) {
	print $announce."<BR><BR>";
}

?>


查看独立矿工状态, 使用 <?php echo $urlprefix;?>userstats.php/[你的矿工比特币地址] 页面查看.<BR>
例如, <A HREF="<?php echo $urlprefix;?>userstats.php/1NBXu5bMqPB5AoZNq1ChcktnYaB9u5Xh1a"><?php echo $urlprefix;?>userstats.php/1NBXu5bMqPB5AoZNq1ChcktnYaB9u5Xh1a</A>
<BR><BR>
<BR>
<CENTER><H3>最近的块</H3></CENTER>

<?php
	# Display partial block list on main page
	$blocklimit = 8;
	$subcall = 1;
	include("blocks.php");
?>
<SMALL>(此统计表格在大多数浏览器里都是准实时更新的. Share 统计数量都转换为困难度为1的shares.)</SMALL><BR>
<BR>
<div id="line"></div>
<div id="graphdiv3phr" style="width:100%; height:275px;"></div>
<script type="text/javascript">

      function round(num, places) {
        var shift = Math.pow(10, places);
        return Math.round(num * shift)/shift;
      };

      var suffixes = ['', 'kh', 'Mh', 'Gh', 'Th', 'Ph', 'Eh'];
      function formatValue(v) {
	v = v * 1000000000;
        if (v < 1000) return v;

        var magnitude = Math.floor(String(Math.floor(v)).length / 3) - 1;
        if (magnitude > suffixes.length - 1)
          magnitude = suffixes.length - 1;
        return String(round(v / Math.pow(10, magnitude * 3), 4)) +
          suffixes[magnitude];
      };

  g2 = new Dygraph(
    document.getElementById("graphdiv3phr"),
    "poolhashrategraph.php",
   	{ strokeWidth: 2.25,
	'hashrate': {fillGraph: true },
	labelsDivStyles: { border: '1px solid black' },
	title: '矿池算力统计',
	xlabel: '时间',
	ylabel: 'Hashes/sec',
	animatedZooms: true,
	includeZero: true,
	yValueFormatter: formatValue,
	yAxisLabelFormatter: formatValue,
	yAxisLabelWidth: 65
	}
  );
</script>
<BR>
<div id="line"></div>
<CENTER><H3>矿池报酬变动</H3></CENTER>
<div id="graphdiv4" style="width:100%; height:150px;"></div>
<script type="text/javascript">
  g3 = new Dygraph(
    document.getElementById("graphdiv4"),
    "poolluckgraph.php",
   	{ strokeWidth: 2.25,
	'hashrate': {fillGraph: true },
	labelsDivStyles: { border: '1px solid black' },
	xlabel: 'Date',
	ylabel: 'Percent of PPS',
	animatedZooms: true
	}
  );
</script>

<div id="graphdiv5" style="width:100%; height:150px;"></div>
<script type="text/javascript">
  g3 = new Dygraph(
    document.getElementById("graphdiv5"),
    "poolluckgraph.php?btc=1",
   	{ strokeWidth: 2.25,
	fillGraph: true,
	labelsDivStyles: { border: '1px solid black' },
	xlabel: 'Date',
	ylabel: 'Est. BTC',
	animatedZooms: true
	}
  );
</script>
<SMALL>(此图以相对于最大每股支付PPS的预估百分比的形式显示了矿池的挖矿收入, 以 1GH/s 算力的矿工在Block 高度为210000开始在<?php echo $poolname; ?>挖矿作为参考)</SMALL><BR>

<BR><div id="line"></div>
<H3><CENTER>顶级大算力矿工 (3 小时平均算力) <A HREF="topcontributors.php">(查看全部)</A></CENTER></H3>

<?php
	# Display partial contributor list on main page
	$minilimit = "limit 10";
	include("topcontributors.php");


?>
<BR><BR>
比特币网络当前挖矿困难度(difficulty): <?php echo $netdiff; ?><BR>
当前困难度下的最大每股支付额(PPS): <?php
$xpps = sprintf("%.12f",currentPPSsatoshi($netdiff)/100000000);
$pps = substr($xpps,0,10);
$subpps = substr($xpps,10,4);
print "$pps<small>$subpps</small>";
?> BTC<BR>
当前困难度下, 以<?php echo $phash; ?>的算力找到一个Block需要的平均时间: <?php echo prettyDuration($netdiff/($sharesperunit*20)); ?><BR>
当前困难度下, 以<?php echo $phash; ?>的算力池子每天平均找到Block的数量: <?php echo printf("%.2f",86400/($netdiff/($sharesperunit*20))); ?><BR>


<?php
	print_stats_bottom();
?>
