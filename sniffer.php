<?php

require_once (__DIR__ . "/lib/class.db.php");
require_once (__DIR__ . "/lib/Helpers.php");
//tcpdump -B 8192 -s 100 -nn -N  -t -l -i wlan0 tcp or udp and not port 22

//turn off precus mode
//exec("ifconfig wlan1 promisc");

$cmd ="tshark -l -s 100 -T fields -E separator=,"; //init
$cmd.=" -b -b files:10 filesize:2 -w /tmp/probe-req.tmp"; //file buffer size limitation
$cmd.=" -i wlan1"; //interfaces
//$cmd.=" -i wlan0";
$cmd.=" -e eth.src -e eth.dst -e ip.src -e ip.dst -e frame.len"; //capture fields
//$cmd.=" -Y 'eth.dst==3c:47:11:03:76:42'";
//echo $cmd.PHP_EOL;die;
//
//$cmd.=" -Y 'eth.dst!=86:3f:5d:15:16:7d and eth.src!=86:3f:5d:15:16:7d'"; //skip forwarded traffic of wlan1 to prevent duplicate calulation
//$cmd .= " -Y 'ip.src ==192.0.73.2'"; //for test mode : https://www.gravatar.com/avatar/
//$cmd = "ping 127.0.0.1";
$descriptorspec = array(
    0 => array("pipe", "r"), // stdin is a pipe that the child will read from
    1 => array("pipe", "w"), // stdout is a pipe that the child will write to
    2 => array("pipe", "w")    // stderr is a pipe that the child will write to
);


/* while(true){
  flush();
  ob_end_flush();
  echo "----------";
  ob_start();
  sleep(1);
  } */
$db = new DB();
$buffer = [];
$process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());
//stream_set_blocking($pipes[0], 0);
//stream_set_blocking($pipes[1], 0);
//stream_set_blocking($pipes[2], 0);
if (is_resource($process)) {
    while ($rowdata = fgets($pipes[1])) {
        if (preg_match('/^(?<src_mac>[\:\da-z]+),(?<dst_mac>[\:\da-z]+),(?<src_ip>[\.\d]+),(?<dst_ip>[\.\d]+),(?<size>\d+)\n$/', $rowdata, $matches)) {
            echo time()."\t".$rowdata;
        } elseif (!preg_match('/(?<src_mac>[\:\da-z]+),(?<dst_mac>[\:\da-z]+),(?<src_ip>[\.\d]+),(?<dst_ip>[\.\d]+),([\.\d]+),([\.\d]+),(?<size>\d+)[\n\r]+$/', $rowdata, $matches)) {
            fwrite(STDERR, "packet data parse failed : $rowdata\n");
            continue;
        }
        if(!isset($matches['size'])){
            fwrite(STDERR, "Parse error in $rowdata\n");
            continue;
        }
        
        if($matches['size']==0){
            continue;
        }
//        $filter=['src_mac','dst_mac','src_ip','dst_ip','size'];
//        $conData=array_intersect_key($matches, array_flip($filter));


        $isSrcPrivate = ip_is_private($matches['src_ip']);
        $isDstPrivate = ip_is_private($matches['dst_ip']);
        if ($isSrcPrivate && $isDstPrivate) {
            continue;
        }

        $matches['mac'] = NULL;
        if (!$isSrcPrivate) {
            //force source ip to be local ip and public ip as destination ip
            swapVars($matches['src_ip'], $matches['dst_ip']);

            $matches['src_mac'] = NULL;
            $matches['mac'] = $matches['dst_mac'];
        }

        if (!$isDstPrivate) {

            $matches['dst_mac'] = NULL;
            $matches['mac'] = $matches['src_mac'];
        }

        
        $filter = ['mac', 'src_ip', 'dst_ip', 'size'];
        //filter matches to table fields
        $conData = array_intersect_key($matches, array_flip($filter));
        $conData['date'] = date("Y-m-d");

        $buffer[] = $conData;
        if ($buffer >= 1000) 
        {
            foreach ($buffer as $conData) 
            {
                if (!$db->insert('connections', $conData, true, "UPDATE size=size+VALUES(size)" /* add up trafic for ever mac related to unique key index */)) {
                    fwrite(STDERR, "Insert Fail \n------------------------------\n");
                }
            }
            $buffer = [];
        }
    }
}
