#!/usr/bin/php
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

if( isLocked() ) die( "Already running.\n" );

$link = pg_Connect("dbname=$psqldb user=$psqluser password='$psqlpass' host=$psqlhost", PGSQL_CONNECT_FORCE_NEW );


$sql = "select to_timestamp((date_part('epoch', time)::integer / 675::integer) * 675::integer) at time zone 'UTC' as lst from public.shares where server=$serverid order by id desc limit 1";
$result = pg_exec($link, $sql);
$row = pg_fetch_array($result, 0);
$latestsharetime = $row["lst"];

$sql = "select to_timestamp(((date_part('epoch', (select time from wizkid057.stats_shareagg order by time desc limit 1))::integer / 675::integer) * 675::integer)+675::integer) at time zone 'UTC' as fst";
$result = pg_exec($link, $sql);

if(pg_num_rows($result) > 0){
    $row = pg_fetch_array($result, 0);
    $firstsharetime = $row["fst"];
}else{
    $firstsharetime='2014-04-04 00:18:45+08';
}

if($firstsharetime==''){$firstsharetime='2014-04-04 00:18:45+08';}

echo '$latestsharetime',$latestsharetime,'<br>';
echo '$firstsharetime',$firstsharetime,'<br>';

# All the work for this is done by postgresql, which is nice, under this query
$sql = "insert into public.users(username) select distinct username from public.shares where username not in (select username from public.users);";
$result = pg_exec($link, $sql);

$sql = "select id,username from public.users where keyhash is NULL;";
$result = pg_exec($link, $sql);
$numrows = pg_numrows($result);

$sql_list=array();

for($ri = 0; $ri < $numrows; $ri++) {

	$row = pg_fetch_array($result, $ri);
	$username = $row["username"];
	$user_id =  $row["id"];

	$split_chars='_+/|,.:;\\-=`!@#$%^&*()<>\?';
	$punc_pos = false;
	for($fi = 0; $fi < strlen($split_chars);$fi ++){
		$punc_pos = strpos($username, substr($split_chars,$fi,1));
		if($punc_pos !== false){
			break;
		}
	}
	if($punc_pos !== false && $punc_pos>30){
		# a bitcoind address must has atleast 30 chars, maybe 34
		$addr=substr($username,0,$punc_pos);
		$workername = strpbrk($username, $split_chars);
	} else {
		$addr = $username;
		$workername = "";
	}
	$bits =  hex2bits(\Bitcoin::addressToHash160($addr));
	$sql = "update public.users set keyhash='$bits', workername='$workername' where id=$user_id;";
	$sql_list[] =$sql;
}
foreach ($sql_list as $sql) {
	$result = pg_exec($link, $sql);
}


$sql = "INSERT INTO $psqlschema.stats_shareagg (server, time, user_id, accepted_shares, rejected_shares, blocks_found, hashrate)
select server, to_timestamp((date_part('epoch', time)::integer / 675::integer) * 675::integer) AS ttime, user_id,
0+SUM(((our_result::integer) * pow(2,(targetmask-32)))) as acceptedshares, COUNT(*)-SUM(our_result::integer) as rejectedshares, SUM(upstream_result::integer) as blocksfound,
((SUM(((our_result::integer) * pow(2,(targetmask-32)))) * 4294967296) / 675) AS hashrate
from public.shares where time > '$firstsharetime' and to_timestamp((date_part('epoch', time)::integer / 675::integer) * 675::integer) < '$latestsharetime' and server=$serverid group by ttime, server, user_id;";

$sql = "INSERT INTO $psqlschema.stats_shareagg (server, time, user_id, accepted_shares, rejected_shares, blocks_found, hashrate)
select server, to_timestamp((date_part('epoch', time)::integer / 675::integer) * 675::integer) at time zone 'UTC' AS ttime, users.id as user_id,
0+SUM(our_result::integer * targetmask) as acceptedshares, COUNT(*)-SUM(our_result::integer) as rejectedshares, SUM(upstream_result::integer) as blocksfound,
((SUM(our_result::integer * targetmask) * POW(2, 32) ) / 675) AS hashrate
from public.shares left join users on shares.username=users.username where time > '$firstsharetime' and to_timestamp((date_part('epoch', time)::integer / 675::integer) * 675::integer) at time zone 'UTC'  < '$latestsharetime' and server=$serverid group by ttime, server, users.id;";
$result = pg_exec($link, $sql);

unlink( LOCK_FILE );


?>
