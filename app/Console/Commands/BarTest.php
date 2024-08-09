<?php

namespace Kami\Cocktail\Console\Commands;

use Google\Protobuf\Internal\Message;
use Illuminate\Console\Command;

class BarTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:bar-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $message = new Message();
        $file = storage_path('objects2.pb');
        $data = file_get_contents($file);
        $d = new \Kami\Cocktail\ProtobufDecoder($data);
        $d2 = new \Kami\Cocktail\PD();
        $objects = $d->decode();
        // $encodedData = hex2bin('0aa5010a115265636970653a746573745f6b61726c6f128f010a0a746573745f6b61726c6f120a54657374206b61726c6f2a100a0367696e101e1a026d6c450000f0412a120a056c656d6f6e10161a026d6c450000b4412a180a0b73797275705f7375676172100f1a026d6c450000704132096d61726761726974613a06637573746f6d3a0a6e6f6e616c636f686f6c40014a07547768646b646b520b4b666d640a4464660a44640ae4050a185265636970653a6d795f69726973685f636f666665655f3212c7050a116d795f69726973685f636f666665655f3212114d7920497269736820436f6666656520322a140a07776869736b657910321a026d6c45000048422a130a06636f6666656510781a026d6c450000f0422a120a05637265616d10321a026d6c45000048422a250a05737567617210011a0874656173706f6f6e220b73797275705f7375676172450000803f320c69726973685f636f666665653a0b736f66745f7265636970653a086f6666696369616c3a06637573746f6d40014a90024163636f7264696e6720746f206f6e65206f6620746865206c6567656e6473206f66207468697320636f636b7461696c2773206f726967696e2c20746865206f726967696e616c20726563697065207761732063726561746564206279204a6f7365706820536865726964616e20696e2031393430207768656e20612067726f7570206f662070617373656e67657273206172726976656420617420616e20497269736820616972706f727420616e6420536865726964616e20747269656420746f207761726d207468656d20757020627920616464696e6720776869736b657920746f20636f666665652e204e6f7720697420697320616e20494241206f6666696369616c20636f636b7461696c2e52bd01312e20506f757220686f7420636f6666656520696e746f20616e20497269736820636f6666656520676c6173732e0a322e204164642035306d6c206f6620776869736b65792c20616e642061646420612074656173706f6f6e206f66207375676172206f7220356d6c206f662073756761722073797275702e205374697220756e74696c2074686520737567617220697320646973736f6c7665642e0a332e20466c6f617420637265616d206f6e20746f70206361726566756c6c792e5a1d617265636970655f69726973685f636f666665655f69636f6e2e706e676218617265636970655f69726973685f636f666665652e6a70670a290a085461673a74657374121d0a04746573741204746573741800200128a38aedfdffffffff0170010ad5010a0e5265636970653a6b61725f67756e12c2010a076b61725f67756e12074b61722067756e2a180a0b7279655f776869736b6579103c1a026d6c45000070422a190a0c7665726d6f7574685f72656410011a026f7a450000c03f32096d61726761726974613a06637573746f6d3a0773686f6f7465723a0d7374726f6e675f7265636970653a047465737440014a104465736372697074696f6e20696e666f5215312e20727669206b6f72616b0a322e2044727567695a0d736b61725f67756e5f69636f6e620e736b61725f67756e5f696d616765');
        // print_r($d2->decode($data));
        // dump($d2->decode($data));
        dump($objects);
    }
}