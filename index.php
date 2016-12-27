<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


require_once (__DIR__ . "/lib/class.db.php");
require_once (__DIR__ . "/lib/Helpers.php");
require_once (__DIR__ . "/lib/jdf.php");


$db = new DB();

$totalUsage = $db->query("SELECT  SUM(c.size)/(1024*1024) traffic FROM connections c")[0];


$totalPerDay = $db->query("SELECT c.date,UNIX_TIMESTAMP(c.date) tdate, SUM(c.size)/(1024*1024) traffic
FROM connections c
LEFT JOIN names n ON(n.mac=c.mac)
GROUP BY c.date
ORDER BY c.date desc,traffic desc");


$perUser = $db->query("SELECT IFNULL(name,IFNULL(c.mac,'<OTHER>')) name, SUM(c.size)/(1024*1024) traffic
FROM connections c
LEFT JOIN `names` n ON(n.mac=c.mac)
GROUP BY c.mac
ORDER BY traffic desc");

$userPerDay = $db->query("SELECT IFNULL(name,IFNULL(c.mac,'<OTHER>')) name,c.date,UNIX_TIMESTAMP(c.date) tdate, SUM(c.size)/(1024*1024) traffic
FROM connections c
LEFT JOIN `names` n ON(n.mac=c.mac)
GROUP BY c.mac,c.date
ORDER BY c.date desc,traffic desc");
?>
<META HTTP-EQUIV="refresh" CONTENT="1">
<CENTER>
    <table border="1">
        <CAPTION>TOTAL USAGE</CAPTION>
        <tr>
            <th>Usage</th>
            <td><?= number_format($totalUsage['traffic'], 2) . " MB" ?></td>
        </tr>
    </table>
    <hr/>
    <table border="1">
        <CAPTION>TOTAL USAGE PER USER</CAPTION>
        <tr>
            <th>User</th>
            <th>Usage</th>
        </tr>
<?php foreach ($perUser as $row): ?>
            <tr>
                <td><?= htmlentities($row['name']) ?></td>
                <td><?= number_format($row['traffic'], 2) . " MB" ?></td>
            </tr>
<?php endforeach; ?>  
    </table>
    <hr/>
    <table border="1">
        <CAPTION> USAGE PER DAY </CAPTION>
        <tr>
            <th>Date</th>
            <th>JDate</th>
            <th>Usage</th>
        </tr>
<?php foreach ($totalPerDay as $row): ?>
            <tr <?php if (date("Y-m-d") == $row['date']) echo "style='background: #ccccff'"; ?>>
                <td><?= $row['date'] ?></td>
                <td><?= jdate('Y/m/d', $row['tdate']) ?></td>
                <td><?= number_format($row['traffic'], 2) . " MB" ?></td>
            </tr>
<?php endforeach; ?>  
    </table>
    <hr/>
    <table border="1">
        <CAPTION>USAGE PER USER PER DAY </CAPTION>
        <tr>
            <th>User</th>
            <th>Date</th>
            <th>JDate</th>
            <th>Usage</th>
        </tr>
<?php foreach ($userPerDay as $row): ?>
            <tr <?php if (date("Y-m-d") == $row['date']) echo "style='background: #ccccff'"; ?>>
                <td><?= htmlentities($row['name']) ?></td>
                <td><?= $row['date'] ?></td>
                <td><?= jdate('Y/m/d', $row['tdate']) ?></td>
                <td><?= number_format($row['traffic'], 2) . " MB" ?></td>
            </tr>
<?php endforeach; ?>  
    </table>


</CENTER>
